<?php

trait DatabaseSeed
{

    public function seedDatabase()
    {

        $database = $this->env->mysqlDatabase;

        $conn = $this->openConnection();
        $this->useDatabase($database);

        foreach ($this->yaml->seeds as $tableName => $tables) {

            $columns  = @$tables['columns'];
            $updater  = @$tables['updater'];
            $reseeded = false;

            $canUpdate = ($updater && in_array($updater, $columns));
            $idx       = array_search($updater, $columns);
            if ($idx === false) {$canUpdate = false;}

            foreach ($tables['files'] as $filename) {

                $filepath = realpath(__DIR__ . "/../../seeds/$filename");
                if (! file_exists($filepath)) {
                    continue;
                }

                $data = $this->readFileData($filepath);

                if ($canUpdate) {
                    // insert or update by handle.

                    $handles = array_map(fn($row) => $row[$idx], $data);

                    // SELECT * FROM `` WHERE `` IN ()
                    $query  = $this->createListQuery($tableName, $updater, $handles);
                    $founds = $this->mysqlQuery($query);

                    $found = array_map(fn($row) => $row[$updater], $founds);

                    $hdlinserts = array_diff($handles, $found);
                    $hdlupdates = array_diff($handles, $hdlinserts);

                    if (count($hdlinserts)) {
                        $inserts = array_filter($data,
                            fn($row) => in_array($row[$idx], $hdlinserts));

                        $q = $this->insertSyntax($tableName, $columns, $inserts);
                        $r = $this->mysqlQuery($q);

                    }

                    if (count($hdlupdates)) {
                        $updates = array_filter($data,
                            fn($row) => in_array($row[$idx], $hdlupdates));

                        $q = $this->updateSyntax($tableName, $updater, $columns, $updates);
                        $r = $this->mysqlQuery($q);

                        $reseeded = true;
                    }

                } else {
                    // insert
                    $q = $this->insertSyntax($tableName, $columns, $data);
                    $r = $this->mysqlQuery($q);
                }
            }

            echo $reseeded ? "Reseeded" : "Seeded";
            echo " $tableName table\n";

        }

        $this->closeConnection();

    }

    private function updateSyntax($tableName, $updater, $columns, $data)
    {
        // START TRANSACTION;
        // UPDATE products SET price = 10 WHERE id = 1;
        // UPDATE products SET price = 20 WHERE id = 2;
        // -- ... more updates
        // COMMIT;

        $upx = array_search($updater, $columns);

        $q = '';
        $q .= "START TRANSACTION;\n";
        foreach ($data as $row) {
            $sets = [];
            foreach ($columns as $idx => $column) {
                $sets[$idx] = "`$columns[$idx]` = " . $this->cast($row[$idx]);
            }
            $where = $sets[$upx];
            unset($sets[$upx]);
            $values = join(', ', $sets);
            $q .= "UPDATE `$tableName` SET $values WHERE $where; \n";

        }
        $q .= "COMMIT;\n";

        return $q;
    }

    private function insertSyntax($tableName, $columns, $data)
    {
        // INSERT INTO Customers (CustomerName, Address, City, PostalCode, Country)
        // VALUES
        // ('Cardinal', 'Skagen 21', 'Stavanger', '4006', 'Norway'),
        // ('Greasy Burger', 'Gateveien 15', 'Sandnes', '4306', 'Norway'),

        $casts = [];
        foreach ($data as $row) {
            $cols = [];
            foreach ($row as $idx => $column) {
                $cols[$idx] = $this->cast($column);
            }
            $casts[] = '(' . join(', ', $cols) . ')';
        }

        $q = '';
        $q .= "INSERT INTO `$tableName`";
        $q .= "(`" . join('`, `', $columns) . "`)\nVALUES\n ";
        $q .= join(", \n ", $casts) . ";";

        return $q;

    }

    private function readFileData($filepath)
    {

        $data  = [];
        $file  = file_get_contents($filepath);
        $lines = explode("\n", $file);

        $rows = array_filter($lines, fn($row) => ('' !== trim($row)));

        foreach ($rows as $row) {
            $data[] = str_getcsv($row, ',', "'", "\\");
        }

        return $data;
    }

}

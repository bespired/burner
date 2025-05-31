<?php

trait DatabaseSeed
{

    private $roothandle = null;

    public function seedDatabase()
    {

        $database = $this->env->mysqlDatabase;

        $conn = $this->openConnection();
        $this->useDatabase($database);

        foreach ($this->yaml->seeds as $tableName => $tables) {

            foreach ($tables['files'] as $filename) {

                $seedtype = str_ends_with($filename, '.csv') ? 'csv' : 'yml';

                switch ($seedtype) {
                    case 'csv':
                        $this->seedCsv($tableName, $tables, $filename);
                        break;

                    case 'yml':
                        $this->seedYml($tableName, $tables, $filename);
                        break;

                }

            }

            echo "Seeded $tableName table\n";
        }
        $this->closeConnection();

    }

    private function seedYml($tableName, $tables, $filename)
    {
        $filepath = realpath(__DIR__ . "/../../seeds/$filename");
        if (! file_exists($filepath)) {
            echo "Cannot find $filename in seeds folder.\n";
            return;
        }

        $data = $this->readYmlData($filepath);
        $data = $this->keepYmlData($data);

        // at this point the data can contain sensitive data

        // if update then children need to be checked on parent() ...
        // put that on todo list.

        $root      = $data->seeds['root'];
        $tableName = array_keys($root)[0];

        $this->seedTableEntry($data, 'root', $tableName);

        foreach ($data->seeds['children'] as $tableName => $child) {
            $this->seedTableEntry($data, 'children', $tableName);
        }

    }

    // entry is root or any of the children
    private function seedTableEntry($data, $area, $tableName)
    {

        $auto        = null;
        $canUpdate   = false;
        $entry       = $data->seeds[$area];
        $entryTable  = $entry[$tableName]['seed'];
        $structTable = $entry[$tableName]['struct'];

        if (isset($entry[$tableName]['updater'])) {
            $updater = $entry[$tableName]['updater'];

            // identifier is the (auto) from the record
            $identifier = array_search('(auto)', $structTable);
            // if no identifier then what?
            if (! $identifier) {
                print_r($structTable);
                echo "Structure issue with the seed file\n";
                exit;
            }

            // Check if record already exists.
            // Then the whole tree needs to be updated instead of created.
            $q = '';
            $q .= "SELECT `$identifier` FROM `$tableName` ";
            $q .= "WHERE `$updater` = " . $this->cast($entryTable[$updater]);
            $r = $this->mysqlQuery($q);
            if (count($r) > 0) {
                $auto = array_values($r[0])[0];
            }
            $canUpdate = $auto ? true : false;
        }

        $data = $this->autoYmlData($data, $auto);

        $entry      = $data->seeds[$area];
        $entryTable = $entry[$tableName]['seed'];

        $columns = array_keys($entryTable);
        $inserts = array_values($entryTable);

        if ($canUpdate) {
            // UPDATE
            echo "Seed update $tableName \n";
            $q = $this->updateSyntax($tableName, $updater, $columns, [$inserts]);
        } else {
            echo "Seed insert $tableName \n";
            $q = $this->insertSyntax($tableName, $columns, [$inserts]);
        }

        // echo "about to $q \n";
        $r = $this->mysqlQuery($q);
    }

    private function seedCsv($tableName, $tables, $filename)
    {

        $columns  = @$tables['columns'];
        $updater  = @$tables['updater'];
        $reseeded = false;

        $canUpdate = ($updater && in_array($updater, $columns));
        $idx       = array_search($updater, $columns);
        if ($idx === false) {$canUpdate = false;}

        $filepath = realpath(__DIR__ . "/../../seeds/$filename");
        if (! file_exists($filepath)) {
            echo "Cannot find $filename in seeds folder.\n";
            return;
        }

        $data = $this->readCsvData($filepath);

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
                $mysqlColumn = str_replace('-', '_', $columns[$idx]);
                $sets[$idx]  = "`$mysqlColumn` = " . $this->cast($row[$idx]);
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

        $columns = array_map(fn($c) => str_replace('-', '_', $c), $columns);

        $q = '';
        $q .= "INSERT INTO `$tableName` ";
        $q .= "(`" . join('`, `', $columns) . "`)\nVALUES\n ";
        $q .= join(", \n ", $casts) . ";";

        return $q;

    }

    // fill in (auto), (now), env(...), hash(...), parent(...)
    private function autoYmlData($data, $auto = null)
    {
        $root      = $data->seeds['root'];
        $tableName = array_keys($root)[0];

        $data->seeds['root'][$tableName]['seed'] =
        $this->valueSwappers($tableName, $root[$tableName]['seed'], [$auto, null]);

        $children = $data->seeds['children'];
        foreach ($children as $tableName => $table) {
            $data->seeds['children'][$tableName]['seed'] =
            $this->valueSwappers($tableName, $table['seed'], [null, $auto]);
        }

        return $data;
    }

    private function valueSwappers($tableName, $data, $relate)
    {
        $envre = '/env\(([\s\S]*?)\)/m';
        $hshre = '/hash\(([\s\S]*?)\)/m';
        $parre = '/parent\(([\s\S]*?)\)/m';

        $auto   = $relate ? $relate[0] : null;
        $parent = $relate ? $relate[1] : null;

        // echo "$tableName : $auto : $parent \n";

        foreach ($data as $columnName => $value) {
            switch (gettype($value)) {
                case 'string':
                    switch ($value) {
                        case '(auto)':
                            $data[$columnName] = $auto ?? $this->autoHandle($tableName);
                            $this->roothandle  = $data[$columnName];

                            break;
                        case '(now)':
                            $data[$columnName] = gmdate("Y-m-d H:i:s");
                            break;
                    }

                    if ($value && substr_count($value, 'env(')) {
                        preg_match_all($envre, $value, $matches, PREG_SET_ORDER, 0);
                        if (count($matches) > 0) {
                            $name = $matches[0][1];
                            $reps = $matches[0][0];
                            if (property_exists($this->env, $name)) {
                                $env   = $this->env->$name;
                                $value = str_replace($reps, $env, $value);

                                $data[$columnName] = $value;
                            }
                        }
                    }
                    if ($value && substr_count($value, 'hash(')) {
                        preg_match_all($hshre, $value, $matches, PREG_SET_ORDER, 0);
                        if (count($matches) > 0) {
                            $pass   = $matches[0][1];
                            $reps   = $matches[0][0];
                            $hashed = password_hash($pass, PASSWORD_DEFAULT);
                            $value  = str_replace($reps, $hashed, $value);

                            $data[$columnName] = $value;
                        }
                    }

                    if ($value && substr_count($value, 'parent(')) {
                        preg_match_all($parre, $value, $matches, PREG_SET_ORDER, 0);
                        if (count($matches) > 0) {
                            $column = $matches[0][1];
                            $reps   = $matches[0][0];

                            $data[$columnName] = $parent ?? $this->roothandle;
                        }
                    }

                    break;
                case 'boolean':
                    $data[$columnName] = $value ? 1 : 0;
                    break;

            }
        }
        return $data;
    }

    private function autoHandle($tableName)
    {
        $mt   = microtime();
        $num  = intval(substr($mt, 2, 4) . substr($mt, -10));
        $fbit = base_convert($num, 10, 36);
        $name = str_ireplace(['a', 'e', 'i', 'o', 'u'], '', $tableName);

        return $name . '-' . substr($fbit, 0, 4) . '-' . substr($fbit, 4);
    }

    private function readCsvData($filepath)
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

    private function keepYmlData($data)
    {

        $entry      = $data->seeds['root'];
        $tableName  = array_keys($entry)[0];
        $entryTable = $entry[$tableName]['seed'];

        $data->seeds['root'][$tableName]['struct'] = $entryTable;

        foreach ($data->seeds['children'] as $tableName => $entry) {
            $data->seeds['children'][$tableName]['struct'] = $entry['seed'];
        }

        return $data;
    }

    private function readYmlData($filepath)
    {

        $data = [];
        $path = explode('/', $filepath);
        $path = array_reverse($path);
        $file = file_get_contents($filepath);

        $parsed = (object) @yaml_parse($file);

        if (property_exists($parsed, 'scalar')) {
            exit("Parsing of seedfile $path[0]failed . \n ");
        }

        if (! property_exists($parsed, 'seeds')) {
            exit("$path[0]failed, it shouldhaveseeds . \n ");
        }

        return $parsed;
    }

}

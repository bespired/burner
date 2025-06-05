<?php

trait DatabaseSeed
{
    private $autohandles = [];

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
                        $this->autohandles = [];
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
        $data = $this->structYmlData($data);

        // at this point the data can contain sensitive data

        $root      = $data->seeds['root'];
        $tableName = array_keys($root)[0];

        $this->rootSeedTableEntry($data->seeds['root'], $tableName, 0);

        foreach ($data->seeds['children'] as $tableName => $child) {
            for ($idx = 0; $idx < count($child['seed']); $idx++) {
                $this->rootSeedTableEntry($data->seeds['children'], $tableName, $idx);
            }
        }
    }

    private function rootSeedTableEntry($entry, $tableName, $idx)
    {
        $canUpdate = $this->canUpdate($entry, $tableName, $idx);

        $entryTable  = $entry[$tableName]['seed'][$idx];
        $structTable = $entry[$tableName]['struct'][$idx];

        $entryTable = $this->valueSwappers($tableName, $entryTable, $idx);

        $columns = array_keys($entryTable);
        $inserts = array_values($entryTable);

        if ($canUpdate) {
            // UPDATE
            $updater = $entry[$tableName]['updater'];
            $key     = array_search('(auto)', $structTable);
            $unsets  = array_search($key, $columns);

            // echo "Seed update $tableName \n";
            $q = $this->updateSyntax($tableName, $updater, $columns, [$inserts], $unsets);
        } else {
            // echo "Seed insert $tableName \n";
            $q = $this->insertSyntax($tableName, $columns, [$inserts]);
        }

        // echo "about to $q \n";
        $r = $this->mysqlQuery($q);
    }

    private function canUpdate($entry, $tableName, $idx)
    {
        if (! isset($entry[$tableName]['updater'])) {
            return false;
        }

        $updater = $entry[$tableName]['updater'];

        $structTables = $entry[$tableName]['struct'];
        $structTable  = $this->isIndexedArray($structTables) ? $structTables[$idx] : $structTables;

        $identifier = array_search('(auto)', $structTable);

        if (! $identifier) {
            return false;
        }

        $auto  = false;
        $combi = "$tableName--$identifier--$idx";

        $entryTables = $entry[$tableName]['seed'];
        $entryTable  = $this->isIndexedArray($entryTables) ? $entryTables[$idx] : $entryTables;

        $entryTable = $this->valueSwappers($tableName, $entryTable, $idx);

        $q = '';
        $q .= "SELECT `$identifier` FROM `$tableName` ";
        $q .= "WHERE `$updater` = " . $this->cast($entryTable[$updater]);

        // echo $q . "\n";

        $r = $this->mysqlQuery($q);

        if (count($r) > 0) {
            $auto = array_values($r[0])[0];
            // echo "found:  $auto for $combi \n";
            $this->autohandles[$combi] = $auto;
        }

        return $auto ? true : false;
    }

    private function seedCsv($tableName, $tables, $filename)
    {
        $columns  = @$tables['columns'];
        $updater  = @$tables['updater'];
        $reseeded = false;

        $canUpdate = ($updater && in_array($updater, $columns));
        $idx       = array_search($updater, $columns);
        if ($idx === false) {
            $canUpdate = false;
        }

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

    private function updateSyntax($tableName, $updater, $columns, $data, $unsets = null)
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
                $sqlColumn  = str_replace('-', '_', $columns[$idx]);
                $sets[$idx] = "`$sqlColumn` = " . $this->cast($row[$idx]);
            }
            $where = $sets[$upx];
            unset($sets[$upx]);
            if ($unsets !== null) {
                unset($sets[$unsets]);
            }

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
        $q .= '(`' . join('`, `', $columns) . "`)\nVALUES\n ";
        $q .= join(", \n ", $casts) . ';';

        return $q;
    }

    private function valueSwappers($tableName, $data, $idx)
    {
        $envre = '/env\(([\s\S]*?)\)/m';
        $hshre = '/hash\(([\s\S]*?)\)/m';

        foreach ($data as $columnName => $value) {
            $combi = "$tableName--$columnName--$idx";

            switch (gettype($value)) {
                case 'string':
                    switch ($value) {
                        case '(auto)':
                            if (! isset($this->autohandles[$combi])) {
                                $handle                    = $this->autoHandle($tableName);
                                $data[$columnName]         = $handle;
                                $this->autohandles[$combi] = $handle;
                            } else {
                                $data[$columnName] = $this->autohandles[$combi];
                            }

                            break;
                        case '(now)':
                            $data[$columnName] = gmdate('Y-m-d H:i:s');
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

                    if ($value && substr_count($value, '--')) {

                        $handle  = $value;
                        $handle0 = $value . '--0';
                        if (array_key_exists($handle0, $this->autohandles)) {
                            $handle = $handle0;
                        }

                        $data[$columnName] = @$this->autohandles[$handle];
                        // echo "$combi is filled with " . $data[$columnName] . "\n";
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
            $data[] = str_getcsv($row, ',', "'", '\\');
        }

        return $data;
    }

    private function structYmlData($data)
    {
        $entry      = $data->seeds['root'];
        $tableName  = array_keys($entry)[0];
        $entryTable = $entry[$tableName]['seed'];

        if (! $this->isIndexedArray($entryTable)) {
            $entryTable = [$entryTable];
        }

        $data->seeds['root'][$tableName]['seed']   = $entryTable;
        $data->seeds['root'][$tableName]['struct'] = $entryTable;

        foreach ($data->seeds['children'] as $tableName => $entry) {
            $entryTable = $entry['seed'];
            if (! $this->isIndexedArray($entryTable)) {
                $entryTable = [$entryTable];
            }
            $data->seeds['children'][$tableName]['seed']   = $entryTable;
            $data->seeds['children'][$tableName]['struct'] = $entryTable;
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

    private function isIndexedArray($array)
    {
        return array_keys($array) === range(0, count($array) - 1);
    }
}

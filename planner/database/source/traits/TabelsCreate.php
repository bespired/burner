<?php

trait TabelsCreate
{

    public function defineTables()
    {
        $database = $this->env->mysqlDatabase;

        $conn = $this->openConnection();
        $this->useDatabase($database);

        foreach ($this->yaml->tables as $table_name => &$table) {
            $query  = "SHOW TABLES LIKE '$table_name'";
            $result = $conn->query($query);

            $table['status'] = ($result->num_rows > 0) ? 'alter' : 'create';
        }

        $this->closeConnection();
    }

    public function createTables()
    {

        $database = $this->env->mysqlDatabase;

        $conn = $this->openConnection();
        $this->useDatabase($database);

        $types = $this->yaml->mysql['types'];

        foreach ($this->yaml->tables as $tableName => $table) {

            if ('create' === $table['status']) {
                // yes create this one
                $columns = [];
                foreach ($table['columns'] as $columnName => $column) {

                    $name  = str_replace('-', '_', $columnName);
                    $type  = explode('(', $column)[0];
                    $enums = '';

                    if ($type === 'enum') {
                        $enum  = rtrim(explode('(', $column)[1], ')');
                        $raw   = $this->yaml->enums[$enum];
                        $enums = "('" . join("', '", $raw) . "')";
                    }

                    $columns[$name] = $types[$type] . $enums;
                }

                $query  = $this->createTableQuery($tableName, $columns);
                $result = $conn->query($query);

                echo $result ? "$tableName created.\n" : "Error on $tableName.\n";

            }

        }

        $this->closeConnection();

    }

    public function alterTables()
    {
        $database = $this->env->mysqlDatabase;

        $conn = $this->openConnection();
        $this->useDatabase($database);

        $types = $this->yaml->mysql['types'];

        foreach ($this->yaml->tables as $tableName => $table) {

            if ('alter' === $table['status']) {
                // alter this one table

                $query  = "DESCRIBE `$tableName`";
                $result = $conn->query($query);
                $array  = $this->mysqlResult($result);

                $this->alterDrops($tableName, $table, $array);
                $this->alterAdds($tableName, $table, $array);
                $this->alterEnums($tableName, $table, $array);
                $this->alterSizes($tableName, $table, $array);

            }

        }

        $this->closeConnection();

    }

    private function alterDrops($tableName, $table, $array)
    {
        // ALTER TABLE table_name
        // DROP COLUMN column_name_1,
        // DROP COLUMN column_name_2;

        $drops = [];
        foreach ($array as $column) {
            $field = str_replace('_', '-', $column['Field']);
            if (! isset($table['columns'][$field])) {
                if ($field !== 'id') {
                    $drops[$field] = $column['Field'];
                }
            }
        }
        if (count($drops)) {
            $mapped = array_map(fn($a) => "DROP COLUMN `$a`", $drops);
            $q      = '';
            $q .= "ALTER TABLE `$tableName` \n";
            $q .= join(",\n", $mapped) . ";\n";

            $result = $this->conn->query($q);
            $names  = join('`, `', $drops);
            $plural = count($drops) == 1 ? 'column' : 'columns';
            echo $result ? "Dropped $plural `$names` in `$tableName`.\n" : "Drop failed.\n";
        }

    }

    private function alterAdds($tableName, $table, $array)
    {
        // ALTER TABLE table_name
        // ADD COLUMN new_column_name data_type
        // [FIRST | AFTER existing_column];

        $adds = [];
        foreach ($array as $column) {
            $field            = str_replace('_', '-', $column['Field']);
            $dbfields[$field] = $column['Field'];
        }

        $types = $this->yaml->mysql['types'];

        $adds   = [];
        $behind = ' first';
        foreach ($table['columns'] as $columnName => $column) {
            $name = str_replace('-', '_', $columnName);

            if (! in_array($name, $dbfields)) {
                $type  = explode('(', $column)[0];
                $enums = '';

                if ($type === 'enum') {
                    $enum  = rtrim(explode('(', $column)[1], ')');
                    $raw   = $this->yaml->enums[$enum];
                    $enums = "('" . join("', '", $raw) . "')";
                }

                $adds[$name]  = strtoupper($types[$type] . $enums);
                $after[$name] = $behind;
            }
            $behind = " AFTER `$name`";
        }

        if (count($adds)) {
            foreach ($adds as $add => $type) {
                $q = '';
                $q .= "ALTER TABLE `$tableName` ";
                $q .= "ADD COLUMN `$add` $type ";
                $q .= $after[$add] . ";\n";

                $result = $this->conn->query($q);
                echo $result ? "Added column `$add` in `$tableName`.\n" : "Add failed.\n";
            }
        }

    }

    private function alterEnums($tableName, $table, $array)
    {
        // ALTER TABLE carmake
        // MODIFY `country` ENUM('Japan', 'USA', 'England', 'Sweden', 'Malaysia');

        $change = [];
        foreach ($array as $column) {
            $type = $column['Type'];
            if (str_starts_with($type, 'enum')) {

                // this is an enum, is it changed?
                $dbfield = str_replace('_', '-', $column['Field']);
                $ymfield = $table['columns'][$dbfield];

                $enum  = rtrim(explode('(', $ymfield)[1], ')');
                $raw   = $this->yaml->enums[$enum];
                $enums = "enum('" . join("','", $raw) . "')";

                if (strtoupper($type) !== strtoupper($enums)) {
                    $change[$column['Field']] = strtoupper($enums);
                }
            }
        }
        if (count($change)) {
            foreach ($change as $column => $enum) {
                $q = '';
                $q .= "ALTER TABLE `$tableName` ";
                $q .= "MODIFY `$column` $enum;";

                $result = $this->conn->query($q);
                echo $result ? "Column `$column` enum changed in `$tableName`.\n" : "Enum failed.\n";
            }
            echo "Altering enums can give strange results if the enums are already handed out.\n";
            echo "Some code could be in place here to remap existing enums to new enumurating.\n";

        }

    }

    private function alterSizes($tableName, $table, $array)
    {
        // change all new types?
        // ALTER TABLE <table_name> MODIFY <col_name> VARCHAR(65353);

        $types = $this->yaml->mysql['types'];

        // check what we got agains what we want.
        $change = [];
        foreach ($array as $column) {

            $field = str_replace('_', '-', $column['Field']);
            if ($field === 'id') {
                continue;
            }

            $type = $column['Type'];
            if (str_starts_with($type, 'enum')) {
                continue;
            }

            $ymtype = $table['columns'][$field];
            $wanted = $types[$ymtype];

            $notnull = ($column['Null'] == 'NO') ? ' not null' : '';

            if ($type == 'tinyint(1)') {
                $type = 'bool';
            }

            if ($type . $notnull !== $wanted) {

                $change[$column['Field']] = $wanted;
            }

        }
        if (count($change)) {
            foreach ($change as $column => $change) {
                $q = '';
                $q .= "ALTER TABLE `$tableName` ";
                $q .= "MODIFY `$column` $change;";

                $result = $this->conn->query($q);
                echo $result ? "Column `$column` changed to $change in `$tableName`.\n" : "Change failed.\n";
            }

        }

    }

}

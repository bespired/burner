<?php

trait TableIndeces
{
    public function indexTables()
    {
        $database = $this->env->mysqlDatabase;

        $conn = $this->openConnection();
        $this->useDatabase($database);

        foreach ($this->yaml->tables as $tableName => $table) {
            $query     = "SHOW INDEX FROM `$tableName`";
            $dbindeces = $this->mysqlQuery($query);

            $ymindeces = @$table['index'];
            if (gettype($ymindeces) == 'string') {
                $ymindeces = [$ymindeces];
            }
            $ymuniques = @$table['unique'];
            if ($ymuniques && gettype($ymuniques) == 'string') {
                $ymuniques = [$ymuniques];
            }

            if (count($dbindeces ?? []) > 1) {
                $this->delIndeces($tableName, $dbindeces, $ymindeces, $ymuniques);
            }

            if (count($ymindeces ?? []) > 1) {
                $this->addIndeces($tableName, $dbindeces, $ymindeces);
            }

            if (count($ymuniques ?? []) > 1) {
                $this->addUniques($tableName, $dbindeces, $ymuniques);
            }
        }

        $this->closeConnection();
    }

    private function delIndeces($tableName, $dbindeces, $ymindeces, $ymuniques)
    {
        // DROP INDEX index_name ON $tableName

        $drops = [];
        foreach ($dbindeces as $dbindex) {
            $column = str_replace('_', '-', $dbindex['Column_name']);

            if ($column == 'id') {
                continue;
            }

            if ((! in_array($column, $ymindeces)) && (! in_array($column, $ymuniques ?? []))) {
                $drops[] = $dbindex['Key_name'];
            }
        }

        if (count($drops)) {
            foreach ($drops as $drop) {
                $q = '';
                $q .= "DROP INDEX `$drop` ";
                $q .= "ON `$tableName`; ";

                // $result = $this->conn->query($q);
                $result = $this->mysqlQuery($q);

                echo $result ? "Drop index $drop.\n" : "Failed index drop $drop.\n";
            }
        }
    }

    private function addIndeces($tableName, $dbindeces, $ymindeces)
    {
        // CREATE INDEX your_index_name ON your_table_name(your_column_name) USING BTREE;

        $names = [];
        foreach ($dbindeces as $dbindex) {
            $column         = str_replace('_', '-', $dbindex['Column_name']);
            $names[$column] = $column;
        }

        $adds = [];
        foreach ($ymindeces as $index) {
            if (! in_array($index, $names)) {
                $adds[$index] = $index;
            }
        }

        if (count($adds)) {
            foreach ($adds as $add) {
                $column = str_replace('-', '_', $add);
                $q      = '';
                $q .= "CREATE INDEX `{$column}_index` ";
                $q .= "ON $tableName($column) USING BTREE; ";

                $result = $this->conn->query($q);
                echo $result ? "Create index for $add on $tableName.\n" : "Failed index for $add.\n";
            }
        }
    }

    private function addUniques($tableName, $dbindeces, $ymuniques)
    {
        // ALTER TABLE `owners` ADD UNIQUE `owners_email_unique`(email(255));

        $names = [];
        foreach ($dbindeces as $dbindex) {
            $column         = str_replace('_', '-', $dbindex['Column_name']);
            $names[$column] = $column;
        }

        $adds = [];
        foreach ($ymuniques as $index) {
            if (! in_array($index, $names)) {
                $adds[$index] = $index;
            }
        }

        if (count($adds)) {
            foreach ($adds as $add) {
                $kind   = $this->yaml->tables[$tableName]['columns'][$add];
                $type   = $this->yaml->mysql['types'][$kind];
                $length = str_starts_with($type, 'text') ? '(255)' : '';

                $q = '';
                $q .= "ALTER TABLE  `$tableName` ";
                $q .= "ADD UNIQUE `{$tableName}_{$add}_unique`(`{$add}`$length); ";

                $result = $this->conn->query($q);
                echo $result ? "Create unique for $add on $tableName.\n" : "Failed unique for $add.\n";
            }
        }
    }
}

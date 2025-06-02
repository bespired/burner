<?php

trait TableIndeces
{

    public function indexTables()
    {

        $database = $this->env->mysqlDatabase;

        $conn = $this->openConnection();
        $this->useDatabase($database);

        foreach ($this->yaml->tables as $tableName => $table) {

            $query = "SHOW INDEX FROM `$tableName`";
            // $result    = $conn->query($query);
            // $dbindeces = $this->mysqlResult($result);
            $dbindeces = $this->mysqlQuery($query);

            $ymindeces = @$table['index'];
            if (gettype($ymindeces) == 'string') {
                $ymindeces = [$ymindeces];
            }

            if (count($dbindeces ?? []) > 1) {
                $this->delIndeces($tableName, $dbindeces, $ymindeces);
            }

            if (count($ymindeces ?? []) > 1) {
                $this->addIndeces($tableName, $dbindeces, $ymindeces);
            }

        }

        $this->closeConnection();

    }

    private function delIndeces($tableName, $dbindeces, $ymindeces)
    {
        // DROP INDEX index_name ON $tableName

        $drops = [];
        foreach ($dbindeces as $dbindex) {
            $column = str_replace('_', '-', $dbindex['Column_name']);

            if ($column == 'id') {
                continue;
            }

            if (! in_array($column, $ymindeces)) {
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
                $q = '';
                $q .= "CREATE INDEX `{$add}_index` ";
                $q .= "ON $tableName($add) USING BTREE; ";

                $result = $this->conn->query($q);
                echo $result ? "Create index for $add on $tableName.\n" : "Failed index for $add.\n";
            }
        }

    }

}

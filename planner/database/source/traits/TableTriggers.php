<?php

trait TableTriggers
{

    public function createTriggers()
    {

        $indeces = [];
        $deletes = [];

        foreach ($this->yaml->tables as $tableName => $table) {

            if (isset($table['index'])) {
                $indeces[$tableName] = $table['index'];
            }
            if (isset($table['trigger']) && isset($table['trigger']['deleted'])) {
                $deletes[$tableName] = $table['trigger']['deleted'];
            }
        }

        if (! count($deletes)) {
            // todo...
            // if DB has delete triggers... remove them...
            return;
        }

        $tiggers = [];
        foreach ($deletes as $table => $delete) {
            // if (gettype($delete) == 'string') {
            //     $delete = [$delete];
            // }
            // foreach ($delete as $deleter) {
            //     list($foreign, $column)    = explode('--', $deleter);
            //     $tiggers[$foreign][$table] = $column;
            // }
            list($foreign, $column)    = explode('--', $delete);
            $tiggers[$foreign][$table] = $column;
        }

        $database = $this->env->mysqlDatabase;

        $conn = $this->openConnection();
        $this->useDatabase($database);

        foreach ($tiggers as $tableName => $tableIds) {

            $main = $indeces[$tableName];

            $q = $this->dropTrigger($tableName);
            $r = $this->mysqlQuery($q);

            $q = $this->deleteRelations($tableName, $main, $tableIds);
            $r = $this->mysqlQuery($q);

        }

        $this->closeConnection();
    }

    // DELIMITER //

    // DROP TRIGGER IF EXISTS delete_owners_relations //

    // CREATE TRIGGER delete_owners_relations
    // AFTER DELETE ON owners
    // FOR EACH ROW
    // BEGIN
    //     DELETE FROM `hashes`        WHERE `owner` = `OLD`.`handle`;
    //     DELETE FROM `holidays`      WHERE `owner` = `OLD`.`handle`;
    //     DELETE FROM `country-pivot` WHERE `owner` = `OLD`.`handle`;
    // END//

    // DELIMITER ;

    public function dropTrigger($tableName)
    {
        return "DROP TRIGGER IF EXISTS delete_{$tableName}_relations";
    }

    public function deleteRelations($tableName, $main, $tableIds)
    {

        $q = [];

        $q[] = "CREATE TRIGGER delete_{$tableName}_relations";
        $q[] = "AFTER DELETE ON {$tableName}";
        $q[] = "FOR EACH ROW";
        $q[] = "BEGIN";
        foreach ($tableIds as $table => $id) {
            $column = str_replace('-', '_', $id);
            $q[]    = "DELETE FROM `$table` WHERE `$column` = `OLD`.`$main`;";
        }
        $q[] = "END";

        return join("\n", $q);

    }

}

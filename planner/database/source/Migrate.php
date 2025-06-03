<?php

include_once 'traits/DbConnection.php';
include_once 'traits/DatabaseCreate.php';
include_once 'traits/TableCreates.php';
include_once 'traits/TableIndeces.php';
include_once 'traits/TableTriggers.php';
include_once 'traits/MigrateFileRead.php';

class Migrate
{
    use DbConnection;
    use DatabaseCreate;
    use TableCreates;
    use TableIndeces;
    use TableTriggers;
    use MigrateFileRead;

    private $conn; // for use in DbConnection
    private $env;  // for use in DotFile
    private $yaml; // for use in MigrateFileRead

    public function __construct($env)
    {
        $this->env = $env;
    }
}

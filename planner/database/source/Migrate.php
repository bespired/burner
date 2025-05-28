<?php

include_once "traits/DbConnection.php";
include_once "traits/DatabaseCreate.php";
include_once "traits/TabelsCreate.php";
include_once "traits/TabelsIndex.php";
include_once "traits/MigrateFileRead.php";

class Migrate
{

    private $conn; // for use in DbConnection
    private $env;  // for use in DotFile
    private $yaml; // for use in MigrateFileRead

    use DbConnection;
    use DatabaseCreate;
    use TabelsCreate;
    use TabelsIndex;
    use MigrateFileRead;

    public function __construct($env)
    {
        $this->env = $env;
    }

}

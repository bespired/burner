<?php

include_once 'traits/DbConnection.php';
include_once 'traits/SeedFileRead.php';
include_once "traits/DatabaseSeed.php";

class Seed
{
    use DbConnection;
    use SeedFileRead;
    use DatabaseSeed;

    private $conn; // for use in DbConnection
    private $env;  // for use in DotFile
    private $yaml; // for use in SeedFileRead

    public function __construct($env)
    {
        $this->env = $env;
    }
}

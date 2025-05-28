<?php

include './source/Migrate.php';
include './source/Seed.php';
include './source/DotFile.php';

$env = Dotfile::handle();

$migrate = new Migrate($env);

$migrate->readMigrateFile();
$migrate->createDatabase();

$migrate->defineTables();
$migrate->createTables();
$migrate->alterTables();

$migrate->indexTables();

// todo:
// $migrate->foreignTables();
// $migrate->swapColumns();

$seed = new Seed($env);
$seed->readSeedFile();
$seed->seedDatabase();

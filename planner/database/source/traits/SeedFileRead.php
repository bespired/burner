<?php

trait SeedFileRead
{
    public function readSeedFile()
    {
        $filename = 'seeds.yaml';
        if (! file_exists($filename)) {
            exit("Seed failed: cannot find $filename \n");
        }

        $yaml   = file_get_contents($filename);
        $parsed = (object) @yaml_parse($yaml);

        if (property_exists($parsed, 'scalar')) {
            exit("Parsing of $filename failed.\n");
        }

        if (! property_exists($parsed, 'seeds')) {
            exit("$filename failed, it should have seeds.\n");
        }

        $this->yaml = $parsed;
    }
}

<?php

trait MigrateFileRead
{
    public function readMigrateFile()
    {
        $filename = 'migrations.yaml';
        if (!file_exists($filename)) {
            exit("Migrate failed: cannot find $filename \n");
        }

        $yaml   = file_get_contents($filename);
        $mfiles = (object) @yaml_parse($yaml);

        if (property_exists($mfiles, 'scalar')) {
            exit("Parsing of $filename failed.\n");
        }

        if (!property_exists($mfiles, 'migrations')) {
            exit("$filename failed, it should have migrations.\n");
        }

        if (!isset($mfiles->migrations['config'])) {
            exit("$filename failed, it should have config files.\n");
        }

        if (!isset($mfiles->migrations['tables'])) {
            exit("$filename failed, it should have table files.\n");
        }

        $config = $this->readConfigFiles($mfiles->migrations['config']);
        $tables = $this->readTableFiles($mfiles->migrations['tables']);

        $parsed = (object) @yaml_parse($config . $tables);

        if (!property_exists($parsed, 'tables')) {
            exit("$filename failed, it should have tables.\n");
        }

        if (!property_exists($parsed, 'mysql')) {
            exit("$filename failed, it should have type mapping.\n");
        }

        if (!$parsed->mysql['types']) {
            exit("$filename failed, it should have type mapping.\n");
        }

        $missingEnums = [];
        $missingTypes = [];
        foreach ($parsed->tables as $table) {
            $columns = @$table['columns'];
            foreach ($columns ?? [] as $column) {
                $type = explode('(', $column)[0];
                if ($type === 'enum') {
                    $enum = rtrim(explode('(', $column)[1], ')');
                    if (!isset($parsed->enums[$enum])) {
                        $missingEnums[$enum] = $enum;
                    }
                }

                if (!isset($parsed->mysql['types'][$type])) {
                    $missingTypes[$type] = $type;
                }
            }
        }
        if (count($missingEnums)) {
            echo "Some enums are not defined:\n";
            foreach ($missingEnums as $missing) {
                echo "  $missing\n";
            }
        }

        if (count($missingTypes)) {
            echo "Some types are not defined:\n";
            foreach ($missingTypes as $missing) {
                echo "  $missing\n";
            }
        }

        if (count($missingEnums) || count($missingTypes)) {
            echo "Add the missing items to the file.\n";
        }

        $this->yaml = $parsed;
    }

    private function readConfigFiles($files)
    {
        $combined = '';
        foreach ($files as $filename) {
            $path = __DIR__ . '/../../migrations/' . $filename;
            if (!file_exists($path)) {
                exit("Migrate failed: cannot find $path  \n");
            }
            $combined .= file_get_contents($path) . "\n";
        }

        return $combined;
    }

    private function readTableFiles($files)
    {
        $combined = 'tables:';
        foreach ($files as $filename) {
            $path = __DIR__ . '/../../migrations/' . $filename;
            if (!file_exists($path)) {
                exit("Migrate failed: cannot find $path  \n");
            }
            $table = @file_get_contents($path);
            $table = str_replace("\n", "\n    ", "\n" . $table) . "\n";
            $combined .= $table;
        }

        return $combined;
    }
}

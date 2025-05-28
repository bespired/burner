<?php

class DotFile
{

    public static function handle()
    {

        $locations = [
            __DIR__ . '/../../docker.env',
            __DIR__ . '/../docker.env',
            __DIR__ . '/docker.env',
        ];

        $file = null;
        foreach ($locations as $location) {
            if (file_exists($location)) {
                $file = file_get_contents($location);
            }
        }

        if (! $file) {
            echo "could not find docker.env file \n";
            exit;
        }

        $env = (object) [];

        foreach (explode("\n", $file) as $row) {

            $line = trim($row);
            if (strlen($line) && ! str_starts_with($line, '#')) {

                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $first = strtolower($parts[0]);
                    $name  = lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $first))));

                    $value = trim($parts[1]);
                    if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                        $value = rtrim(ltrim($value, '"'), '"');
                    }
                    $env->$name = $value;
                }
            }
        }

        if (isset($_SERVER['TERM_PROGRAM'])) {
            $env->mysqlHost = '127.0.0.1';
            $env->redisHost = '127.0.0.1';
        }

        return $env;

    }

}

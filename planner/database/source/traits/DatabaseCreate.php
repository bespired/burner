<?php

trait DatabaseCreate
{

    public function createDatabase()
    {
        $database = $this->env->mysqlDatabase;

        $conn = $this->openConnection();

        // Check database
        $query     = 'SHOW DATABASES LIKE "' . $database . '"';
        $resExists = $conn->query($query);

        if ($resExists->num_rows == 1) {
            echo "Database already exists.\n";
        } else {
            // Create database
            $sql = "CREATE DATABASE $database";
            if ($conn->query($sql) === true) {
                echo "Database created successfully.\n";
            }
        }

        $this->closeConnection();

    }

}

<?php

trait DbConnection
{

    public function openConnection()
    {
        $servername = $this->env->mysqlHost;
        $serverport = $this->env->mysqlPort;
        $username   = $this->env->mysqlRootUser;
        $password   = $this->env->mysqlRootPassword;
        $database   = $this->env->mysqlDatabase;

        $host = $servername . ($serverport ? ':' . $serverport : '');

        // Create connection
        try {
            $conn = new \mysqli($host, $username, $password);
        } catch (\Exception $e) {
            exit('Connection failed: ' . $e->getMessage() . "\n");
        }

        // Check connection
        if ($conn->connect_error) {
            exit('Connection failed: ' . $conn->connect_error . "\n");
        }

        $this->conn = $conn;

        return $conn;
    }

    public function closeConnection()
    {
        $this->conn->close();
    }

    public function useDatabase($database)
    {
        $this->conn->query('use ' . $database);
    }

    public function mysqlResult($result)
    {
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function mysqlCount($q)
    {
        $this->mysqlClear();
        $result = $this->conn->query($q);
        $values = $result->fetch_all(MYSQLI_ASSOC);
        $value  = array_values($values[0]);
        return intval($value[0]);
    }

    public function mysqlClear()
    {
        do {
            if ($res = $this->conn->store_result()) {
                $this->conn->free();
            }
        } while ($this->conn->more_results() && $this->conn->next_result());
    }

    public function mysqlQuery($q)
    {
        $this->mysqlClear();

        $commit = strripos($q, 'commit') > 0;

        if ($commit) {
            $result = $this->conn->multi_query($q);
        } else {
            $result = $this->conn->query($q);
        }

        if (gettype($result) == "boolean") {
            return $result;
        }
        if (gettype($result) == "object") {
            return $result->fetch_all(MYSQLI_ASSOC);
        }

        return $result;
    }

    public function createTableQuery($tableName, $columns)
    {
        $fullcolumns   = [];
        $fullcolumns[] = "`id` BIGINT PRIMARY KEY AUTO_INCREMENT";
        foreach ($columns as $name => $type) {
            $fullcolumns[] = "`$name` " . strtoupper($type);
        }

        $q = '';
        $q .= "CREATE TABLE `$tableName` ( \n";
        $q .= join(",\n", $fullcolumns);
        $q .= "\n);\n";

        return $q;
    }

    public function createListQuery($tableName, $column, $handles)
    {
        foreach ($handles as $handle) {
            $casts[] = $this->cast($handle);
        }

        $q = '';
        $q .= "SELECT * FROM `$tableName` ";
        $q .= "WHERE `$column` \n";
        $q .= "IN (" . join(",", $casts) . ");";

        return $q;
    }

    public function cast($val)
    {

        if (is_null($val)) {
            return 'NULL';
        }

        if (is_bool($val)) {
            return $val ? 1 : 0;
        }

        if (is_numeric($val)) {
            return $val;
        }

        if (is_array($val)) {
            return '"' . json_encode($val) . '"';
        }

        return '"' . $val . '"';
    }

    // CREATE TABLE Persons (
    //     PersonID int,
    //     LastName varchar(255),
    //     FirstName varchar(255),
    //     Address varchar(255),
    //     City varchar(255)
    // );

}

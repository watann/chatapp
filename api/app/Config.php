<?php
class Config{
    private $hostname;
    private $username;
    private $password;
    private $dbname;
    private static $connection = null;

    public function __construct(){
        $host = getenv('CHATAPP_DB_HOST');
        $user = getenv('CHATAPP_DB_USER');
        $pass = getenv('CHATAPP_DB_PASS');
        $name = getenv('CHATAPP_DB_NAME');
        $this->hostname = $host !== false && $host !== '' ? $host : 'localhost';
        $this->username = $user !== false && $user !== '' ? $user : 'root';
        $this->password = $pass !== false ? $pass : '';
        $this->dbname = $name !== false && $name !== '' ? $name : 'chatapp';
    }

    public function connect(){
        if(self::$connection instanceof mysqli){
            return self::$connection;
        }

        self::$connection = new mysqli($this->hostname, $this->username, $this->password, $this->dbname);
        if(self::$connection->connect_error){
            error_log('Database connection failed: ' . self::$connection->connect_error);
            http_response_code(503);
            die('Database service is temporarily unavailable.');
        }
        self::$connection->set_charset("utf8mb4");
        return self::$connection;
    }
}

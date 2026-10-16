<?php
// config/database.php
class Database {
    private $host = "localhost";
    private $db_name = "project_management";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                                  $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            // Don't echo here - let the calling file handle it
            throw new Exception("Connection error: " . $exception->getMessage());
        }
        return $this->conn;
    }
}

// Enable error reporting for development
error_reporting(0); // Turn off HTML error display
ini_set('display_errors', 0);
?>
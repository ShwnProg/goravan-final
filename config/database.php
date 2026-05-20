<?php
class Database
{
    private $host = "localhost";
    private $user = "root";
    private $pass = "shawnmarlogaldo@1122";
    private $dbname = "goravan_project_db";

    public $conn = null;
    public function GetConnection()
    {
        try {
            $this->conn = new PDO("mysql:
                                   host=$this->host;
                                   dbname=$this->dbname;",
                                   $this->user,
                                   $this->pass
                                );

            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("SET time_zone = '+08:00'");
        } catch (PDOException $e) {
            die("Connection Error: " . $e->getMessage());
        }

        return $this->conn;
    }
}
?>

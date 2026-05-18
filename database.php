<?php
class Database
{
    private $host = "it208.2025.ccsit.info";
    private $user = "root";
    private $pass = "67TRJCDQ1+Rm169B";
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
        } catch (PDOException $e) {
            die("Connection Error: " . $e->getMessage());
        }

        return $this->conn;
    }
}
?>
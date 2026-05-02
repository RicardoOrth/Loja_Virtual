<?php
class Database {
    private $host = "localhost";
    private $port = "5432";
    private $db_name = "trabalho_1";
    private $username = "postgres";
    private $password = "lfvb3112";
    public $conn; 

    public function getConnection() {
        $this->conn = null;
        try {
            // Driver pgsql para PostgreSQL
            $this->conn = new PDO("pgsql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            echo "Erro na conexão: " . $e->getMessage();
        }
        return $this->conn;
    }
}
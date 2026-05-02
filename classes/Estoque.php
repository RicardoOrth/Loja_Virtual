<?php
class Estoque {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function listar() {
        return $this->conn->query("SELECT * FROM estoque")->fetchAll();
    }

    public function inserir($produto_id, $quantidade, $preco) {
        $sql = "INSERT INTO estoque (produto_id, quantidade, preco)
                VALUES (:produto_id, :quantidade, :preco)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute(compact("produto_id","quantidade","preco"));
    }
}
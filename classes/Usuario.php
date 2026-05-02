<?php
class Usuario {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($email, $senha) {
        $sql = "SELECT * FROM usuario WHERE email = :email AND senha = :senha";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ":email" => $email,
            ":senha" => $senha
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function listar() {
        return $this->conn->query("SELECT * FROM usuario")->fetchAll();
    }

    public function inserir($email, $senha) {
    $sql = "INSERT INTO usuario (email, senha, tipo)
            VALUES (:email, :senha, 2)
            RETURNING usuario_id";

    $stmt = $this->conn->prepare($sql);
    $stmt->execute([
        ':email' => $email,
        ':senha' => $senha
    ]);

    return $stmt->fetchColumn();
}
}
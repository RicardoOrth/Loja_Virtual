<?php

class ClienteDAO {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /** Insere um Cliente vinculado a um usuário e endereço já existentes. */
    public function inserir(Cliente $cliente) {
        $sql = "INSERT INTO CLIENTE (USUARIO_ID, ENDERECO_ID, NOME, TELEFONE, CARTAO_CREDITO)
                VALUES (:uid, :eid, :nome, :tel, :cartao)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':uid'    => $cliente->usuario_id,
            ':eid'    => $cliente->endereco_id,
            ':nome'   => $cliente->nome,
            ':tel'    => $cliente->telefone,
            ':cartao' => $cliente->cartao_credito
        ]);
    }

    /**
     * Consulta clientes com filtro opcional por nome (ILIKE) ou código (ID).
     * Faz join com USUARIO e ENDERECO. Termo vazio retorna todos.
     */
    public function consultar($termo = "") {
        $sql = "SELECT c.*, u.email, e.cidade
                FROM CLIENTE c
                JOIN USUARIO  u ON c.usuario_id  = u.usuario_id
                JOIN ENDERECO e ON c.endereco_id = e.endereco_id";
        if ($termo !== "") {
            $sql .= " WHERE c.nome ILIKE ? OR CAST(c.cliente_id AS TEXT) = ?
                      ORDER BY c.nome ASC";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(["%$termo%", $termo]);
        } else {
            $sql .= " ORDER BY c.nome ASC";
            $stmt = $this->conn->query($sql);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Retorna cliente + endereço + email (usado na edição). */
    public function buscarCompletoPorId($cliente_id) {
        $sql = "SELECT c.*, e.*, u.email, u.senha, u.usuario_id
                FROM CLIENTE c
                JOIN USUARIO  u ON c.usuario_id  = u.usuario_id
                JOIN ENDERECO e ON c.endereco_id = e.endereco_id
                WHERE c.cliente_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$cliente_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** Atualiza os dados próprios do cliente (nome, telefone, cartão). */
    public function atualizar(Cliente $cliente) {
        $sql = "UPDATE CLIENTE SET NOME = ?, TELEFONE = ?, CARTAO_CREDITO = ?
                WHERE CLIENTE_ID = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            $cliente->nome, $cliente->telefone, $cliente->cartao_credito, $cliente->cliente_id
        ]);
    }

    /** Retorna o cliente vinculado a um usuário (usado na finalização do pedido - US05). */
    public function buscarPorUsuario($usuario_id) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM CLIENTE WHERE USUARIO_ID = ? LIMIT 1"
        );
        $stmt->execute([$usuario_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** Retorna os IDs de usuário e endereço vinculados ao cliente. */
    public function buscarVinculos($cliente_id) {
        $stmt = $this->conn->prepare(
            "SELECT usuario_id, endereco_id FROM CLIENTE WHERE cliente_id = ?"
        );
        $stmt->execute([$cliente_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function excluir($cliente_id) {
        $stmt = $this->conn->prepare("DELETE FROM CLIENTE WHERE CLIENTE_ID = ?");
        return $stmt->execute([$cliente_id]);
    }
}

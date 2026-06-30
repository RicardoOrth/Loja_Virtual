<?php

class FornecedorDAO {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /** Insere um Fornecedor vinculado a um usuário e endereço já existentes. */
    public function inserir(Fornecedor $fornecedor) {
        $sql = "INSERT INTO FORNECEDOR (USUARIO_ID, ENDERECO_ID, NOME, DESCRICAO, TELEFONE)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            $fornecedor->usuario_id, $fornecedor->endereco_id,
            $fornecedor->nome, $fornecedor->descricao, $fornecedor->telefone
        ]);
    }

    public function atualizar(Fornecedor $fornecedor) {
        $sql = "UPDATE FORNECEDOR SET NOME = ?, TELEFONE = ?, DESCRICAO = ? WHERE FORNECEDOR_ID = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            $fornecedor->nome, $fornecedor->telefone, $fornecedor->descricao, $fornecedor->fornecedor_id
        ]);
    }

    public function excluir($fornecedor_id) {
        $stmt = $this->conn->prepare("DELETE FROM FORNECEDOR WHERE FORNECEDOR_ID = ?");
        return $stmt->execute([$fornecedor_id]);
    }

    /** Lista fornecedores com cidade e email (join com ENDERECO e USUARIO). */
    public function listarTudo() {
        return $this->consultar("");
    }

    /**
     * Consulta fornecedores com filtro opcional por nome (ILIKE) ou código (ID).
     * Termo vazio retorna todos.
     */
    public function consultar($termo = "") {
        $sql = "SELECT f.*, e.cidade, u.email
                FROM FORNECEDOR f
                JOIN ENDERECO e ON f.endereco_id = e.endereco_id
                JOIN USUARIO  u ON f.usuario_id  = u.usuario_id";
        if ($termo !== "") {
            $sql .= " WHERE f.nome ILIKE ? OR CAST(f.fornecedor_id AS TEXT) = ?
                      ORDER BY f.nome ASC";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(["%$termo%", $termo]);
        } else {
            $sql .= " ORDER BY f.nome ASC";
            $stmt = $this->conn->query($sql);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Lista resumida (id + nome) para popular combos de seleção. */
    public function listarParaSelect() {
        $sql = "SELECT fornecedor_id, nome FROM FORNECEDOR ORDER BY nome ASC";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorUsuarioId($usuario_id) {
        $stmt = $this->conn->prepare(
            "SELECT fornecedor_id, nome FROM FORNECEDOR WHERE usuario_id = ?"
        );
        $stmt->execute([$usuario_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** Retorna fornecedor + endereço + usuário (usado na edição). */
    public function buscarCompletoPorId($fornecedor_id) {
        $sql = "SELECT f.*, e.*, u.email, u.senha
                FROM FORNECEDOR f
                JOIN ENDERECO e ON f.endereco_id = e.endereco_id
                JOIN USUARIO  u ON f.usuario_id  = u.usuario_id
                WHERE f.fornecedor_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$fornecedor_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** Retorna os IDs de usuário e endereço vinculados ao fornecedor. */
    public function buscarVinculos($fornecedor_id) {
        $stmt = $this->conn->prepare(
            "SELECT usuario_id, endereco_id FROM FORNECEDOR WHERE fornecedor_id = ?"
        );
        $stmt->execute([$fornecedor_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** ADICIONADO: Consulta os fornecedores de forma limitada e deslocada para a paginação */
    public function consultarPaginado(string $busca = "", int $pagina = 1, int $limite = 8): array {
        $offset = ($pagina - 1) * $limite;
        
        $sql = "SELECT f.*, e.cidade, u.email
                FROM FORNECEDOR f
                JOIN ENDERECO e ON f.endereco_id = e.endereco_id
                JOIN USUARIO  u ON f.usuario_id  = u.usuario_id";
        $params = [];

        if ($busca !== "") {
            $sql .= " WHERE f.nome ILIKE ? OR CAST(f.fornecedor_id AS TEXT) = ?";
            $params = ["%$busca%", $busca];
        }

        $sql .= " ORDER BY f.fornecedor_id DESC LIMIT ? OFFSET ?";
        
        $stmt = $this->conn->prepare($sql);
        
        $idx = 1;
        foreach ($params as $p) {
            $stmt->bindValue($idx++, $p);
        }
        
        $stmt->bindValue($idx++, $limite, PDO::PARAM_INT);
        $stmt->bindValue($idx++, $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** ADICIONADO: Conta o total absoluto de fornecedores encontrados no filtro */
    public function contarTotal(string $busca = ""): int {
        $sql = "SELECT COUNT(f.fornecedor_id) 
                FROM FORNECEDOR f
                JOIN ENDERECO e ON f.endereco_id = e.endereco_id
                JOIN USUARIO  u ON f.usuario_id  = u.usuario_id";
        $params = [];

        if ($busca !== "") {
            $sql .= " WHERE f.nome ILIKE ? OR CAST(f.fornecedor_id AS TEXT) = ?";
            $params = ["%$busca%", $busca];
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}

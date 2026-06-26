<?php

class ProdutoDAO {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /** Insere um Produto e retorna o ID gerado. */
    public function inserir(Produto $produto) {
        $sql = "INSERT INTO PRODUTO (NOME, DESCRICAO, FORNECEDOR_ID)
                VALUES (?, ?, ?) RETURNING PRODUTO_ID";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$produto->nome, $produto->descricao, $produto->fornecedor_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new Exception("Falha ao obter ID do Produto.");
        return $row['produto_id'] ?? $row['PRODUTO_ID'];
    }

    public function atualizar(Produto $produto) {
        $sql = "UPDATE PRODUTO SET NOME = ?, DESCRICAO = ? WHERE PRODUTO_ID = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$produto->nome, $produto->descricao, $produto->produto_id]);
    }

    public function excluir($produto_id) {
        $stmt = $this->conn->prepare("DELETE FROM PRODUTO WHERE PRODUTO_ID = ?");
        return $stmt->execute([$produto_id]);
    }

    /** Consulta produtos com fornecedor e estoque (join). Filtro opcional por nome/código. */
    public function consultar($termo = "") {
        $sql = "SELECT p.*, f.nome AS fornecedor_nome, e.quantidade, e.preco
                FROM PRODUTO p
                JOIN FORNECEDOR f ON p.fornecedor_id = f.fornecedor_id
                JOIN ESTOQUE   e ON p.produto_id    = e.produto_id";
        if ($termo !== "") {
            $sql .= " WHERE p.nome ILIKE ? OR CAST(p.produto_id AS TEXT) = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(["%$termo%", $termo]);
        } else {
            $stmt = $this->conn->query($sql);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Retorna produto + estoque + nome do fornecedor por ID (edição e carrinho). */
    public function buscarComEstoquePorId($produto_id) {
        $sql = "SELECT p.*, f.nome AS fornecedor_nome, e.quantidade, e.preco
                FROM PRODUTO p
                JOIN ESTOQUE    e ON p.produto_id    = e.produto_id
                JOIN FORNECEDOR f ON p.fornecedor_id = f.fornecedor_id
                WHERE p.produto_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$produto_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

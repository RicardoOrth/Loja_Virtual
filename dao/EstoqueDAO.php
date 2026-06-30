<?php

class EstoqueDAO {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function inserir(Estoque $estoque) {
        $sql = "INSERT INTO ESTOQUE (PRODUTO_ID, QUANTIDADE, PRECO) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$estoque->produto_id, $estoque->quantidade, $estoque->preco]);
    }

    /** Atualiza quantidade e preço de um produto. */
    public function atualizarPorProduto($produto_id, $quantidade, $preco) {
        $sql = "UPDATE ESTOQUE SET QUANTIDADE = ?, PRECO = ? WHERE PRODUTO_ID = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$quantidade, $preco, $produto_id]);
    }

    /**
     * Baixa a quantidade em estoque de um produto.
     * Só efetua se houver saldo suficiente; retorna true se a baixa ocorreu.
     */
    public function baixarEstoque($produto_id, $quantidade) {
        $sql = "UPDATE ESTOQUE SET QUANTIDADE = QUANTIDADE - ?
                WHERE PRODUTO_ID = ? AND QUANTIDADE >= ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$quantidade, $produto_id, $quantidade]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Repõe (devolve) quantidade ao estoque de um produto.
     * Usado ao cancelar um pedido (US06), para os itens voltarem ao estoque.
     */
    public function reporEstoque($produto_id, $quantidade) {
        $sql = "UPDATE ESTOQUE SET QUANTIDADE = QUANTIDADE + ? WHERE PRODUTO_ID = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$quantidade, $produto_id]);
    }

    public function excluirPorProduto($produto_id) {
        $stmt = $this->conn->prepare("DELETE FROM ESTOQUE WHERE PRODUTO_ID = ?");
        return $stmt->execute([$produto_id]);
    }

    public function listar() {
        return $this->conn->query("SELECT * FROM ESTOQUE")->fetchAll(PDO::FETCH_ASSOC);
    }
}

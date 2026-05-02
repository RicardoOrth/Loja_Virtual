<?php
class Produto {
    private $conn;

    public function __construct($db) { $this->conn = $db; }

    public function cadastrar($nome, $desc, $forn_id, $qtd, $preco) {
    try {
        $this->conn->beginTransaction();
        
        // 1. Inserir na tabela PRODUTO (SEM a coluna FOTO por enquanto, conforme solicitado)
        $sql = "INSERT INTO PRODUTO (NOME, DESCRICAO, FORNECEDOR_ID) VALUES (?, ?, ?) RETURNING PRODUTO_ID";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$nome, $desc, $forn_id]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) throw new Exception("Falha ao obter ID do Produto.");
        $p_id = $result['produto_id'];

        // 2. Inserir na tabela ESTOQUE (Obrigatório para aparecer na vitrine)
        $sqlE = "INSERT INTO ESTOQUE (PRODUTO_ID, QUANTIDADE, PRECO) VALUES (?, ?, ?)";
        $stmtE = $this->conn->prepare($sqlE);
        $stmtE->execute([$p_id, $qtd, $preco]);

        $this->conn->commit();
        return true;
    } catch (Exception $e) {
        $this->conn->rollBack();
        // ESTA LINHA VAI MOSTRAR O ERRO NA TELA:
        die("ERRO AO CRIAR PRODUTO: " . $e->getMessage()); 
    }
}

    public function consultar($termo = "") {
        $sql = "SELECT p.*, f.nome as fornecedor_nome, e.quantidade, e.preco 
                FROM PRODUTO p 
                JOIN FORNECEDOR f ON p.fornecedor_id = f.fornecedor_id
                JOIN ESTOQUE e ON p.produto_id = e.produto_id";
        
        if ($termo != "") {
            // ILIKE é específico do Postgres para busca case-insensitive
            $sql .= " WHERE p.nome ILIKE ? OR CAST(p.produto_id AS TEXT) = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(["%$termo%", $termo]);
        } else {
            $stmt = $this->conn->query($sql);
        }
        return $stmt;
    }
}
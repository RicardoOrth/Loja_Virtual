<?php

class ProdutoDAO
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /** Insere um Produto e sua lista de imagens, retornando o ID gerado. */
    public function inserir(Produto $produto, array $listaImagens = [])
    {
        // 1. Insere o produto
        $sql = "INSERT INTO PRODUTO (NOME, DESCRICAO, FORNECEDOR_ID)
                VALUES (?, ?, ?) RETURNING PRODUTO_ID";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$produto->nome, $produto->descricao, $produto->fornecedor_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) throw new Exception("Falha ao obter ID do Produto.");

        $produtoId = $row['produto_id'] ?? $row['PRODUTO_ID'];

        // 2. Percorre a lista de imagens salvas e insere todas no banco de dados
        if (!empty($listaImagens)) {
            $sqlImg = "INSERT INTO PRODUTO_IMAGEM (PRODUTO_ID, CAMINHO) VALUES (?, ?)";
            $stmtImg = $this->conn->prepare($sqlImg);

            foreach ($listaImagens as $img) {
                // Passa o ID gerado do produto e o caminho individual da foto
                $stmtImg->execute([$produtoId, $img->caminho]);
            }
        }

        return $produtoId;
    }

    public function atualizar(Produto $produto)
    {
        $sql = "UPDATE PRODUTO SET NOME = ?, DESCRICAO = ? WHERE PRODUTO_ID = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$produto->nome, $produto->descricao, $produto->produto_id]);
    }

    public function excluir($produto_id)
    {
        $stmt = $this->conn->prepare("DELETE FROM PRODUTO WHERE PRODUTO_ID = ?");
        return $stmt->execute([$produto_id]);
    }

    /** Consulta produtos com fornecedor, estoque e a primeira imagem cadastrada. Filtro opcional por nome/código. */
    public function consultar($termo = "")
    {
        // Usamos DISTINCT ON no PostgreSQL para garantir que retorne estritamente 1 imagem por produto (a primeira criada)
        $sql = "SELECT DISTINCT ON (p.produto_id) 
                       p.*, 
                       f.nome AS fornecedor_nome, 
                       e.quantidade, 
                       e.preco, 
                       pi.caminho AS imagem_caminho
                FROM PRODUTO p
                JOIN FORNECEDOR f ON p.fornecedor_id = f.fornecedor_id
                JOIN ESTOQUE   e ON p.produto_id    = e.produto_id
                LEFT JOIN PRODUTO_IMAGEM pi ON p.produto_id = pi.produto_id";

        if ($termo !== "") {
            $sql .= " WHERE p.nome ILIKE ? OR CAST(p.produto_id AS TEXT) = ?";
            // Ordenamos por produto_id (obrigatório pelo DISTINCT ON) e depois pelo ID da imagem para pegar a primeira
            $sql .= " ORDER BY p.produto_id ASC, pi.produto_imagem_id ASC";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(["%$termo%", $termo]);
        } else {
            $sql .= " ORDER BY p.produto_id ASC, pi.produto_imagem_id ASC";
            $stmt = $this->conn->query($sql);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Retorna produto + estoque + nome do fornecedor por ID (edição e carrinho). */
    public function buscarComEstoquePorId($produto_id)
    {
        // Query limpa e sem DISTINCT ON para evitar conflitos em buscas por ID único
        $sql = "SELECT p.*, f.nome AS fornecedor_nome, e.quantidade, e.preco, pi.caminho AS imagem_caminho
                FROM PRODUTO p
                JOIN ESTOQUE    e ON p.produto_id    = e.produto_id
                JOIN FORNECEDOR f ON p.fornecedor_id = f.fornecedor_id
                LEFT JOIN PRODUTO_IMAGEM pi ON p.produto_id = pi.produto_id
                WHERE p.produto_id = ?
                LIMIT 1"; // Garante apenas um resultado sem forçar ordenações complexas
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$produto_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** Busca todas as imagens associadas a um produto específico */
    public function buscarImagensPorProdutoId($produto_id)
    {
        $sql = "SELECT * FROM PRODUTO_IMAGEM WHERE PRODUTO_ID = ? ORDER BY PRODUTO_IMAGEM_ID ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$produto_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Exclui uma imagem específica pelo ID dela */
    public function excluirImagem($produto_imagem_id)
    {
        $sql = "DELETE FROM PRODUTO_IMAGEM WHERE PRODUTO_IMAGEM_ID = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$produto_imagem_id]);
    }

    /** Insere uma única imagem vinculada ao produto */
    public function inserirImagem($produto_id, $caminho)
    {
        $sql = "INSERT INTO PRODUTO_IMAGEM (PRODUTO_ID, CAMINHO) VALUES (?, ?)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$produto_id, $caminho]);
    }

    /** ADICIONADO: Consulta os produtos paginados limitando estritamente em lotes de 8 */
    public function consultarPaginado(string $busca = "", int $pagina = 1, int $limite = 8): array
    {
        $offset = ($pagina - 1) * $limite;

        // Mantém a estrutura idêntica com DISTINCT ON do PostgreSQL
        $sql = "SELECT DISTINCT ON (p.produto_id) 
                       p.*, 
                       f.nome AS fornecedor_nome, 
                       e.quantidade, 
                       e.preco, 
                       pi.caminho AS imagem_caminho
                FROM PRODUTO p
                JOIN FORNECEDOR f ON p.fornecedor_id = f.fornecedor_id
                JOIN ESTOQUE   e ON p.produto_id    = e.produto_id
                LEFT JOIN PRODUTO_IMAGEM pi ON p.produto_id = pi.produto_id";
        $params = [];

        if ($busca !== "") {
            $sql .= " WHERE p.nome ILIKE ? OR p.descricao ILIKE ? OR CAST(p.produto_id AS TEXT) = ?";
            $params = ["%$busca%", "%$busca%", $busca];
        }

        // Importante: No PostgreSQL usando DISTINCT ON, a primeira coluna do ORDER BY deve ser a mesma do DISTINCT
        $sql .= " ORDER BY p.produto_id DESC, pi.produto_imagem_id ASC LIMIT ? OFFSET ?";

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

    /** ADICIONADO: Conta a quantidade total absoluta para o cálculo de botões e páginas */
    public function contarTotal(string $busca = ""): int
    {
        $sql = "SELECT COUNT(p.produto_id) 
                FROM PRODUTO p
                JOIN FORNECEDOR f ON p.fornecedor_id = f.fornecedor_id
                JOIN ESTOQUE   e ON p.produto_id    = e.produto_id";
        $params = [];

        if ($busca !== "") {
            $sql .= " WHERE p.nome ILIKE ? OR p.descricao ILIKE ? OR CAST(p.produto_id AS TEXT) = ?";
            $params = ["%$busca%", "%$busca%", $busca];
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}

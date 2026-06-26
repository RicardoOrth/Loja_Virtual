<?php

class PedidoDAO{
    private PDO $conn;

    public function __construct(PDO $conn){
        $this->conn = $conn;
    }

    public function consultarPedidos(
        ?int $id = null,
        ?int $numero = null,
        ?string $cliente = null,
        int $pagina = 1,
        int $limite = 10
    ): array {
        $offset = ($pagina - 1) * $limite;

        $sql = "
            SELECT
                p.PEDIDO_ID,
                p.PEDIDO_NUMERO,
                p.DATA_PEDIDO,
                p.DATA_ENTREGA,
                p.DATA_CANCELAMENTO,
                c.NOME AS CLIENTE_NOME,
                ps.DESCRICAO AS SITUACAO,
                COALESCE(SUM(ip.QUANTIDADE * ip.PRECO), 0) AS VALOR_TOTAL
            FROM PEDIDO p
            JOIN CLIENTE c
                ON c.CLIENTE_ID = p.CLIENTE_ID
            JOIN PEDIDO_SITUACAO ps
                ON ps.PEDIDO_SITUACAO_ID = p.SITUACAO_ID
            LEFT JOIN ITEM_PEDIDO ip
                ON ip.PEDIDO_ID = p.PEDIDO_ID
            WHERE 1 = 1
        ";

        $params = [];

        if ($id !== null) {
            $sql .= " AND p.PEDIDO_ID = :id";
            $params[':id'] = $id;
        }

        if ($numero !== null) {
            $sql .= " AND p.PEDIDO_NUMERO = :numero";
            $params[':numero'] = $numero;
        }

        if ($cliente !== null && $cliente !== '') {
            $sql .= " AND c.NOME ILIKE :cliente";
            $params[':cliente'] = '%' . $cliente . '%';
        }

        $sql .= "
            GROUP BY
                p.PEDIDO_ID,
                p.PEDIDO_NUMERO,
                p.DATA_PEDIDO,
                p.DATA_ENTREGA,
                p.DATA_CANCELAMENTO,
                c.NOME,
                ps.DESCRICAO
            ORDER BY p.DATA_PEDIDO DESC
            LIMIT :limite
            OFFSET :offset
        ";

        $stmt = $this->conn->prepare($sql);

        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value);
        }

        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function consultarItensPedido(int $pedidoId): array
    {
        $sql = "
            SELECT
                ip.ITEM_PEDIDO_ID,
                ip.PEDIDO_ID,
                ip.PRODUTO_ID,
                p.NOME AS PRODUTO_NOME,
                p.DESCRICAO AS PRODUTO_DESCRICAO,
                ip.QUANTIDADE,
                ip.PRECO AS VALOR_UNITARIO,
                (ip.QUANTIDADE * ip.PRECO) AS VALOR_TOTAL_ITEM
            FROM ITEM_PEDIDO ip
            JOIN PRODUTO p
                ON p.PRODUTO_ID = ip.PRODUTO_ID
            WHERE ip.PEDIDO_ID = :pedido_id
            ORDER BY ip.ITEM_PEDIDO_ID
        ";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            ':pedido_id' => $pedidoId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarPedidos(
        ?int $id = null,
        ?int $numero = null,
        ?string $cliente = null
    ): int {
        $sql = "
            SELECT
                COUNT(p.PEDIDO_ID) AS TOTAL
            FROM PEDIDO p
            JOIN CLIENTE c
                ON c.CLIENTE_ID = p.CLIENTE_ID
            WHERE 1 = 1
        ";

        $params = [];

        if ($id !== null) {
            $sql .= " AND p.PEDIDO_ID = :id";
            $params[':id'] = $id;
        }

        if ($numero !== null) {
            $sql .= " AND p.PEDIDO_NUMERO = :numero";
            $params[':numero'] = $numero;
        }

        if ($cliente !== null && $cliente !== '') {
            $sql .= " AND c.NOME ILIKE :cliente";
            $params[':cliente'] = '%' . $cliente . '%';
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }
}
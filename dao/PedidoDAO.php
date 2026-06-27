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
        int $limite = 10,
        ?int $usuarioClienteId = null
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

        if ($usuarioClienteId !== null) {
            $sql .= " AND c.USUARIO_ID = :usuario_cliente_id";
            $params[':usuario_cliente_id'] = $usuarioClienteId;
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

    public function consultarPedidoDetalhado(int $pedidoId, ?int $usuarioClienteId = null): ?array
    {
        $sql = "
            SELECT
                p.PEDIDO_ID,
                p.PEDIDO_NUMERO,
                p.DATA_PEDIDO,
                p.DATA_ENTREGA,
                p.DATA_CANCELAMENTO,
                c.CLIENTE_ID,
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
            WHERE p.PEDIDO_ID = :pedido_id
        ";

        $params = [
            ':pedido_id' => $pedidoId
        ];

        if ($usuarioClienteId !== null) {
            $sql .= " AND c.USUARIO_ID = :usuario_cliente_id";
            $params[':usuario_cliente_id'] = $usuarioClienteId;
        }

        $sql .= "
            GROUP BY
                p.PEDIDO_ID,
                p.PEDIDO_NUMERO,
                p.DATA_PEDIDO,
                p.DATA_ENTREGA,
                p.DATA_CANCELAMENTO,
                c.CLIENTE_ID,
                c.NOME,
                ps.DESCRICAO
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

        return $pedido ?: null;
    }

    public function consultarPrimeiroPedido(?int $usuarioClienteId = null): ?array
    {
        $sql = "
            SELECT p.PEDIDO_ID
            FROM PEDIDO p
            JOIN CLIENTE c
                ON c.CLIENTE_ID = p.CLIENTE_ID
            WHERE 1 = 1
        ";

        $params = [];

        if ($usuarioClienteId !== null) {
            $sql .= " AND c.USUARIO_ID = :usuario_cliente_id";
            $params[':usuario_cliente_id'] = $usuarioClienteId;
        }

        $sql .= " ORDER BY p.DATA_PEDIDO DESC, p.PEDIDO_ID DESC LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $pedidoId = $stmt->fetchColumn();

        if (!$pedidoId) {
            return null;
        }

        return $this->consultarPedidoDetalhado((int) $pedidoId, $usuarioClienteId);
    }

    public function consultarItensPedidoPaginado(
        int $pedidoId,
        int $pagina = 1,
        int $limite = 5,
        ?int $usuarioClienteId = null
    ): array {
        $offset = ($pagina - 1) * $limite;

        $sql = "
            SELECT
                ip.ITEM_PEDIDO_ID,
                ip.PEDIDO_ID,
                ip.PRODUTO_ID,
                p.NOME AS PRODUTO_NOME,
                p.DESCRICAO AS PRODUTO_DESCRICAO,
                CASE
                    WHEN p.FOTO IS NULL THEN NULL
                    ELSE encode(p.FOTO, 'base64')
                END AS FOTO_BASE64,
                ip.QUANTIDADE,
                ip.PRECO AS VALOR_UNITARIO,
                (ip.QUANTIDADE * ip.PRECO) AS VALOR_TOTAL_ITEM
            FROM ITEM_PEDIDO ip
            JOIN PRODUTO p
                ON p.PRODUTO_ID = ip.PRODUTO_ID
            JOIN PEDIDO pe
                ON pe.PEDIDO_ID = ip.PEDIDO_ID
            JOIN CLIENTE c
                ON c.CLIENTE_ID = pe.CLIENTE_ID
            WHERE ip.PEDIDO_ID = :pedido_id
        ";

        $params = [
            ':pedido_id' => $pedidoId
        ];

        if ($usuarioClienteId !== null) {
            $sql .= " AND c.USUARIO_ID = :usuario_cliente_id";
            $params[':usuario_cliente_id'] = $usuarioClienteId;
        }

        $sql .= "
            ORDER BY ip.ITEM_PEDIDO_ID
            LIMIT :limite
            OFFSET :offset
        ";

        $stmt = $this->conn->prepare($sql);

        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value, PDO::PARAM_INT);
        }

        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarItensPedido(int $pedidoId, ?int $usuarioClienteId = null): int
    {
        $sql = "
            SELECT COUNT(ip.ITEM_PEDIDO_ID)
            FROM ITEM_PEDIDO ip
            JOIN PEDIDO p
                ON p.PEDIDO_ID = ip.PEDIDO_ID
            JOIN CLIENTE c
                ON c.CLIENTE_ID = p.CLIENTE_ID
            WHERE ip.PEDIDO_ID = :pedido_id
        ";

        $params = [
            ':pedido_id' => $pedidoId
        ];

        if ($usuarioClienteId !== null) {
            $sql .= " AND c.USUARIO_ID = :usuario_cliente_id";
            $params[':usuario_cliente_id'] = $usuarioClienteId;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function consultarFotosPedido(int $pedidoId, ?int $usuarioClienteId = null): array
    {
        $sql = "
            SELECT
                p.PRODUTO_ID,
                p.NOME AS PRODUTO_NOME,
                CASE
                    WHEN p.FOTO IS NULL THEN NULL
                    ELSE encode(p.FOTO, 'base64')
                END AS FOTO_BASE64
            FROM ITEM_PEDIDO ip
            JOIN PRODUTO p
                ON p.PRODUTO_ID = ip.PRODUTO_ID
            JOIN PEDIDO pe
                ON pe.PEDIDO_ID = ip.PEDIDO_ID
            JOIN CLIENTE c
                ON c.CLIENTE_ID = pe.CLIENTE_ID
            WHERE ip.PEDIDO_ID = :pedido_id
        ";

        $params = [
            ':pedido_id' => $pedidoId
        ];

        if ($usuarioClienteId !== null) {
            $sql .= " AND c.USUARIO_ID = :usuario_cliente_id";
            $params[':usuario_cliente_id'] = $usuarioClienteId;
        }

        $sql .= " ORDER BY ip.ITEM_PEDIDO_ID";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

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

    /** Retorna o ID de uma situação pela descrição (ex.: 'NOVO'). */
    public function buscarSituacaoId(string $descricao): ?int
    {
        $stmt = $this->conn->prepare(
            "SELECT PEDIDO_SITUACAO_ID FROM PEDIDO_SITUACAO WHERE DESCRICAO = ?"
        );
        $stmt->execute([$descricao]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /** Próximo número de pedido (sequencial e único). */
    public function proximoNumero(): int
    {
        $stmt = $this->conn->query(
            "SELECT COALESCE(MAX(PEDIDO_NUMERO), 0) + 1 FROM PEDIDO"
        );
        return (int) $stmt->fetchColumn();
    }

    /**
     * Insere o cabeçalho do pedido e retorna o PEDIDO_ID gerado.
     * Deve ser chamado dentro de uma transação (US05).
     */
    public function inserirPedido(int $clienteId, int $numero, int $situacaoId): int
    {
        $sql = "INSERT INTO PEDIDO (CLIENTE_ID, PEDIDO_NUMERO, DATA_PEDIDO, SITUACAO_ID)
                VALUES (?, ?, CURRENT_DATE, ?) RETURNING PEDIDO_ID";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$clienteId, $numero, $situacaoId]);
        return (int) $stmt->fetchColumn();
    }

    /** Insere um item do pedido. */
    public function inserirItem(int $pedidoId, int $produtoId, int $quantidade, float $preco): bool
    {
        $sql = "INSERT INTO ITEM_PEDIDO (PEDIDO_ID, PRODUTO_ID, QUANTIDADE, PRECO)
                VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$pedidoId, $produtoId, $quantidade, $preco]);
    }
}

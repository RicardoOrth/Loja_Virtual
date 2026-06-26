<?php

require_once __DIR__ . "/../config/bootstrap.php";

header("Content-Type: application/json; charset=utf-8");

function responderJson(int $statusCode, array $dados): void
{
    http_response_code($statusCode);

    echo json_encode(
        $dados,
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    );

    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    responderJson(405, [
        "erro" => true,
        "mensagem" => "Método não permitido. Use GET."
    ]);
}

try {
    $id = null;
    $numero = null;
    $cliente = null;
    $pagina = 1;
    $limite = 10;

    if (isset($_GET["id"]) && $_GET["id"] !== "") {
        if (!ctype_digit($_GET["id"]) || (int) $_GET["id"] <= 0) {
            responderJson(400, [
                "erro" => true,
                "mensagem" => "O ID do pedido deve ser um número inteiro positivo."
            ]);
        }

        $id = (int) $_GET["id"];
    }

    if (isset($_GET["numero"]) && $_GET["numero"] !== "") {
        if (!ctype_digit($_GET["numero"]) || (int) $_GET["numero"] <= 0) {
            responderJson(400, [
                "erro" => true,
                "mensagem" => "O número do pedido deve ser um número inteiro positivo."
            ]);
        }

        $numero = (int) $_GET["numero"];
    }

    if (isset($_GET["cliente"]) && $_GET["cliente"] !== "") {
        $cliente = trim($_GET["cliente"]);
    }

    if (isset($_GET["pagina"]) && $_GET["pagina"] !== "") {
        if (!ctype_digit($_GET["pagina"]) || (int) $_GET["pagina"] <= 0) {
            responderJson(400, [
                "erro" => true,
                "mensagem" => "A página deve ser um número inteiro positivo."
            ]);
        }

        $pagina = (int) $_GET["pagina"];
    }

    if (isset($_GET["limite"]) && $_GET["limite"] !== "") {
        if (!ctype_digit($_GET["limite"]) || (int) $_GET["limite"] <= 0) {
            responderJson(400, [
                "erro" => true,
                "mensagem" => "O limite deve ser um número inteiro positivo."
            ]);
        }

        $limite = (int) $_GET["limite"];
    }

    if ($limite > 100) {
        $limite = 100;
    }

    $db = getDB();
    $pedidoDAO = new PedidoDAO($db);

    $totalRegistros = $pedidoDAO->contarPedidos(
        $id,
        $numero,
        $cliente
    );

    $pedidos = $pedidoDAO->consultarPedidos(
        $id,
        $numero,
        $cliente,
        $pagina,
        $limite
    );

    foreach ($pedidos as &$pedido) {
        $pedido["valor_total"] = (float) $pedido["valor_total"];

        $pedido["itens"] = $pedidoDAO->consultarItensPedido(
            (int) $pedido["pedido_id"]
        );

        foreach ($pedido["itens"] as &$item) {
            $item["valor_unitario"] = (float) $item["valor_unitario"];
            $item["valor_total_item"] = (float) $item["valor_total_item"];
        }

        unset($item);
    }

    unset($pedido);

    $totalPaginas = $totalRegistros > 0
        ? (int) ceil($totalRegistros / $limite)
        : 0;

    if ($totalRegistros === 0) {
        responderJson(200, [
            "erro" => false,
            "mensagem" => "Nenhum pedido encontrado.",
            "paginacao" => [
                "pagina" => $pagina,
                "limite" => $limite,
                "total_registros" => 0,
                "total_paginas" => 0
            ],
            "dados" => []
        ]);
    }

    responderJson(200, [
        "erro" => false,
        "mensagem" => "Pedidos consultados com sucesso.",
        "paginacao" => [
            "pagina" => $pagina,
            "limite" => $limite,
            "total_registros" => $totalRegistros,
            "total_paginas" => $totalPaginas
        ],
        "dados" => $pedidos
    ]);

} catch (Exception $e) {
    responderJson(500, [
        "erro" => true,
        "mensagem" => "Erro interno ao consultar pedidos.",
        "detalhe" => $e->getMessage()
    ]);
}
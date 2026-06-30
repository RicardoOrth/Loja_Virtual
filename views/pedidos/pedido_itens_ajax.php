<?php
session_start();
require_once __DIR__ . "/../../config/bootstrap.php";

header("Content-Type: application/json; charset=utf-8");

function responder(array $dados, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['usuario_id'])) {
    responder([
        'ok' => false,
        'mensagem' => 'Usuario nao autenticado.'
    ], 401);
}

$pedidoId = $_GET['pedido_id'] ?? "";
$pagina = $_GET['pagina'] ?? "1";
$limite = $_GET['limite'] ?? "5";

if (!ctype_digit($pedidoId) || (int) $pedidoId <= 0) {
    responder([
        'ok' => false,
        'mensagem' => 'Pedido invalido.'
    ], 400);
}

if (!ctype_digit($pagina) || (int) $pagina <= 0) {
    $pagina = "1";
}

if (!ctype_digit($limite) || (int) $limite <= 0) {
    $limite = "5";
}

$pedidoId = (int) $pedidoId;
$pagina = (int) $pagina;
$limite = min((int) $limite, 20);
$usuarioTipo = (int) ($_SESSION['usuario_tipo'] ?? 0);
$usuarioClienteId = $usuarioTipo === 2 ? (int) $_SESSION['usuario_id'] : null;
$fornecedorLogadoId = null;

try {
    $db = getDB();
    $pedidoDAO = new PedidoDAO($db);

    if ($usuarioTipo === 3) {
        $fornecedorDAO = new FornecedorDAO($db);
        $fornecedorLogado = $fornecedorDAO->buscarPorUsuarioId($_SESSION['usuario_id']);

        if (!$fornecedorLogado) {
            responder([
                'ok' => false,
                'mensagem' => 'Fornecedor nao encontrado para o usuario logado.'
            ], 403);
        }

        $fornecedorLogadoId = (int) $fornecedorLogado['fornecedor_id'];
    }

    $pedido = $pedidoDAO->consultarPedidoDetalhado($pedidoId, $usuarioClienteId, $fornecedorLogadoId);

    if (!$pedido) {
        responder([
            'ok' => false,
            'mensagem' => 'Pedido nao encontrado ou sem permissao de acesso.'
        ], 404);
    }

    $total = $pedidoDAO->contarItensPedido($pedidoId, $usuarioClienteId, $fornecedorLogadoId);
    $totalPaginas = $total > 0 ? (int) ceil($total / $limite) : 1;

    if ($pagina > $totalPaginas) {
        $pagina = $totalPaginas;
    }

    $itens = $pedidoDAO->consultarItensPedidoPaginado($pedidoId, $pagina, $limite, $usuarioClienteId, $fornecedorLogadoId);
    $fotos = $pedidoDAO->consultarFotosPedido($pedidoId, $usuarioClienteId, $fornecedorLogadoId);

    // Ajusta os dados dos itens e formata o caminho da imagem
    foreach ($itens as &$item) {
        $item['quantidade'] = (int) $item['quantidade'];
        $item['valor_unitario'] = (float) $item['valor_unitario'];
        $item['valor_total_item'] = (float) $item['valor_total_item'];

        // Se o produto tiver imagem, concatena com a BASE_URL do sistema
        if (!empty($item['imagem_caminho'])) {
            $item['foto'] = BASE_URL . $item['imagem_caminho'];
        } else {
            $item['foto'] = null; // Tratamento caso não tenha foto
        }
    }
    unset($item);

    // Ajusta o array separado de fotos (caso seu front-end utilize ele)
    foreach ($fotos as &$foto) {
        if (!empty($foto['imagem_caminho'])) {
            $foto['foto'] = BASE_URL . $foto['imagem_caminho'];
        } else {
            $foto['foto'] = null;
        }
    }
    unset($foto);

    responder([
        'ok' => true,
        'pedido_id' => $pedidoId,
        'itens' => $itens,
        'fotos' => $fotos,
        'paginacao' => [
            'pagina' => $pagina,
            'limite' => $limite,
            'total_registros' => $total,
            'total_paginas' => $totalPaginas
        ]
    ]);
} catch (Exception $e) {
    responder([
        'ok' => false,
        'mensagem' => 'Erro ao carregar itens do pedido.',
        'detalhe' => $e->getMessage()
    ], 500);
}

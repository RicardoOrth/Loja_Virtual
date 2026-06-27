<?php
/**
 * Finalização do Pedido (US05).
 *
 * Fluxo:
 *  - Carrinho vazio: volta para o carrinho.
 *  - Usuário não logado: redireciona para o login (que tem link de cadastro
 *    para clientes novos) e retorna para cá após autenticar.
 *  - Logado como cliente: grava o pedido com situação "NOVO", cria os itens
 *    e dá baixa no estoque — tudo em uma única transação.
 *  - Ao final, esvazia o carrinho e volta ao carrinho com a mensagem de status.
 */

session_start();
require_once __DIR__ . "/../../config/bootstrap.php";

$urlCarrinho = BASE_URL . "/views/carrinho/carrinho.php";

/** Redireciona de volta ao carrinho com um status na query string. */
function voltarCarrinho($status, $extra = []) {
    global $urlCarrinho;
    $query = http_build_query(array_merge(['status' => $status], $extra));
    header("Location: " . $urlCarrinho . "?" . $query);
    exit;
}

// Carrinho vazio: nada a finalizar.
if (empty(Carrinho::itens())) {
    voltarCarrinho('vazio');
}

// Não logado: manda para o login e volta para cá depois de autenticar.
if (!isset($_SESSION['usuario_id'])) {
    $retorno = urlencode(BASE_URL . "/views/carrinho/finalizar.php");
    header("Location: " . BASE_URL . "/views/auth/login.php?redirect=" . $retorno);
    exit;
}

$db = getDB();
$clienteDAO = new ClienteDAO($db);
$produtoDAO = new ProdutoDAO($db);
$estoqueDAO = new EstoqueDAO($db);
$pedidoDAO  = new PedidoDAO($db);

// Só clientes (tipo 2) podem encerrar pedido.
$cliente = $clienteDAO->buscarPorUsuario($_SESSION['usuario_id']);
if (!$cliente) {
    voltarCarrinho('erro', ['msg' => 'Apenas clientes podem encerrar pedidos.']);
}
$clienteId = $cliente['cliente_id'] ?? $cliente['CLIENTE_ID'];

try {
    $db->beginTransaction();

    $itens = Carrinho::itens();

    // Revalida o estoque de cada item antes de gravar.
    foreach ($itens as $item) {
        $produto = $produtoDAO->buscarComEstoquePorId($item['produto_id']);
        if (!$produto || (int) $produto['quantidade'] < (int) $item['quantidade']) {
            $nome = $produto['nome'] ?? ('produto #' . $item['produto_id']);
            throw new Exception("Estoque insuficiente para \"$nome\".");
        }
    }

    // Situação inicial "NOVO".
    $situacaoNovo = $pedidoDAO->buscarSituacaoId('NOVO');
    if ($situacaoNovo === null) {
        throw new Exception("Situação 'NOVO' não cadastrada (PEDIDO_SITUACAO).");
    }

    // Cabeçalho do pedido.
    $numero   = $pedidoDAO->proximoNumero();
    $pedidoId = $pedidoDAO->inserirPedido($clienteId, $numero, $situacaoNovo);

    // Itens + baixa de estoque.
    foreach ($itens as $item) {
        $pedidoDAO->inserirItem($pedidoId, $item['produto_id'], $item['quantidade'], $item['preco']);
        if (!$estoqueDAO->baixarEstoque($item['produto_id'], $item['quantidade'])) {
            throw new Exception("Falha ao baixar o estoque de \"" . $item['nome'] . "\".");
        }
    }

    $db->commit();
    Carrinho::limpar();
    voltarCarrinho('sucesso', ['pedido' => $numero]);

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    voltarCarrinho('erro', ['msg' => $e->getMessage()]);
}

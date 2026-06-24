<?php
/**
 * Endpoint AJAX do Carrinho de Compras (US04).
 *
 * Recebe ações via POST e responde sempre em JSON, sem recarregar a página.
 * Ações suportadas (campo "acao"):
 *   - adicionar : produto_id, quantidade  -> soma ao carrinho
 *   - atualizar : produto_id, quantidade  -> define a quantidade do item
 *   - remover   : produto_id              -> remove o item
 *   - listar    : (nenhum)                -> apenas retorna o estado atual
 *
 * Regra de estoque: só adiciona/atualiza se houver estoque suficiente.
 * Se a quantidade pedida ultrapassar o disponível, retorna erro e informa
 * a quantidade máxima em estoque (campo "estoque_maximo").
 */

session_start();
require_once __DIR__ . "/../../config/bootstrap.php";

header('Content-Type: application/json; charset=utf-8');

/** Monta o payload padrão com o estado atual do carrinho. */
function estadoCarrinho() {
    $total = Carrinho::total();
    return [
        'itens'           => Carrinho::itens(),
        'total'           => $total,
        'total_formatado' => 'R$ ' . number_format($total, 2, ',', '.'),
        'quantidade_total'=> Carrinho::quantidadeTotal(),
    ];
}

/** Responde em JSON e encerra. */
function responder($ok, $mensagem = "", $extra = []) {
    echo json_encode(array_merge([
        'ok'        => $ok,
        'mensagem'  => $mensagem,
        'carrinho'  => estadoCarrinho(),
    ], $extra));
    exit;
}

$acao = $_POST['acao'] ?? $_GET['acao'] ?? 'listar';

try {
    $db = getDB();
    $produtoDAO = new ProdutoDAO($db);

    switch ($acao) {

        case 'adicionar': {
            $produto_id = (int) ($_POST['produto_id'] ?? 0);
            $quantidade = (int) ($_POST['quantidade'] ?? 1);

            if ($produto_id <= 0 || $quantidade <= 0) {
                responder(false, "Dados inválidos para adicionar ao carrinho.");
            }

            $produto = $produtoDAO->buscarComEstoquePorId($produto_id);
            if (!$produto) {
                responder(false, "Produto não encontrado.");
            }

            $estoque = (int) $produto['quantidade'];
            if ($estoque <= 0) {
                responder(false, "Produto indisponível (sem estoque).", ['estoque_maximo' => 0]);
            }

            // Considera o que já está no carrinho para não estourar o estoque.
            $jaNoCarrinho = Carrinho::quantidadeDoProduto($produto_id);
            $desejado     = $jaNoCarrinho + $quantidade;

            if ($desejado > $estoque) {
                $disponivel = $estoque - $jaNoCarrinho;
                $msg = $disponivel > 0
                    ? "Estoque insuficiente. Máximo disponível: {$estoque} (você pode adicionar mais {$disponivel})."
                    : "Você já tem o estoque máximo deste produto no carrinho ({$estoque}).";
                responder(false, $msg, ['estoque_maximo' => $estoque]);
            }

            Carrinho::adicionar($produto_id, $produto['nome'], $produto['preco'], $quantidade, $produto['fornecedor_nome']);
            responder(true, "Produto adicionado ao carrinho.");
            break;
        }

        case 'atualizar': {
            $produto_id = (int) ($_POST['produto_id'] ?? 0);
            $quantidade = (int) ($_POST['quantidade'] ?? 0);

            if ($produto_id <= 0) {
                responder(false, "Produto inválido.");
            }

            // Quantidade 0 ou negativa remove o item.
            if ($quantidade <= 0) {
                Carrinho::remover($produto_id);
                responder(true, "Item removido do carrinho.");
            }

            $produto = $produtoDAO->buscarComEstoquePorId($produto_id);
            if (!$produto) {
                responder(false, "Produto não encontrado.");
            }

            $estoque = (int) $produto['quantidade'];
            if ($quantidade > $estoque) {
                responder(false, "Estoque insuficiente. Máximo disponível: {$estoque}.", ['estoque_maximo' => $estoque]);
            }

            Carrinho::atualizar($produto_id, $quantidade);
            responder(true, "Quantidade atualizada.");
            break;
        }

        case 'remover': {
            $produto_id = (int) ($_POST['produto_id'] ?? 0);
            if ($produto_id <= 0) {
                responder(false, "Produto inválido.");
            }
            Carrinho::remover($produto_id);
            responder(true, "Item removido do carrinho.");
            break;
        }

        case 'listar':
        default:
            responder(true, "");
            break;
    }
} catch (Exception $e) {
    responder(false, "Erro no servidor: " . $e->getMessage());
}

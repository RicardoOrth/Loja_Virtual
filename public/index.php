<?php
session_start();
require_once __DIR__ . "/../config/bootstrap.php";

$db = getDB();
$produtoDAO = new ProdutoDAO($db);

// Lógica de Consulta com Paginação (12 produtos por página)
$busca = trim($_GET['search'] ?? "");
$limite = 12;
$paginaAtual = (isset($_GET['pagina']) && ctype_digit($_GET['pagina'])) ? (int) $_GET['pagina'] : 1;
if ($paginaAtual < 1) {
    $paginaAtual = 1;
}

// Obtém o total de registros para calcular as páginas necessárias
$totalRegistros = $produtoDAO->contarTotal($busca);
$totalPaginas = $totalRegistros > 0 ? (int) ceil($totalRegistros / $limite) : 1;

if ($paginaAtual > $totalPaginas) {
    $paginaAtual = $totalPaginas;
}

// Busca o lote exato de 12 produtos para a página atual
$listaProdutos = $produtoDAO->consultarPaginado($busca, $paginaAtual, $limite);

/** Função auxiliar para construir os links da paginação mantendo buscas ativas */
function urlPaginacao(int $numPagina, string $busca): string
{
    $params = ['pagina' => $numPagina];
    if ($busca !== "") {
        $params['search'] = $busca;
    }
    return "?" . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minha Loja Virtual</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css?v=<?= filemtime(ROOT_PATH . '/css/style.css') ?>">
</head>

<body>

    <?php include ROOT_PATH . "/views/layouts/header.php"; ?>

    <main class="container loja-home">
        <h2>Confira nossos produtos</h2>
        <hr>

        <form method="GET" class="lista-busca" style="margin-bottom: 25px;">
            <div class="busca-campo">
                <i class="fa-solid fa-magnifying-glass busca-icone"></i>
                <input type="text" name="search" placeholder="Buscar por nome ou descrição do produto..." value="<?= htmlspecialchars($busca) ?>">
            </div>
            <button type="submit" class="btn">Buscar</button>
            <?php if ($busca !== ""): ?>
                <a href="?" class="btn btn-secundario" style="text-decoration:none; display: inline-flex; align-items: center;">Limpar</a>
            <?php endif; ?>
        </form>

        <div class="vitrine">
            <?php foreach ($listaProdutos as $p): ?>
                <div class="produto-card">
                    
                    <a href="<?= BASE_URL ?>/views/produtos/detalhes_produto.php?id=<?= $p['produto_id'] ?>" style="text-decoration: none; display: block;">
                        <div style="background:#f9f9f9; height:150px; border-radius:4px; display:flex; align-items:center; justify-content:center; overflow:hidden; border: 1px solid #eee;">
                            <?php if (!empty($p['imagem_caminho'])): ?>
                                <img src="<?= BASE_URL . $p['imagem_caminho'] ?>" alt="<?= htmlspecialchars($p['nome']) ?>" style="width:100%; height:100%; object-fit:cover;">
                            <?php else: ?>
                                <div style="color:#ccc; display:flex; flex-direction:column; align-items:center; gap:8px;">
                                    <i class="fa-solid fa-image" style="font-size:32px;"></i>
                                    <span style="font-size:12px;">Sem imagem</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </a>

                    <a href="<?= BASE_URL ?>/views/produtos/detalhes_produto.php?id=<?= $p['produto_id'] ?>" style="text-decoration: none; color: inherit;">
                        <h3><?= htmlspecialchars($p['nome']) ?></h3>
                    </a>
                    
                    <p class="fornecedor-tag">Fornecedor: <?= htmlspecialchars($p['fornecedor_nome']) ?></p>
                    <p class="preco">R$ <?= number_format($p['preco'], 2, ',', '.') ?></p>

                    <?php $semEstoque = (int)$p['quantidade'] <= 0; ?>
                    <?php if ($semEstoque): ?>
                        <button class="btn" style="width:100%" disabled>Indisponível</button>
                    <?php else: ?>
                        <button class="btn" style="width:100%" onclick="adicionarAoCarrinho(<?= $p['produto_id'] ?>)">Adicionar ao Carrinho</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php if (count($listaProdutos) == 0): ?>
                <p style="grid-column: 1/-1; text-align: center; color: #666; margin: 20px 0;">Nenhum produto encontrado para a sua busca.</p>
            <?php endif; ?>
        </div>

        <?php if ($totalPaginas > 1): ?>
            <div class="paginacao" style="display: flex; justify-content: center; align-items: center; gap: 10px; margin: 30px 0;">

                <?php if ($paginaAtual > 1): ?>
                    <a class="btn btn-secundario" href="<?= urlPaginacao($paginaAtual - 1, $busca) ?>" style="text-decoration: none;">Anterior</a>
                <?php else: ?>
                    <button class="btn btn-secundario" disabled>Anterior</button>
                <?php endif; ?>

                <div class="paginas-numeros" style="display: flex; gap: 5px;">
                    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                        <?php if ($i === $paginaAtual): ?>
                            <button class="btn btn-ativo" disabled style="padding: 5px 10px; font-weight: bold; background-color: #007bff; color: #fff; border: 1px solid #007bff; cursor: not-allowed;">
                                <?= $i ?>
                            </button>
                        <?php else: ?>
                            <a class="btn btn-secundario" href="<?= urlPaginacao($i, $busca) ?>" style="padding: 5px 10px; text-decoration: none;">
                                <?= $i ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>

                <?php if ($paginaAtual < $totalPaginas): ?>
                    <a class="btn btn-secundario" href="<?= urlPaginacao($paginaAtual + 1, $busca) ?>" style="text-decoration: none;">Próxima</a>
                <?php else: ?>
                    <button class="btn btn-secundario" disabled>Próxima</button>
                <?php endif; ?>

            </div>
        <?php endif; ?>
    </main>

    <div id="toast" class="toast" style="display:none;"></div>

    <script>
        const AJAX_URL = "<?= BASE_URL ?>/views/carrinho/carrinho_ajax.php";

        // Mantive as funções JavaScript idênticas com os ajustes de correção feitos antes
        function mostrarToast(texto, sucesso) {
            const toast = document.getElementById("toast");
            toast.textContent = texto;
            toast.classList.toggle("toast-ok", !!sucesso);
            toast.classList.toggle("toast-erro", !sucesso);
            toast.style.display = "block";
            clearTimeout(toast._timer);
            toast._timer = setTimeout(() => {
                toast.style.display = "none";
            }, 3000);
        }

        function atualizarBadgeHeader(quantidade) {
            const badge = document.getElementById("cart-badge");
            if (!badge) return;
            badge.textContent = quantidade;
            badge.style.display = quantidade > 0 ? "" : "none";
        }

        async function adicionarAoCarrinho(produtoId) {
            try {
                const resposta = await fetch(AJAX_URL, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: new URLSearchParams({
                        acao: "adicionar",
                        produto_id: produtoId,
                        quantidade: 1
                    }).toString()
                });
                const dados = await resposta.json();
                if (dados.carrinho) atualizarBadgeHeader(dados.carrinho.quantidade_total);
                mostrarToast(dados.mensagem, dados.ok);
            } catch (e) {
                mostrarToast("Falha de comunicação com o servidor.", false);
            }
        }
    </script>

</body>

</html>
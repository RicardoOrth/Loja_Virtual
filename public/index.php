<?php
session_start();
require_once __DIR__ . "/../config/bootstrap.php";

$db = getDB();
$produtoDAO = new ProdutoDAO($db);

// Busca os produtos para exibir na vitrine
$listaProdutos = $produtoDAO->consultar("");
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Minha Loja Virtual</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">
</head>
<body>

    <?php include ROOT_PATH . "/views/layouts/header.php"; ?>
    
    <div class="container">

    <section class="container">
        <h2>Confira nossos produtos</h2>
        <hr>
        
        <div class="vitrine">
            <?php foreach($listaProdutos as $p): ?>
                <div class="produto-card">
                    <div style="background:#f0f0f0; height:150px; border-radius:4px; display:flex; align-items:center; justify-content:center; color:#ccc;">
                        [Sem Imagem]
                    </div>
                    <h3><?= htmlspecialchars($p['nome']) ?></h3>
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

            <?php if(count($listaProdutos) == 0): ?>
                <p>Nenhum produto cadastrado no momento.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Aviso (toast) de feedback ao adicionar ao carrinho -->
    <div id="toast" class="toast" style="display:none;"></div>

    <script>
        const AJAX_URL = "<?= BASE_URL ?>/views/carrinho/carrinho_ajax.php";

        function mostrarToast(texto, sucesso) {
            const toast = document.getElementById("toast");
            toast.textContent = texto;
            toast.classList.toggle("toast-ok", !!sucesso);
            toast.classList.toggle("toast-erro", !sucesso);
            toast.style.display = "block";
            clearTimeout(toast._timer);
            toast._timer = setTimeout(() => { toast.style.display = "none"; }, 3000);
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
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: new URLSearchParams({ acao: "adicionar", produto_id: produtoId, quantidade: 1 }).toString()
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
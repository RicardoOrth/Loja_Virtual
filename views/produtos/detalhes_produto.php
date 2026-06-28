<?php
session_start();
require_once __DIR__ . "/../../config/bootstrap.php";

$idProduto = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : 0;

if ($idProduto <= 0) {
    header("Location: " . BASE_URL . "/public/index.php");
    exit;
}

$db = getDB();
$produtoDAO = new ProdutoDAO($db);

// 1. Busca os dados mestre do produto (inclui estoque e fornecedor)
$produto = $produtoDAO->buscarComEstoquePorId($idProduto);

if (!$produto) {
    die("Produto não encontrado ou indisponível.");
}

// 2. Busca todas as fotos vinculadas a este produto para o carrossel
$imagens = $produtoDAO->buscarImagensPorProdutoId($idProduto);
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($produto['nome']) ?> - Detalhes</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css?v=<?= filemtime(ROOT_PATH . '/css/style.css') ?>">
    <style>
        .detalhes-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 40px;
            margin-top: 30px;
            align-items: start;
        }

        /* Novo contêiner isolado para a galeria */
        .produto-galeria {
            background: #fff;
            border: 1px solid #e6e6e6;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Palco do Carrossel Isolado e Forçado a 400x400 */
        .detalhes-carrossel-palco {
            width: 100%;
            max-width: 400px;
            height: 400px;
            position: relative;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border-radius: 4px;
        }

        .detalhes-carrossel-palco img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        /* Customização das setas sobrepostas à imagem */
        .seta-carrossel {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid #ccc;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            z-index: 10;
        }

        .seta-carrossel:hover {
            background: #007bff;
            color: #fff;
            border-color: #007bff;
        }

        .seta-esq {
            left: 10px;
        }

        .seta-dir {
            right: 10px;
        }

        .compra-card {
            background: #f8f9fa;
            border: 1px solid #e3e6f0;
            border-radius: 8px;
            padding: 24px;
            position: sticky;
            top: 20px;
        }

        .compra-card .preco-grande {
            font-size: 28px;
            color: #28a745;
            font-weight: bold;
            margin: 10px 0;
        }

        .qtd-seletor {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }

        .qtd-seletor input {
            width: 70px;
            text-align: center;
            padding: 8px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        @media (max-width: 768px) {
            .detalhes-container {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .detalhes-carrossel-palco {
                height: 300px;
            }
        }
    </style>
</head>

<body>

    <?php include ROOT_PATH . "/views/layouts/header.php"; ?>

    <main class="container">
        <div class="detalhes-container">

            <div class="produto-principal">
                <div class="produto-galeria">
                    <div class="detalhes-carrossel-palco">
                        <button type="button" class="seta-carrossel seta-esq" onclick="moverFoto(-1)" title="Foto anterior">
                            <i class="fa-solid fa-chevron-left"></i>
                        </button>

                        <div id="carousel-stage" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                            <div class="pedido-foto-placeholder">Carregando fotos...</div>
                        </div>

                        <button type="button" class="seta-carrossel seta-dir" onclick="moverFoto(1)" title="Próxima foto">
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    </div>
                    <div id="carousel-dots" class="carousel-dots" style="margin-top: 15px;"></div>
                </div>

                <div style="margin-top: 30px;">
                    <h2>Descrição do Produto</h2>
                    <p style="color: #555; line-height: 1.6; font-size: 16px; white-space: pre-wrap;">
                        <?= !empty($produto['descricao']) ? htmlspecialchars($produto['descricao']) : 'Nenhuma descrição informada para este produto.' ?>
                    </p>
                </div>
            </div>

            <aside class="compra-card">
                <h1><?= htmlspecialchars($produto['nome']) ?></h1>
                <p style="color: #777; font-size: 14px; margin-bottom: 15px;">Fornecedor: <strong><?= htmlspecialchars($produto['fornecedor_nome']) ?></strong></p>
                <hr style="border: 0; border-top: 1px solid #eee;">

                <div class="preco-grande">R$ <?= number_format($produto['preco'], 2, ',', '.') ?></div>

                <?php $estoque = (int)$produto['quantidade']; ?>
                <?php if ($estoque > 0): ?>
                    <p style="color: #28a745; font-size: 14px;"><i class="fa-solid fa-boxes-stacked"></i> Em estoque: <strong><?= $estoque ?> unidades</strong></p>

                    <div class="qtd-seletor">
                        <label for="input-qtd">Quantidade:</label>
                        <input type="number" id="input-qtd" value="1" min="1" max="<?= $estoque ?>" oninput="validarQtdMax(this, <?= $estoque ?>)">
                    </div>

                    <button class="btn" style="width: 100%; padding: 12px; font-size: 16px;" onclick="adicionarComQuantidade(<?= $produto['produto_id'] ?>)">
                        <i class="fa-solid fa-cart-shopping"></i> Adicionar ao Carrinho
                    </button>
                <?php else: ?>
                    <p style="color: #dc3545; font-size: 15px; font-weight: bold; margin: 20px 0;"><i class="fa-solid fa-circle-xmark"></i> Produto Indisponível</p>
                    <button class="btn" style="width: 100%; padding: 12px;" disabled>Sem Estoque</button>
                <?php endif; ?>

                <a href="<?= BASE_URL ?>/public/index.php" class="btn btn-secundario" style="width: 100%; text-align: center; margin-top: 10px; text-decoration: none; display: block; box-sizing: border-box;">
                    Voltar para a Vitrine
                </a>
            </aside>

        </div>
    </main>

    <div id="toast" class="toast" style="display:none;"></div>

    <script>
        const AJAX_URL = "<?= BASE_URL ?>/views/carrinho/carrinho_ajax.php";

        // Injeta a lista de caminhos de imagem vindas do PHP diretamente no escopo JS
        const fotosDoProduto = <?= json_encode($imagens) ?>;
        const BASE_URL_SISTEMA = "<?= BASE_URL ?>";
        let fotoAtual = 0;

        function renderizarCarrossel() {
            const stage = document.getElementById("carousel-stage");
            const dots = document.getElementById("carousel-dots");

            if (!fotosDoProduto.length) {
                stage.innerHTML = '<div class="pedido-foto-placeholder"><i class="fa-regular fa-image" style="font-size:48px;"></i><span>Sem fotos para este produto</span></div>';
                dots.innerHTML = "";
                return;
            }

            const imgAtual = fotosDoProduto[fotoAtual];
            stage.innerHTML = '<img src="' + BASE_URL_SISTEMA + imgAtual.caminho + '" alt="Imagem do Produto" style="width:100%; height:100%; object-fit:contain; display:block;">';

            dots.innerHTML = fotosDoProduto.map(function(_, indice) {
                return '<button type="button" class="' + (indice === fotoAtual ? 'ativo' : '') + '" onclick="irParaFoto(' + indice + ')" title="Foto ' + (indice + 1) + '"></button>';
            }).join("");
        }

        function moverFoto(direcao) {
            if (!fotosDoProduto.length) return;
            fotoAtual = (fotoAtual + direcao + fotosDoProduto.length) % fotosDoProduto.length;
            renderizarCarrossel();
        }

        function irParaFoto(indice) {
            fotoAtual = indice;
            renderizarCarrossel();
        }

        function validarQtdMax(input, maximo) {
            let valor = parseInt(input.value);
            if (valor > maximo) input.value = maximo;
            if (valor < 1 || isNaN(valor)) input.value = 1;
        }

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

        async function adicionarComQuantidade(produtoId) {
            const inputQtd = document.getElementById("input-qtd");
            const quantidadeDesejada = inputQtd ? parseInt(inputQtd.value) : 1;

            try {
                const resposta = await fetch(AJAX_URL, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: new URLSearchParams({
                        acao: "adicionar",
                        produto_id: produtoId,
                        quantidade: quantidadeDesejada
                    }).toString()
                });

                const dados = await resposta.json();
                if (dados.carrinho) atualizarBadgeHeader(dados.carrinho.quantidade_total);
                mostrarToast(dados.mensagem, dados.ok);
            } catch (e) {
                mostrarToast("Falha de comunicação com o servidor.", false);
            }
        }

        // Inicializa o carrossel assim que a página abre
        renderizarCarrossel();
    </script>
</body>

</html>
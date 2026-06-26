<?php
session_start();
require_once __DIR__ . "/../../config/bootstrap.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: " . BASE_URL . "/views/auth/login.php");
    exit;
}

$db = getDB();
$pedidoDAO = new PedidoDAO($db);
$usuarioTipo = (int) ($_SESSION['usuario_tipo'] ?? 0);
$usuarioClienteId = $usuarioTipo === 2 ? (int) $_SESSION['usuario_id'] : null;

$pedidoId = null;
if (isset($_GET['id']) && ctype_digit($_GET['id']) && (int) $_GET['id'] > 0) {
    $pedidoId = (int) $_GET['id'];
}

$busca = trim($_GET['busca'] ?? "");
$buscaNumero = "";
$buscaCliente = "";

if ($busca !== "") {
    if (ctype_digit($busca)) {
        $buscaNumero = $busca;
    } else {
        $buscaCliente = $busca;
    }
}

$pedidos = $pedidoDAO->consultarPedidos(
    null,
    $buscaNumero !== "" && ctype_digit($buscaNumero) ? (int) $buscaNumero : null,
    $buscaCliente,
    1,
    50,
    $usuarioClienteId
);

$pedido = $pedidoId !== null
    ? $pedidoDAO->consultarPedidoDetalhado($pedidoId, $usuarioClienteId)
    : null;

function linkDetalhesPedido(int $pedidoId, string $busca): string
{
    $params = [
        'id' => $pedidoId
    ];

    if ($busca !== "") {
        $params['busca'] = $busca;
    }

    return BASE_URL . "/views/pedidos/pedidos.php?" . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Pedidos - TechStore</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css?v=<?= filemtime(ROOT_PATH . '/css/style.css') ?>">
    <style>
        .pedido-detalhe-abaixo .pedido-itens {
            display: grid;
            gap: 18px;
        }

        .pedido-detalhe-abaixo .pedido-item {
            display: grid;
            grid-template-columns: 220px minmax(0, 1fr) minmax(190px, auto);
            gap: 24px;
            align-items: stretch;
            min-height: 220px;
            padding: 0 24px 0 0;
            border: 1px solid #e6e6e6;
            border-radius: 8px;
            background: #fff;
            overflow: hidden;
        }

        .pedido-detalhe-abaixo .pedido-item-foto {
            width: 220px;
            height: 220px;
            min-height: 220px;
            border-right: 1px solid #eee;
            background: #fff;
        }

        .pedido-detalhe-abaixo .pedido-item-foto img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .pedido-detalhe-abaixo .pedido-item-sem-foto {
            width: 100%;
            height: 100%;
            min-height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
            color: #777;
            text-align: center;
            overflow-wrap: anywhere;
        }

        .pedido-detalhe-abaixo .pedido-item-texto,
        .pedido-detalhe-abaixo .pedido-item-valores {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 10px;
            min-width: 0;
        }

        .pedido-detalhe-abaixo .pedido-item-valores {
            align-items: flex-end;
            text-align: right;
            justify-self: end;
        }

        .pedido-detalhe-abaixo .pedido-item-nome {
            font-size: 18px;
            color: #333;
        }

        .pedido-detalhe-abaixo .pedido-item-descricao {
            margin: 0;
            color: #666;
        }

        @media (max-width: 768px) {
            .pedido-detalhe-abaixo .pedido-item {
                grid-template-columns: 120px minmax(0, 1fr);
                gap: 14px;
                min-height: 120px;
                padding: 0 14px 0 0;
            }

            .pedido-detalhe-abaixo .pedido-item-foto {
                width: 120px;
                height: 120px;
                min-height: 120px;
            }

            .pedido-detalhe-abaixo .pedido-item-sem-foto {
                min-height: 120px;
                font-size: 12px;
            }

            .pedido-detalhe-abaixo .pedido-item-texto {
                padding-top: 12px;
            }

            .pedido-detalhe-abaixo .pedido-item-valores {
                grid-column: 2;
                align-items: flex-start;
                justify-content: flex-start;
                text-align: left;
                padding-bottom: 12px;
                gap: 6px;
            }
        }
    </style>
</head>
<body>
    <?php include ROOT_PATH . "/views/layouts/header.php"; ?>

    <div class="container pedidos-page">
        <h2>Consulta de Pedidos</h2>

        <form method="GET" class="pedido-filtros-tabela">
            <input type="text" name="busca" placeholder="Buscar por numero do pedido ou nome do cliente..." value="<?= htmlspecialchars($busca) ?>">
            <button type="submit" class="btn">Filtrar</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Nro</th>
                    <th>Data</th>
                    <th>Cliente</th>
                    <th>Situacao</th>
                    <th>Valor Total</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pedidos as $item): ?>
                    <?php $ativo = $pedido && (int) $pedido['pedido_id'] === (int) $item['pedido_id']; ?>
                    <tr class="<?= $ativo ? 'pedido-linha-selecionada' : '' ?>">
                        <td><?= htmlspecialchars($item['pedido_numero']) ?></td>
                        <td><?= date('d/m/Y', strtotime($item['data_pedido'])) ?></td>
                        <td><?= htmlspecialchars($item['cliente_nome']) ?></td>
                        <td><?= htmlspecialchars($item['situacao']) ?></td>
                        <td>R$ <?= number_format((float) $item['valor_total'], 2, ',', '.') ?></td>
                        <td style="white-space: nowrap;">
                            <a href="<?= linkDetalhesPedido((int) $item['pedido_id'], $busca) ?>" class="btn-edit">
                                Detalhes
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (count($pedidos) === 0): ?>
                    <tr>
                        <td colspan="6">Nenhum pedido encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <hr>

        <section class="pedido-detalhe-abaixo">
            <?php if (!$pedido): ?>
                <div class="pedido-empty-state">
                    <i class="fa-solid fa-receipt"></i>
                    <p>Selecione um pedido na tabela para visualizar os detalhes.</p>
                </div>
            <?php else: ?>
                <h2>Detalhes do Pedido #<?= htmlspecialchars($pedido['pedido_numero']) ?></h2>

                <div class="pedido-master-card">
                    <div>
                        <span class="pedido-label">Pedido</span>
                        <strong>#<?= htmlspecialchars($pedido['pedido_numero']) ?></strong>
                    </div>
                    <div>
                        <span class="pedido-label">Data</span>
                        <strong><?= date('d/m/Y', strtotime($pedido['data_pedido'])) ?></strong>
                    </div>
                    <div>
                        <span class="pedido-label">Cliente</span>
                        <strong><?= htmlspecialchars($pedido['cliente_nome']) ?></strong>
                    </div>
                    <div>
                        <span class="pedido-label">Situacao</span>
                        <strong class="pedido-status pedido-status-<?= strtolower($pedido['situacao']) ?>">
                            <?= htmlspecialchars($pedido['situacao']) ?>
                        </strong>
                    </div>
                    <div>
                        <span class="pedido-label">Total</span>
                        <strong>R$ <?= number_format((float) $pedido['valor_total'], 2, ',', '.') ?></strong>
                    </div>
                </div>

                <div class="pedido-carrossel">
                    <div id="carousel-stage" class="carousel-stage">
                        <div class="pedido-foto-placeholder">Carregando fotos...</div>
                    </div>
                    <div class="carousel-controls">
                        <button type="button" class="carousel-btn" onclick="moverFoto(-1)" title="Foto anterior">
                            <i class="fa-solid fa-chevron-left"></i>
                        </button>
                        <button type="button" class="carousel-btn" onclick="moverFoto(1)" title="Proxima foto">
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
                <div id="carousel-dots" class="carousel-dots"></div>

                <div class="pedido-itens-topo">
                    <h3>Itens do Pedido</h3>
                    <span id="pedido-itens-info"></span>
                </div>
                <div id="pedido-itens" class="pedido-itens">
                    <p class="pedido-vazio">Carregando itens...</p>
                </div>
                <div id="pedido-paginacao" class="paginacao"></div>
            <?php endif; ?>
        </section>
    </div>

    <?php if ($pedido): ?>
        <script>
            const PEDIDO_ID = <?= (int) $pedido['pedido_id'] ?>;
            const ITENS_URL = "<?= BASE_URL ?>/views/pedidos/pedido_itens_ajax.php";
            let fotos = [];
            let fotoAtual = 0;

            function escaparHtml(texto) {
                return String(texto ?? "").replace(/[&<>"']/g, function (char) {
                    return {
                        "&": "&amp;",
                        "<": "&lt;",
                        ">": "&gt;",
                        '"': "&quot;",
                        "'": "&#039;"
                    }[char];
                });
            }

            function mimeImagem(base64) {
                if (base64.startsWith("/9j/")) return "image/jpeg";
                if (base64.startsWith("iVBOR")) return "image/png";
                if (base64.startsWith("R0lGOD")) return "image/gif";
                return "image/jpeg";
            }

            function formatarBRL(valor) {
                return "R$ " + Number(valor).toLocaleString("pt-BR", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function fotoHtml(item) {
                if (item.foto_base64) {
                    return '<img src="data:' + mimeImagem(item.foto_base64) + ';base64,' + item.foto_base64 + '" alt="' + escaparHtml(item.produto_nome) + '">';
                }
                return '<div class="pedido-foto-placeholder"><i class="fa-regular fa-image"></i><span>' + escaparHtml(item.produto_nome) + '</span></div>';
            }

            function fotoItemHtml(item) {
                if (item.foto_base64) {
                    return '<img src="data:' + mimeImagem(item.foto_base64) + ';base64,' + item.foto_base64 + '" alt="' + escaparHtml(item.produto_nome) + '">';
                }
                return '<div class="pedido-item-sem-foto">' + escaparHtml(item.produto_nome) + '</div>';
            }

            function renderizarCarrossel() {
                const stage = document.getElementById("carousel-stage");
                const dots = document.getElementById("carousel-dots");

                if (!fotos.length) {
                    stage.innerHTML = '<div class="pedido-foto-placeholder">Sem fotos para exibir.</div>';
                    dots.innerHTML = "";
                    return;
                }

                stage.innerHTML = fotoHtml(fotos[fotoAtual]);
                dots.innerHTML = fotos.map(function (_, indice) {
                    return '<button type="button" class="' + (indice === fotoAtual ? 'ativo' : '') + '" onclick="irParaFoto(' + indice + ')" title="Foto ' + (indice + 1) + '"></button>';
                }).join("");
            }

            function moverFoto(direcao) {
                if (!fotos.length) return;
                fotoAtual = (fotoAtual + direcao + fotos.length) % fotos.length;
                renderizarCarrossel();
            }

            function irParaFoto(indice) {
                fotoAtual = indice;
                renderizarCarrossel();
            }

            function renderizarItens(dados) {
                const box = document.getElementById("pedido-itens");
                const paginacao = document.getElementById("pedido-paginacao");
                const info = document.getElementById("pedido-itens-info");

                if (!dados.itens.length) {
                    box.innerHTML = '<p class="pedido-vazio">Nenhum item encontrado.</p>';
                    paginacao.innerHTML = "";
                    info.textContent = "";
                    return;
                }

                box.innerHTML = dados.itens.map(function (item) {
                    return '<article class="pedido-item">' +
                        '<div class="pedido-item-foto">' + fotoItemHtml(item) + '</div>' +
                        '<div class="pedido-item-texto">' +
                            '<strong class="pedido-item-nome">' + escaparHtml(item.produto_nome) + '</strong>' +
                            '<p class="pedido-item-descricao">' + escaparHtml(item.produto_descricao) + '</p>' +
                        '</div>' +
                        '<div class="pedido-item-valores">' +
                            '<span class="pedido-item-qtd">Quantidade: ' + item.quantidade + '</span>' +
                            '<span class="pedido-item-preco">Preco Unitário: ' + formatarBRL(item.valor_unitario) + '</span>' +
                            '<strong class="pedido-item-total">Total: ' + formatarBRL(item.valor_total_item) + '</strong>' +
                        '</div>' +
                    '</article>';
                }).join("");

                const totalItens = dados.paginacao.total_registros;
                info.textContent = totalItens === 1 ? "1 Item" : totalItens + " Itens";
                paginacao.innerHTML =
                    '<button class="btn btn-secundario" ' + (dados.paginacao.pagina <= 1 ? 'disabled' : '') + ' onclick="carregarItens(' + (dados.paginacao.pagina - 1) + ')">Anterior</button>' +
                    '<span>Pagina ' + dados.paginacao.pagina + ' de ' + dados.paginacao.total_paginas + '</span>' +
                    '<button class="btn btn-secundario" ' + (dados.paginacao.pagina >= dados.paginacao.total_paginas ? 'disabled' : '') + ' onclick="carregarItens(' + (dados.paginacao.pagina + 1) + ')">Proxima</button>';
            }

            async function carregarItens(pagina) {
                const params = new URLSearchParams({
                    pedido_id: PEDIDO_ID,
                    pagina: pagina,
                    limite: 5
                });

                const resposta = await fetch(ITENS_URL + "?" + params.toString());
                const dados = await resposta.json();

                if (!dados.ok) {
                    document.getElementById("pedido-itens").innerHTML = '<p class="pedido-vazio">' + escaparHtml(dados.mensagem) + '</p>';
                    return;
                }

                fotos = dados.fotos;
                if (fotoAtual >= fotos.length) fotoAtual = 0;
                renderizarCarrossel();
                renderizarItens(dados);
            }

            carregarItens(1);
        </script>
    <?php endif; ?>
</body>
</html>

<?php
session_start();
require_once __DIR__ . "/../../config/bootstrap.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: " . BASE_URL . "/views/auth/login.php");
    exit;
}

$db = getDB();
$pedidoDAO = new PedidoDAO($db);
$estoqueDAO = new EstoqueDAO($db);
$fornecedorDAO = new FornecedorDAO($db);
$usuarioTipo = (int) ($_SESSION['usuario_tipo'] ?? 0);
$usuarioClienteId = $usuarioTipo === 2 ? (int) $_SESSION['usuario_id'] : null;
$fornecedorLogadoId = null;

if ($usuarioTipo === 3) {
    $fornecedorLogado = $fornecedorDAO->buscarPorUsuarioId($_SESSION['usuario_id']);

    if (!$fornecedorLogado) {
        die("Fornecedor nao encontrado para o usuario logado.");
    }

    $fornecedorLogadoId = (int) $fornecedorLogado['fornecedor_id'];
}

// Admin (1) e Fornecedor (3) podem gerenciar o status dos pedidos (US06).
$podeGerenciar = in_array($usuarioTipo, [1, 3], true);

// Mudança de status do pedido (US06): ENTREGUE ou CANCELADO, gravando a data.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'mudar_status') {
    $idAlvo     = ctype_digit($_POST['pedido_id'] ?? '') ? (int) $_POST['pedido_id'] : 0;
    $novoStatus = $_POST['situacao'] ?? '';

    if (!$podeGerenciar) {
        $statusMsg = 'sem_permissao';
    } elseif ($idAlvo <= 0 || !in_array($novoStatus, ['ENTREGUE', 'CANCELADO'], true)) {
        $statusMsg = 'invalido';
    } elseif ($fornecedorLogadoId !== null && !$pedidoDAO->consultarPedidoDetalhado($idAlvo, null, $fornecedorLogadoId)) {
        $statusMsg = 'sem_permissao';
    } else {
        try {
            if ($novoStatus === 'CANCELADO') {
                // Cancelar: muda o status e devolve os itens ao estoque, numa
                // única transação. Só repõe se o pedido ainda estava "NOVO"
                // (evita repor estoque duas vezes em cancelamentos repetidos).
                $db->beginTransaction();
                if ($pedidoDAO->situacaoAtual($idAlvo) === 'NOVO') {
                    foreach ($pedidoDAO->consultarItensPedido($idAlvo) as $item) {
                        $estoqueDAO->reporEstoque($item['produto_id'], $item['quantidade']);
                    }
                }
                $pedidoDAO->atualizarSituacao($idAlvo, $novoStatus);
                $db->commit();
            } else {
                $pedidoDAO->atualizarSituacao($idAlvo, $novoStatus);
            }
            $statusMsg = 'ok';
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $statusMsg = 'erro';
        }
    }

    header("Location: " . BASE_URL . "/views/pedidos/pedidos.php?id=$idAlvo&status_msg=$statusMsg");
    exit;
}

// Aviso resultante da ação de status (lido após o redirect).
$statusBanner = "";
$statusBannerClasse = "";
switch ($_GET['status_msg'] ?? '') {
    case 'ok':            $statusBanner = "Situação do pedido atualizada.";                              $statusBannerClasse = "pedido-msg-ok";   break;
    case 'sem_permissao': $statusBanner = "Você não tem permissão para alterar o status.";       $statusBannerClasse = "pedido-msg-erro"; break;
    case 'invalido':      $statusBanner = "Ação inválida.";                                       $statusBannerClasse = "pedido-msg-erro"; break;
    case 'erro':          $statusBanner = "Não foi possível atualizar o status do pedido.";       $statusBannerClasse = "pedido-msg-erro"; break;
}

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

// Paginação da tabela de pedidos
$porPaginaTabela = 10;
$paginaTabela = (isset($_GET['pagina']) && ctype_digit($_GET['pagina']) && (int) $_GET['pagina'] > 0)
    ? (int) $_GET['pagina']
    : 1;

$numeroFiltro = $buscaNumero !== "" && ctype_digit($buscaNumero) ? (int) $buscaNumero : null;

$totalPedidos = $pedidoDAO->contarPedidos(null, $numeroFiltro, $buscaCliente, $usuarioClienteId, $fornecedorLogadoId);
$totalPaginasTabela = $totalPedidos > 0 ? (int) ceil($totalPedidos / $porPaginaTabela) : 1;
if ($paginaTabela > $totalPaginasTabela) {
    $paginaTabela = $totalPaginasTabela;
}

$pedidos = $pedidoDAO->consultarPedidos(
    null,
    $numeroFiltro,
    $buscaCliente,
    $paginaTabela,
    $porPaginaTabela,
    $usuarioClienteId,
    $fornecedorLogadoId
);

$pedido = $pedidoId !== null
    ? $pedidoDAO->consultarPedidoDetalhado($pedidoId, $usuarioClienteId, $fornecedorLogadoId)
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

function linkPaginaPedidos(int $pagina, string $busca, ?int $pedidoId): string
{
    $params = ['pagina' => $pagina];

    if ($busca !== "") {
        $params['busca'] = $busca;
    }

    if ($pedidoId !== null) {
        $params['id'] = $pedidoId;
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
        .pedido-detalhe-abaixo .pedido-itens { display: grid; gap: 18px; }
        .pedido-detalhe-abaixo .pedido-item { display: grid; grid-template-columns: 220px minmax(0, 1fr) minmax(190px, auto); gap: 24px; align-items: stretch; min-height: 220px; padding: 0 24px 0 0; border: 1px solid #e6e6e6; border-radius: 8px; background: #fff; overflow: hidden; }
        .pedido-detalhe-abaixo .pedido-item-foto { width: 220px; height: 220px; min-height: 220px; border-right: 1px solid #eee; background: #fff; }
        .pedido-detalhe-abaixo .pedido-item-foto img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .pedido-detalhe-abaixo .pedido-item-sem-foto { width: 100%; height: 100%; min-height: 220px; display: flex; align-items: center; justify-content: center; padding: 18px; color: #777; text-align: center; overflow-wrap: anywhere; }
        .pedido-detalhe-abaixo .pedido-item-texto, .pedido-detalhe-abaixo .pedido-item-valores { display: flex; flex-direction: column; justify-content: center; gap: 10px; min-width: 0; }
        .pedido-detalhe-abaixo .pedido-item-valores { align-items: flex-end; text-align: right; justify-self: end; }
        .pedido-detalhe-abaixo .pedido-item-nome { font-size: 18px; color: #333; }
        .pedido-detalhe-abaixo .pedido-item-descricao { margin: 0; color: #666; }
        @media (max-width: 768px) {
            .pedido-detalhe-abaixo .pedido-item { grid-template-columns: 120px minmax(0, 1fr); gap: 14px; min-height: 120px; padding: 0 14px 0 0; }
            .pedido-detalhe-abaixo .pedido-item-foto { width: 120px; height: 120px; min-height: 120px; }
            .pedido-detalhe-abaixo .pedido-item-sem-foto { min-height: 120px; font-size: 12px; }
            .pedido-detalhe-abaixo .pedido-item-texto { padding-top: 12px; }
            .pedido-detalhe-abaixo .pedido-item-valores { grid-column: 2; align-items: flex-start; justify-content: flex-start; text-align: left; padding-bottom: 12px; gap: 6px; }
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
                    <th>Nº</th>
                    <th>Data</th>
                    <th>Cliente</th>
                    <th>Situação</th>
                    <th>Valor Total</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pedidos as $item): ?>
                    <?php $ativo = $pedido && (int) $pedido['pedido_id'] === (int) $item['pedido_id']; ?>
                    <tr class="<?= $ativo ? 'pedido-linha-selecionada' : '' ?>">
                        <td><?= htmlspecialchars($item['pedido_numero']) ?></td>
                        <td><?= date('d/m/Y', strtotime($item['data_pedido'])) ?></td>
                        <td><?= htmlspecialchars($item['cliente_nome']) ?></td>
                        <td>
                            <span class="pedido-status pedido-status-<?= strtolower($item['situacao']) ?>">
                                <?= htmlspecialchars($item['situacao']) ?>
                            </span>
                        </td>
                        <td>R$ <?= number_format((float) $item['valor_total'], 2, ',', '.') ?></td>
                        <td style="text-align:center;">
                            <div class="kebab-wrap">
                                <button type="button" class="kebab-btn" onclick="alternarKebab(this)" aria-label="Ações" aria-haspopup="true">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </button>
                                <div class="kebab-menu">
                                    <a href="<?= linkDetalhesPedido((int) $item['pedido_id'], $busca) ?>">
                                        <i class="fa-solid fa-eye"></i> Detalhes
                                    </a>
                                    <?php if ($podeGerenciar && strtoupper($item['situacao']) === 'NOVO'): ?>
                                        <form method="POST" onsubmit="return confirm('Marcar este pedido como ENTREGUE?');">
                                            <input type="hidden" name="acao" value="mudar_status">
                                            <input type="hidden" name="pedido_id" value="<?= (int) $item['pedido_id'] ?>">
                                            <input type="hidden" name="situacao" value="ENTREGUE">
                                            <button type="submit"><i class="fa-solid fa-check"></i> Pedido Entregue</button>
                                        </form>
                                        <form method="POST" onsubmit="return confirm('Cancelar este pedido?');">
                                            <input type="hidden" name="acao" value="mudar_status">
                                            <input type="hidden" name="pedido_id" value="<?= (int) $item['pedido_id'] ?>">
                                            <input type="hidden" name="situacao" value="CANCELADO">
                                            <button type="submit" class="kebab-item-perigo"><i class="fa-solid fa-xmark"></i> Cancelar Pedido</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
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

        <?php if ($totalPaginasTabela > 1): ?>
            <div class="paginacao">
                <?php if ($paginaTabela > 1): ?>
                    <a class="btn btn-secundario" href="<?= linkPaginaPedidos($paginaTabela - 1, $busca, $pedidoId) ?>">Anterior</a>
                <?php else: ?>
                    <button class="btn btn-secundario" disabled>Anterior</button>
                <?php endif; ?>

                <span>Página <?= $paginaTabela ?> de <?= $totalPaginasTabela ?></span>

                <?php if ($paginaTabela < $totalPaginasTabela): ?>
                    <a class="btn btn-secundario" href="<?= linkPaginaPedidos($paginaTabela + 1, $busca, $pedidoId) ?>">Próxima</a>
                <?php else: ?>
                    <button class="btn btn-secundario" disabled>Próxima</button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <hr>

        <section class="pedido-detalhe-abaixo">
            <?php if (!$pedido): ?>
                <div class="pedido-empty-state">
                    <i class="fa-solid fa-receipt"></i>
                    <p>Selecione um pedido na tabela para visualizar os detalhes.</p>
                </div>
            <?php else: ?>
                <h2>Detalhes do Pedido #<?= htmlspecialchars($pedido['pedido_numero']) ?></h2>

                <?php if ($statusBanner): ?>
                    <div class="pedido-msg <?= $statusBannerClasse ?>"><?= $statusBanner ?></div>
                <?php endif; ?>

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
                        <span class="pedido-label">Situação</span>
                        <strong class="pedido-status pedido-status-<?= strtolower($pedido['situacao']) ?>">
                            <?= htmlspecialchars($pedido['situacao']) ?>
                        </strong>
                    </div>
                    <div>
                        <span class="pedido-label">Total</span>
                        <strong>R$ <?= number_format((float) $pedido['valor_total'], 2, ',', '.') ?></strong>
                    </div>
                </div>

                <?php if (!empty($pedido['data_entrega'])): ?>
                    <p class="pedido-data-acao">Entregue em <?= date('d/m/Y', strtotime($pedido['data_entrega'])) ?>.</p>
                <?php elseif (!empty($pedido['data_cancelamento'])): ?>
                    <p class="pedido-data-acao">Cancelado em <?= date('d/m/Y', strtotime($pedido['data_cancelamento'])) ?>.</p>
                <?php endif; ?>

                <?php if ($podeGerenciar && strtoupper($pedido['situacao']) === 'NOVO'): ?>
                    <div class="pedido-acoes">
                        <form method="POST" style="display:inline" onsubmit="return confirm('Marcar este pedido como ENTREGUE?');">
                            <input type="hidden" name="acao" value="mudar_status">
                            <input type="hidden" name="pedido_id" value="<?= (int) $pedido['pedido_id'] ?>">
                            <input type="hidden" name="situacao" value="ENTREGUE">
                            <button type="submit" class="btn btn-entregue">
                                <i class="fa-solid fa-check"></i> Pedido Entregue
                            </button>
                        </form>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Cancelar este pedido?');">
                            <input type="hidden" name="acao" value="mudar_status">
                            <input type="hidden" name="pedido_id" value="<?= (int) $pedido['pedido_id'] ?>">
                            <input type="hidden" name="situacao" value="CANCELADO">
                            <button type="submit" class="btn btn-cancelar"><i class="fa-solid fa-xmark"></i> Cancelar Pedido</button>
                        </form>
                    </div>
                <?php endif; ?>

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

                <div class="pedido-itens-total">
                    <span>Total do pedido</span>
                    <strong>R$ <?= number_format((float) $pedido['valor_total'], 2, ',', '.') ?></strong>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <script>
        // Menu de ações (três pontinhos) das linhas da tabela de pedidos
        function alternarKebab(btn) {
            const menu = btn.nextElementSibling;
            const estavaAberto = menu.classList.contains("aberto");
            fecharTodosKebabs();
            if (!estavaAberto) menu.classList.add("aberto");
        }
        function fecharTodosKebabs() {
            document.querySelectorAll(".kebab-menu.aberto").forEach(function (m) {
                m.classList.remove("aberto");
            });
        }
        document.addEventListener("click", function (e) {
            if (!e.target.closest(".kebab-wrap")) fecharTodosKebabs();
        });
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") fecharTodosKebabs();
        });
    </script>

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

            function formatarBRL(valor) {
                return "R$ " + Number(valor).toLocaleString("pt-BR", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            // ADJUSTED: Agora renderiza caminhos de string nativos diretos do servidor
            function fotoHtml(item) {
                if (item.foto) {
                    return '<img src="' + item.foto + '" alt="' + escaparHtml(item.produto_nome) + '">';
                }
                return '<div class="pedido-foto-placeholder"><i class="fa-regular fa-image"></i><span>' + escaparHtml(item.produto_nome) + '</span></div>';
            }

            // ADJUSTED: Agora renderiza caminhos de string nativos diretos do servidor
            function fotoItemHtml(item) {
                if (item.foto) {
                    return '<img src="' + item.foto + '" alt="' + escaparHtml(item.produto_nome) + '">';
                }
                return '<div class="pedido-item-sem-foto">' + escaparHtml(item.produto_nome) + '</div>';
            }

            function renderizarCarrossel() {
                const stage = document.getElementById("carousel-stage");
                const dots = document.getElementById("carousel-dots");

                if (!fotos.length || (fotos.length === 1 && !fotos[0].foto)) {
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
                            '<span class="pedido-item-preco">Preço Unitário: ' + formatarBRL(item.valor_unitario) + '</span>' +
                            '<strong class="pedido-item-total">Total: ' + formatarBRL(item.valor_total_item) + '</strong>' +
                        '</div>' +
                    '</article>';
                }).join("");

                const totalItens = dados.paginacao.total_registros;
                info.textContent = totalItens === 1 ? "1 Item" : totalItens + " Itens";
                paginacao.innerHTML =
                    '<button class="btn btn-secundario" ' + (dados.paginacao.pagina <= 1 ? 'disabled' : '') + ' onclick="carregarItens(' + (dados.paginacao.pagina - 1) + ')">Anterior</button>' +
                    '<span>Página ' + dados.paginacao.pagina + ' de ' + dados.paginacao.total_paginas + '</span>' +
                    '<button class="btn btn-secundario" ' + (dados.paginacao.pagina >= dados.paginacao.total_paginas ? 'disabled' : '') + ' onclick="carregarItens(' + (dados.paginacao.pagina + 1) + ')">Próxima</button>';
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

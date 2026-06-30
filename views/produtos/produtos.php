<?php
session_start();
require_once __DIR__ . "/../../config/bootstrap.php";

if (!isset($_SESSION['usuario_id'])) { 
    header("Location: " . BASE_URL . "/public/index.php"); 
    exit; 
}

$db = getDB();
$produtoDAO   = new ProdutoDAO($db);
$estoqueDAO   = new EstoqueDAO($db);
$fornecedorDAO = new FornecedorDAO($db);
$mensagem = "";
$usuarioTipo = (int) ($_SESSION['usuario_tipo'] ?? 0);
$fornecedorLogado = null;

if ($usuarioTipo === 3) {
    $fornecedorLogado = $fornecedorDAO->buscarPorUsuarioId($_SESSION['usuario_id']);

    if (!$fornecedorLogado) {
        die("Fornecedor nao encontrado para o usuario logado.");
    }
}

// Processar Cadastro (produto + estoque + imagens numa única transação coordenada pelo DAO)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bt_cadastrar'])) {
    try {
        $db->beginTransaction();

        $listaImagens = []; // Array que guardará os objetos ProdutoImagem

        // 1. Processar Múltiplos Uploads de Imagem
        if (isset($_FILES['imagens']) && is_array($_FILES['imagens']['name'])) {
            $totalArquivos = count($_FILES['imagens']['name']);

            for ($i = 0; $i < $totalArquivos; $i++) {
                // Verifica se não houve erro no arquivo atual
                if ($_FILES['imagens']['error'][$i] === UPLOAD_ERR_OK) {
                    $fileTmpPath = $_FILES['imagens']['tmp_name'][$i];
                    $fileName    = $_FILES['imagens']['name'][$i];
                    
                    $extensao = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $extensoesPermitidas = ['jpg', 'jpeg', 'png'];

                    if (!in_array($extensao, $extensoesPermitidas)) {
                        throw new Exception("Formato inválido no arquivo " . htmlspecialchars($fileName) . "! Apenas JPG, JPEG e PNG.");
                    }

                    // Definir diretório de salvamento
                    $diretorioDestino = ROOT_PATH . "/public/uploads/produtos/";
                    if (!is_dir($diretorioDestino)) {
                        mkdir($diretorioDestino, 0755, true);
                    }

                    // Gerar nome único para cada arquivo
                    $novoNomeArquivo = md5(uniqid(rand(), true)) . "." . $extensao;
                    $caminhoCompleto = $diretorioDestino . $novoNomeArquivo;

                    // Mover o arquivo para o servidor
                    if (move_uploaded_file($fileTmpPath, $caminhoCompleto)) {
                        $caminhoRelativo = "/public/uploads/produtos/" . $novoNomeArquivo;
                        
                        // Cria o objeto de model e adiciona no array
                        $listaImagens[] = new ProdutoImagem(0, $caminhoRelativo);
                    } else {
                        throw new Exception("Erro ao mover o arquivo: " . htmlspecialchars($fileName));
                    }
                }
            }
        }

        // 2. Inserir Produto passando a LISTA de imagens para o DAO
        $fornecedorId = $fornecedorLogado
            ? (int) $fornecedorLogado['fornecedor_id']
            : (int) $_POST['fornecedor_id'];

        $produto = new Produto($_POST['nome'], $_POST['descricao'], $fornecedorId);
        $produtoId = $produtoDAO->inserir($produto, $listaImagens);

        // 3. Inserir Estoque correspondente
        $estoqueDAO->inserir(new Estoque($produtoId, $_POST['qtd'], $_POST['preco']));

        $db->commit();
        echo "<script>
            alert('Produto, estoque e imagens cadastrados com sucesso!');
            window.location.href='" . BASE_URL . "/views/produtos/produtos.php';
        </script>";
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        die("ERRO AO CRIAR PRODUTO: " . $e->getMessage());
    }
}

// Busca fornecedores para o Select (Combo Box)
$fornecedores = $fornecedorLogado
    ? [$fornecedorLogado]
    : $fornecedorDAO->listarParaSelect();

// Lógica de Consulta com Paginação (8 itens por página - US02)
$busca = trim($_GET['search'] ?? "");
$limite = 8;
$paginaAtual = (isset($_GET['pagina']) && ctype_digit($_GET['pagina'])) ? (int) $_GET['pagina'] : 1;
if ($paginaAtual < 1) { $paginaAtual = 1; }

// Obtém totais e calcula as páginas necessárias
$totalRegistros = $produtoDAO->contarTotal(
    $busca,
    $fornecedorLogado ? (int) $fornecedorLogado['fornecedor_id'] : null
);
$totalPaginas = $totalRegistros > 0 ? (int) ceil($totalRegistros / $limite) : 1;

if ($paginaAtual > $totalPaginas) {
    $paginaAtual = $totalPaginas;
}

// Busca apenas os 8 produtos da página ativa
$lista = $produtoDAO->consultarPaginado(
    $busca,
    $paginaAtual,
    $limite,
    $fornecedorLogado ? (int) $fornecedorLogado['fornecedor_id'] : null
);

/** Função auxiliar para construir os links mantendo o termo buscado */
function urlPaginacao(int $numPagina, string $busca): string {
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
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css?v=<?= filemtime(ROOT_PATH . '/css/style.css') ?>">
    <title>Gestão de Produtos</title>
</head>

<body>
    <?php include ROOT_PATH . "/views/layouts/header.php"; ?>

    <div class="container">

        <div class="lista-toolbar">
            <h2>Produtos</h2>
            <button type="button" class="btn" onclick="abrirModal()">
                <i class="fa-solid fa-plus"></i> Novo Produto
            </button>
        </div>

        <form method="GET" class="lista-busca">
            <div class="busca-campo">
                <i class="fa-solid fa-magnifying-glass busca-icone"></i>
                <input type="text" name="search" placeholder="Buscar por nome ou código..." value="<?= htmlspecialchars($busca) ?>">
            </div>
            <button type="submit" class="btn">Buscar</button>
            <?php if ($busca !== ""): ?>
                <a href="<?= BASE_URL ?>/views/produtos/produtos.php" class="btn btn-secundario" style="text-decoration:none;">Limpar</a>
            <?php endif; ?>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Cód</th>
                    <th style="width: 50px; text-align: center;">Foto</th>
                    <th>Nome</th>
                    <th>Fornecedor</th>
                    <th>Estoque</th>
                    <th>Preço</th>
                    <th style="width:60px; text-align:center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lista as $p): ?>
                    <tr>
                        <td><?= $p['produto_id'] ?></td>
                        <td style="text-align: center; vertical-align: middle;">
                            <?php if (!empty($p['imagem_caminho'])): ?>
                                <img src="<?= BASE_URL . $p['imagem_caminho'] ?>" alt="Foto" style="width:40px; height:40px; object-fit:cover; border-radius:4px; display:block; margin:0 auto;">
                            <?php else: ?>
                                <i class="fa-solid fa-image" style="color:#ccc; font-size:20px;"></i>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($p['nome']) ?></td>
                        <td><?= htmlspecialchars($p['fornecedor_nome']) ?></td>
                        <td><?= $p['quantidade'] ?></td>
                        <td>R$ <?= number_format($p['preco'], 2, ',', '.') ?></td>
                        <td style="text-align:center;">
                            <div class="kebab-wrap">
                                <button type="button" class="kebab-btn" onclick="alternarKebab(this)" aria-label="Ações" aria-haspopup="true">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </button>
                                <div class="kebab-menu">
                                    <a href="<?= BASE_URL ?>/views/produtos/editar_produto.php?id=<?= $p['produto_id'] ?>">
                                        <i class="fa-solid fa-pen"></i> Alterar
                                    </a>
                                    <a href="<?= BASE_URL ?>/views/produtos/excluir_produto.php?id=<?= $p['produto_id'] ?>"
                                        class="kebab-item-perigo"
                                        onclick="return confirm('Excluir este produto?')">
                                        <i class="fa-solid fa-trash"></i> Remover
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (count($lista) === 0): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;">Nenhum produto encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($totalPaginas > 1): ?>
            <div class="paginacao" style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px;">
                
                <?php if ($paginaAtual > 1): ?>
                    <a class="btn btn-secundario" href="<?= urlPaginacao($paginaAtual - 1, $busca) ?>">Anterior</a>
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
                    <a class="btn btn-secundario" href="<?= urlPaginacao($paginaAtual + 1, $busca) ?>">Próxima</a>
                <?php else: ?>
                    <button class="btn btn-secundario" disabled>Próxima</button>
                <?php endif; ?>

            </div>
            <div style="text-align: center; color: #777; font-size: 13px; margin-top: 8px;">
                Mostrando página <?= $paginaAtual ?> de <?= $totalPaginas ?> (Total de <?= $totalRegistros ?> registros)
            </div>
        <?php endif; ?>

    </div>

    <div id="modal-novo-produto" class="modal-overlay" onclick="if (event.target === this) fecharModal()">
        <div class="modal-box">
            <div class="modal-head">
                <h3>Novo Produto</h3>
                <button type="button" class="modal-close" onclick="fecharModal()" aria-label="Fechar">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="text" name="nome" placeholder="Nome do Produto" required>
                <textarea name="descricao" placeholder="Descrição"></textarea>

                <label>Fornecedor:</label>
                <select name="fornecedor_id" required <?= $fornecedorLogado ? 'disabled' : '' ?>>
                    <?php if (!$fornecedorLogado): ?>
                        <option value="">Selecione um fornecedor</option>
                    <?php endif; ?>
                    <?php foreach ($fornecedores as $f): ?>
                        <option value="<?= $f['fornecedor_id'] ?>"><?= htmlspecialchars($f['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($fornecedorLogado): ?>
                    <input type="hidden" name="fornecedor_id" value="<?= (int) $fornecedorLogado['fornecedor_id'] ?>">
                <?php endif; ?>

                <input type="number" name="qtd" placeholder="Quantidade inicial" required>
                <input type="number" step="0.01" name="preco" placeholder="Preço (ex: 99.90)" required>

                <label style="margin-top: 8px; display: block; font-weight: bold; font-size: 14px;">Imagens do Produto:</label>
                <input type="file" name="imagens[]" accept="image/png, image/jpeg, image/jpg" required style="margin-top: 4px;" multiple>

                <button type="submit" name="bt_cadastrar" class="btn" style="width:100%; margin-top:12px;">Salvar Produto</button>
            </form>
        </div>
    </div>

    <script>
        function abrirModal() {
            document.getElementById("modal-novo-produto").classList.add("aberto");
        }

        function fecharModal() {
            document.getElementById("modal-novo-produto").classList.remove("aberto");
        }

        function alternarKebab(btn) {
            const menu = btn.nextElementSibling;
            const estavaAberto = menu.classList.contains("aberto");
            fecharTodosKebabs();
            if (!estavaAberto) menu.classList.add("aberto");
        }

        function fecharTodosKebabs() {
            document.querySelectorAll(".kebab-menu.aberto").forEach(function(m) {
                m.classList.remove("aberto");
            });
        }
        document.addEventListener("click", function(e) {
            if (!e.target.closest(".kebab-wrap")) fecharTodosKebabs();
        });
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") {
                fecharModal();
                fecharTodosKebabs();
            }
        });
    </script>
</body>

</html>

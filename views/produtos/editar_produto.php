<?php
session_start();
require_once __DIR__ . "/../../config/bootstrap.php";

if (!isset($_SESSION['usuario_id'])) { 
    header("Location: " . BASE_URL . "/public/index.php"); 
    exit; 
}

$db = getDB();
$produtoDAO = new ProdutoDAO($db);
$estoqueDAO = new EstoqueDAO($db);
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

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    // Busca produto e estoque juntos (via DAO)
    $prod = $produtoDAO->buscarComEstoquePorId($id);
    if (!$prod) die("Produto não encontrado.");

    if ($fornecedorLogado && (int) $prod['fornecedor_id'] !== (int) $fornecedorLogado['fornecedor_id']) {
        die("Voce nao tem permissao para alterar este produto.");
    }
} else {
    header("Location: " . BASE_URL . "/views/produtos/produtos.php");
    exit;
}

// AÇÃO 1: Processar exclusão de imagem individual via link GET secundário
if (isset($_GET['excluir_imagem_id'])) {
    try {
        $imgId = $_GET['excluir_imagem_id'];
        
        // Buscar os dados da imagem para descobrir o caminho do arquivo físico
        $stmtImg = $db->prepare("SELECT CAMINHO FROM PRODUTO_IMAGEM WHERE PRODUTO_IMAGEM_ID = ?");
        $stmtImg->execute([$imgId]);
        $foto = $stmtImg->fetch(PDO::FETCH_ASSOC);
        
        if ($foto) {
            // Apaga o arquivo físico do servidor se ele existir
            $arquivoFisico = ROOT_PATH . $foto['caminho'];
            if (file_exists($arquivoFisico)) {
                unlink($arquivoFisico);
            }
            // Apaga o registro correspondente no banco de dados
            $produtoDAO->excluirImagem($imgId);
            
            header("Location: " . BASE_URL . "/views/produtos/editar_produto.php?id=" . $id . "&msg=img_excluida");
            exit;
        }
    } catch (Exception $e) {
        $mensagem = "Erro ao excluir imagem: " . $e->getMessage();
    }
}

// AÇÃO 2: Salvar Alterações do Formulário (Dados + Novas Imagens)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();

        // 1. Update Produto básico
        $produtoDAO->atualizar(new Produto($_POST['nome'], $_POST['descricao'], null, $id));

        // 2. Update Estoque
        $estoqueDAO->atualizarPorProduto($id, $_POST['qtd'], $_POST['preco']);

        // 3. Processar novas imagens (Upload Opcional)
        if (isset($_FILES['novas_imagens']) && is_array($_FILES['novas_imagens']['name'])) {
            $totalArquivos = count($_FILES['novas_imagens']['name']);

            for ($i = 0; $i < $totalArquivos; $i++) {
                if ($_FILES['novas_imagens']['error'][$i] === UPLOAD_ERR_OK) {
                    $fileTmpPath = $_FILES['novas_imagens']['tmp_name'][$i];
                    $fileName    = $_FILES['novas_imagens']['name'][$i];
                    
                    $extensao = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $extensoesPermitidas = ['jpg', 'jpeg', 'png'];

                    if (!in_array($extensao, $extensoesPermitidas)) {
                        throw new Exception("Formato inválido! Apenas JPG, JPEG e PNG são permitidos.");
                    }

                    $diretorioDestino = ROOT_PATH . "/public/uploads/produtos/";
                    if (!is_dir($diretorioDestino)) {
                        mkdir($diretorioDestino, 0755, true);
                    }

                    $novoNomeArquivo = md5(uniqid(rand(), true)) . "." . $extensao;
                    $caminhoCompleto = $diretorioDestino . $novoNomeArquivo;

                    if (move_uploaded_file($fileTmpPath, $caminhoCompleto)) {
                        $caminhoRelativo = "/public/uploads/produtos/" . $novoNomeArquivo;
                        // Salva diretamente a nova imagem lincada ao ID do produto atual
                        $produtoDAO->inserirImagem($id, $caminhoRelativo);
                    }
                }
            }
        }

        $db->commit();
        header("Location: " . BASE_URL . "/views/produtos/produtos.php?msg=sucesso");
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        $mensagem = "Erro ao atualizar: " . $e->getMessage();
    }
}

// Busca a lista de TODAS as imagens vinculadas a este produto para exibir na galeria abaixo
$imagensAtuais = $produtoDAO->buscarImagensPorProdutoId($id);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css?v=<?= filemtime(ROOT_PATH . '/css/style.css') ?>">
    <title>Manutenção de Estoque</title>
    <style>
        .galeria-container { display: flex; flex-wrap: wrap; gap: 12px; margin: 10px 0 20px 0; }
        .galeria-item { position: relative; width: 100px; height: 100px; border: 1px solid #ddd; border-radius: 4px; overflow: hidden; background: #fafafa; }
        .galeria-item img { width: 100%; height: 100%; object-fit: cover; }
        .btn-remover-img { position: absolute; top: 4px; right: 4px; background: rgba(220, 53, 69, 0.9); color: white; border: none; border-radius: 50%; width: 22px; height: 22px; font-size: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; text-decoration: none; font-weight: bold; }
        .btn-remover-img:hover { background: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Alterar Produto / Estoque</h2>
        
        <?php if(!empty($mensagem)): ?>
            <div style="background:#f8d7da; color:#721c24; padding:10px; margin-bottom:15px; border-radius:4px;"><?= $mensagem ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <label>Nome:</label>
            <input type="text" name="nome" value="<?= htmlspecialchars($prod['nome']) ?>" required>
            
            <label>Descrição:</label>
            <textarea name="descricao"><?= htmlspecialchars($prod['descricao']) ?></textarea>
            
            <label>Quantidade em Estoque:</label>
            <input type="number" name="qtd" value="<?= $prod['quantidade'] ?>" required>
            
            <label>Preço Unitário (R$):</label>
            <input type="number" step="0.01" name="preco" value="<?= $prod['preco'] ?>" required>
            
            <label style="margin-top: 15px; display: block; font-weight: bold;">Imagens Atuais do Produto:</label>
            <div class="galeria-container">
                <?php foreach($imagensAtuais as $img): ?>
                    <div class="galeria-item">
                        <img src="<?= BASE_URL . $img['caminho'] ?>" alt="Foto do produto">
                        <a href="?id=<?= $id ?>&excluir_imagem_id=<?= $img['produto_imagem_id'] ?>" 
                           class="btn-remover-img" 
                           onclick="return confirm('Deseja realmente excluir esta imagem permanentemente?')" 
                           title="Excluir Imagem">&times;</a>
                    </div>
                <?php endforeach; ?>
                
                <?php if(count($imagensAtuais) === 0): ?>
                    <p style="color:#999; font-size:14px; font-style: italic;">Este produto não possui nenhuma imagem cadastrada.</p>
                <?php endif; ?>
            </div>

            <label style="font-weight: bold; display: block; margin-top: 15px;">Adicionar Novas Imagens:</label>
            <input type="file" name="novas_imagens[]" accept="image/png, image/jpeg, image/jpg" style="margin-top: 4px; margin-bottom: 20px;" multiple>
            
            <button type="submit" class="btn-edit" style="width:100%; padding:15px; cursor: pointer;">Salvar Alterações</button>
            <br><br>
            <a href="<?= BASE_URL ?>/views/produtos/produtos.php" style="display:block; text-align:center;">Voltar</a>
        </form>
    </div>
</body>
</html>

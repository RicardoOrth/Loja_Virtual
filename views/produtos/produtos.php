<?php
session_start();
require_once __DIR__ . "/../../config/bootstrap.php";

if (!isset($_SESSION['usuario_id'])) { header("Location: " . BASE_URL . "/public/index.php"); exit; }


$db = getDB();
$produtoDAO   = new ProdutoDAO($db);
$estoqueDAO   = new EstoqueDAO($db);
$fornecedorDAO = new FornecedorDAO($db);

// Processar Cadastro (produto + estoque numa única transação)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bt_cadastrar'])) {
    try {
        $db->beginTransaction();

        $produto = new Produto($_POST['nome'], $_POST['descricao'], $_POST['fornecedor_id']);
        $produtoId = $produtoDAO->inserir($produto);

        $estoqueDAO->inserir(new Estoque($produtoId, $_POST['qtd'], $_POST['preco']));

        $db->commit();
        echo "<script>
            alert('Produto e estoque criados com sucesso!');
            window.location.href='" . BASE_URL . "/views/produtos/produtos.php';
        </script>";
    } catch (Exception $e) {
        $db->rollBack();
        die("ERRO AO CRIAR PRODUTO: " . $e->getMessage());
    }
}

// Busca fornecedores para o Select (Combo Box)
$fornecedores = $fornecedorDAO->listarParaSelect();

// Busca de Produtos
$busca = $_GET['search'] ?? "";
$lista = $produtoDAO->consultar($busca);
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

    <div class="container">
        <h2>Novo Produto</h2>
        <form method="POST">
            <input type="text" name="nome" placeholder="Nome do Produto" required>
            <textarea name="descricao" placeholder="Descrição"></textarea>
            
            <label>Fornecedor:</label>
            <select name="fornecedor_id" required>
                <option value="">Selecione um fornecedor</option>
                <?php foreach($fornecedores as $f): ?>
                    <option value="<?= $f['fornecedor_id'] ?>"><?= htmlspecialchars($f['nome']) ?></option>
                <?php endforeach; ?>
            </select>

            <input type="number" name="qtd" placeholder="Quantidade inicial" required>
            <input type="number" step="0.01" name="preco" placeholder="Preço (ex: 99.90)" required>
            
            <button type="submit" name="bt_cadastrar" class="btn">Salvar Produto</button>
        </form>

        <hr>

        <h2>Consulta de Produtos</h2>
        <form method="GET">
            <input type="text" name="search" placeholder="Buscar por nome ou código..." value="<?= htmlspecialchars($busca) ?>">
            <button type="submit" class="btn">Filtrar</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Cód</th>
                    <th>Nome</th>
                    <th>Fornecedor</th>
                    <th>Estoque</th>
                    <th>Preço</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($lista as $p): ?>
                <tr>
                    <td><?= $p['produto_id'] ?></td>
                    <td><?= htmlspecialchars($p['nome']) ?></td>
                    <td><?= htmlspecialchars($p['fornecedor_nome']) ?></td>
                    <td><?= $p['quantidade'] ?></td>
                    <td>R$ <?= number_format($p['preco'], 2, ',', '.') ?></td>
                    <td style="white-space: nowrap;">
                        <a href="<?= BASE_URL ?>/views/produtos/editar_produto.php?id=<?= $p['produto_id'] ?>" class="btn-edit">Alterar</a>
                        <a href="<?= BASE_URL ?>/views/produtos/excluir_produto.php?id=<?= $p['produto_id'] ?>" class="btn-del" onclick="return confirm('Excluir este produto?')">Remover</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

<?php
session_start();
if (!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }

require_once "config/Database.php";
require_once "classes/Produto.php";

$db = (new Database())->getConnection();
$prodObj = new Produto($db);

// Processar Cadastro
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bt_cadastrar'])) {
    if($prodObj->cadastrar($_POST['nome'], $_POST['descricao'], $_POST['fornecedor_id'], $_POST['qtd'], $_POST['preco'])) {
        echo "<script>alert('Produto e estoque criados com sucesso!'); window.location.href='produtos.php';</script>";
    }
}

// Busca fornecedores para o Select (Combo Box)
$stmtForn = $db->query("SELECT fornecedor_id, nome FROM FORNECEDOR ORDER BY nome ASC");
$fornecedores = $stmtForn->fetchAll(PDO::FETCH_ASSOC);

// Busca de Produtos
$busca = $_GET['search'] ?? "";
$lista = $prodObj->consultar($busca);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="css/style.css">
    <title>Gestão de Produtos</title>
</head>
<body>
    <?php include "header.php"; ?>
    
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
                <?php while($p = $lista->fetch(PDO::FETCH_ASSOC)): ?>
                <tr>
                    <td><?= $p['produto_id'] ?></td>
                    <td><?= htmlspecialchars($p['nome']) ?></td>
                    <td><?= htmlspecialchars($p['fornecedor_nome']) ?></td>
                    <td><?= $p['quantidade'] ?></td>
                    <td>R$ <?= number_format($p['preco'], 2, ',', '.') ?></td>
                    <td style="white-space: nowrap;">
                        <a href="editar_produto.php?id=<?= $p['produto_id'] ?>" class="btn-edit">Alterar</a>
                        <a href="excluir_produto.php?id=<?= $p['produto_id'] ?>" class="btn-del" onclick="return confirm('Excluir este produto?')">Remover</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
<?php
session_start();
if (!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }

require_once "config/Database.php";
require_once "classes/Fornecedor.php";

$db = (new Database())->getConnection();
$fornObj = new Fornecedor($db);
$mensagem = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($fornObj->cadastrar($_POST)) {
        $mensagem = "Fornecedor cadastrado com sucesso!";
    } else {
        $mensagem = "Erro ao cadastrar fornecedor.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <link rel="stylesheet" href="css/style.css">
    <title>Gestão de Fornecedores</title>
</head>
<body>
    <?php include "header.php"; ?>
    
    <div class="container">

    <div class="container">
        <h2>Cadastrar Fornecedor</h2>
        <?php if($mensagem) echo "<p>$mensagem</p>"; ?>
        
        <form method="POST">
            <h3>Dados Gerais</h3>
            <input type="text" name="nome" placeholder="Nome da Empresa" required>
            <input type="email" name="email" placeholder="E-mail (Login)" required>
            <input type="password" name="senha" placeholder="Senha" required>
            <input type="text" name="telefone" placeholder="Telefone">
            <textarea name="descricao" placeholder="Descrição dos serviços"></textarea>

            <h3>Endereço</h3>
            <input type="text" name="rua" placeholder="Rua" required>
            <input type="text" name="numero" placeholder="Número" style="width: 20%;">
            <input type="text" name="bairro" placeholder="Bairro" style="width: 78%;">
            <input type="text" name="cidade" placeholder="Cidade" required>
            <input type="text" name="estado" placeholder="Estado (UF)" maxlength="2">
            <input type="text" name="cep" placeholder="CEP">

            <button type="submit" class="btn">Salvar Fornecedor</button>
        </form>

        <hr>
        <h2>Fornecedores Registados</h2>
        <table>
            <tr>
                <th>Nome</th>
                <th>Cidade</th>
                <th>E-mail</th>
                <th>Ações</th>
            </tr>
            <?php 
            $lista = $fornObj->listarTudo();
            while($f = $lista->fetch(PDO::FETCH_ASSOC)): 
            ?>
            <tr>
               <td><?= htmlspecialchars($f['nome']) ?></td>
               <td><?= htmlspecialchars($f['cidade']) ?></td>
               <td><?= htmlspecialchars($f['email']) ?></td>
               <td style="white-space: nowrap;">
                  <a href="editar_fornecedor.php?id=<?= $f['fornecedor_id'] ?>" class="btn-edit">Editar</a>
        
                  <a href="excluir_fornecedor.php?id=<?= $f['fornecedor_id'] ?>" 
                      class="btn-del" 
                      onclick="return confirm('Deseja realmente remover este fornecedor?')">
                   Remover
                </a>
                </td>
            </tr>
           <?php endwhile; ?>
        </table>
    </div>
</body>
</html>
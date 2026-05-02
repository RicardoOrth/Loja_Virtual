<?php
session_start();
if (!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }

require_once "config/Database.php";
$db = (new Database())->getConnection();
$mensagem = "";

// 1. Busca os dados atuais
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT f.*, e.*, u.email, u.senha 
            FROM FORNECEDOR f
            JOIN ENDERECO e ON f.endereco_id = e.endereco_id
            JOIN USUARIO u ON f.usuario_id = u.usuario_id
            WHERE f.fornecedor_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dados) { die("Fornecedor não encontrado."); }
}

// 2. Processa o Update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();

        // Update Usuário
        $db->prepare("UPDATE USUARIO SET email = ?, senha = ? WHERE usuario_id = ?")
           ->execute([$_POST['email'], $_POST['senha'], $dados['usuario_id']]);

        // Update Endereço (Ajuste para BAIRRO ou BAIRO conforme seu banco)
        $db->prepare("UPDATE ENDERECO SET rua = ?, numero = ?, bairro = ?, cidade = ?, estado = ?, cep = ? WHERE endereco_id = ?")
           ->execute([$_POST['rua'], $_POST['numero'], $_POST['bairro'], $_POST['cidade'], $_POST['estado'], $_POST['cep'], $dados['endereco_id']]);

        // Update Fornecedor
        $db->prepare("UPDATE FORNECEDOR SET nome = ?, telefone = ?, descricao = ? WHERE fornecedor_id = ?")
           ->execute([$_POST['nome'], $_POST['telefone'], $_POST['descricao'], $id]);

        $db->commit();
        header("Location: fornecedores.php?msg=sucesso");
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        $mensagem = "Erro ao atualizar: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="css/style.css">
    <title>Editar Fornecedor</title>
</head>
<body>
    <?php include "header.php"; ?>
    
    <div class="container">
        
    <div class="container">
        <h2>Editar Fornecedor: <?= htmlspecialchars($dados['nome']) ?></h2>
        <?php if($mensagem) echo "<p style='color:red'>$mensagem</p>"; ?>
        
        <form method="POST">
            <input type="text" name="nome" value="<?= $dados['nome'] ?>" required>
            <input type="email" name="email" value="<?= $dados['email'] ?>" required>
            <input type="text" name="senha" value="<?= $dados['senha'] ?>" required>
            <input type="text" name="telefone" value="<?= $dados['telefone'] ?>">
            <textarea name="descricao"><?= $dados['descricao'] ?></textarea>
            
            <input type="text" name="rua" value="<?= $dados['rua'] ?>" required>
            <input type="text" name="numero" value="<?= $dados['numero'] ?>" style="width:20%">
            <input type="text" name="bairro" value="<?= $dados['bairro'] ?>" style="width:75%">
            <input type="text" name="cidade" value="<?= $dados['cidade'] ?>" required>
            <input type="text" name="estado" value="<?= $dados['estado'] ?>" maxlength="2">
            <input type="text" name="cep" value="<?= $dados['cep'] ?>">
            
            <button type="submit" class="btn-edit" style="width:100%; padding:15px; cursor:pointer">Salvar Alterações</button>
        </form>
    </div>
</body>
</html>
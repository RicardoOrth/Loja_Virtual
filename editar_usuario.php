<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Segurança: Somente Admin pode editar usuários
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] != 1) {
    header("Location: index.php");
    exit;
}

require_once "config/Database.php";
$db = (new Database())->getConnection();
$mensagem = "";

// 1. CARREGAR DADOS ATUAIS
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT * FROM USUARIO WHERE USUARIO_ID = :id";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        die("Usuário não encontrado.");
    }
} else {
    header("Location: usuarios.php");
    exit;
}

// 2. PROCESSAR ATUALIZAÇÃO (UPDATE)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bt_atualizar'])) {
    try {
        $email = $_POST['email'];
        $senha = $_POST['senha'];
        $tipo = $_POST['tipo'];

        $sqlUpd = "UPDATE USUARIO SET EMAIL = ?, SENHA = ?, TIPO = ? WHERE USUARIO_ID = ?";
        $stmtUpd = $db->prepare($sqlUpd);
        
        if ($stmtUpd->execute([$email, $senha, $tipo, $id])) {
            $mensagem = "Usuário atualizado com sucesso!";
            // Atualiza os dados na tela
            $usuario['email'] = $email;
            $usuario['senha'] = $senha;
            $usuario['tipo'] = $tipo;
        }
    } catch (Exception $e) {
        $mensagem = "Erro ao atualizar: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="css/style.css">
    <title>Editar Usuário</title>
</head>
<body>
    <?php include "header.php"; ?>
    
    <div class="container">

    <div class="container">
        <h2>Editar Usuário (ID: <?= $id ?>)</h2>
        
        <?php if($mensagem) echo "<p style='color:green; font-weight:bold;'>$mensagem</p>"; ?>
        
        <form method="POST">
            <label>E-mail:</label>
            <input type="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>" required>
            
            <label>Senha:</label>
            <input type="text" name="senha" value="<?= htmlspecialchars($usuario['senha']) ?>" required>
            
            <label>Tipo de Usuário:</label>
            <select name="tipo">
                <option value="1" <?= $usuario['tipo'] == 1 ? 'selected' : '' ?>>1 - Administrador</option>
                <option value="2" <?= $usuario['tipo'] == 2 ? 'selected' : '' ?>>2 - Cliente</option>
                <option value="3" <?= $usuario['tipo'] == 3 ? 'selected' : '' ?>>3 - Fornecedor</option>
            </select>
            
            <button type="submit" name="bt_atualizar" class="btn">Salvar Alterações</button>
            <a href="usuarios.php" style="margin-left:10px; color:#666;">Cancelar</a>
        </form>
    </div>
</body>
</html>
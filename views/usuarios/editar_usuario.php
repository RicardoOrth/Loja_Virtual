<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../../config/bootstrap.php";

// Segurança: Somente Admin pode editar usuários
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] != 1) {
    header("Location: " . BASE_URL . "/public/index.php");
    exit;
}


$usuarioDAO = new UsuarioDAO(getDB());
$mensagem = "";

// 1. CARREGAR DADOS ATUAIS
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $usuario = $usuarioDAO->buscarPorId($id);

    if (!$usuario) {
        die("Usuário não encontrado.");
    }
} else {
    header("Location: " . BASE_URL . "/views/usuarios/usuarios.php");
    exit;
}

// 2. PROCESSAR ATUALIZAÇÃO (UPDATE)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bt_atualizar'])) {
    try {
        $email = $_POST['email'];
        $tipo = $_POST['tipo'];

        // Só re-gera o hash se uma nova senha for informada; senão mantém a atual.
        if (!empty($_POST['senha'])) {
            $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
        } else {
            $senha = $usuario['senha'];
        }

        if ($usuarioDAO->atualizar(new Usuario($email, $senha, $tipo, $id))) {
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css?v=<?= filemtime(ROOT_PATH . '/css/style.css') ?>">
    <title>Editar Usuário</title>
</head>
<body>
    <?php include ROOT_PATH . "/views/layouts/header.php"; ?>
    
    <div class="container">

    <div class="container">
        <h2>Editar Usuário (ID: <?= $id ?>)</h2>
        
        <?php if($mensagem) echo "<p style='color:green; font-weight:bold;'>$mensagem</p>"; ?>
        
        <form method="POST">
            <label>E-mail:</label>
            <input type="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>" required>
            
            <label>Senha: <small>(deixe em branco para manter a atual)</small></label>
            <input type="password" name="senha" placeholder="Nova senha (opcional)">
            
            <label>Tipo de Usuário:</label>
            <select name="tipo">
                <option value="1" <?= $usuario['tipo'] == 1 ? 'selected' : '' ?>>1 - Administrador</option>
                <option value="2" <?= $usuario['tipo'] == 2 ? 'selected' : '' ?>>2 - Cliente</option>
                <option value="3" <?= $usuario['tipo'] == 3 ? 'selected' : '' ?>>3 - Fornecedor</option>
            </select>
            
            <button type="submit" name="bt_atualizar" class="btn">Salvar Alterações</button>
            <a href="<?= BASE_URL ?>/views/usuarios/usuarios.php" style="margin-left:10px; color:#666;">Cancelar</a>
        </form>
    </div>
</body>
</html>

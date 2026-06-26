<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../../config/bootstrap.php";

// Segurança: Apenas ADMIN (Tipo 1) acessa esta página
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] != 1) {
    header("Location: " . BASE_URL . "/public/index.php");
    exit;
}


$usuarioDAO = new UsuarioDAO(getDB());
$mensagem = "";

// Lógica de Cadastro (Inclusão)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bt_cadastrar'])) {
    try {
        $senhaHash = password_hash($_POST['senha'], PASSWORD_DEFAULT);
        $usuario = new Usuario($_POST['email'], $senhaHash, $_POST['tipo']);
        if ($usuarioDAO->inserir($usuario)) {
            $mensagem = "Usuário cadastrado com sucesso!";
        }
    } catch (Exception $e) {
        $mensagem = "Erro: " . $e->getMessage();
    }
}

// Lógica de Consulta (Por Nome/Email ou Código)
$busca = $_GET['search'] ?? "";
$listaUsuarios = $usuarioDAO->listar($busca);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css?v=<?= filemtime(ROOT_PATH . '/css/style.css') ?>">
    <title>Gestão de Usuários</title>
</head>
<body>
    <?php include ROOT_PATH . "/views/layouts/header.php"; ?>
    
    <div class="container">

    <div class="container">
        <h2>Novo Usuário</h2>
        <?php if($mensagem) echo "<p style='color:blue'>$mensagem</p>"; ?>
        
        <form method="POST">
            <input type="email" name="email" placeholder="E-mail" required>
            <input type="password" name="senha" placeholder="Senha" required>
            <select name="tipo">
                <option value="1">1 - Administrador</option>
                <option value="2">2 - Cliente</option>
                <option value="3">3 - Fornecedor</option>
            </select>
            <button type="submit" name="bt_cadastrar" class="btn">Cadastrar Usuário</button>
        </form>

        <hr>

        <h2>Lista de Usuários</h2>
        <form method="GET" style="display: flex; gap: 10px;">
            <input type="text" name="search" placeholder="Buscar por e-mail ou ID..." value="<?= htmlspecialchars($busca) ?>">
            <button type="submit" class="btn">Consultar</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>E-mail</th>
                    <th>Tipo</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                 <?php foreach($listaUsuarios as $user): ?>
                  <tr>
                    <td><?= $user['usuario_id'] ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                     <td>
                     <?php 
                     if($user['tipo'] == 1) echo "Admin";
                       elseif($user['tipo'] == 2) echo "Cliente";
                       else echo "Fornecedor";
                     ?>
                     </td>
                    <td style="white-space: nowrap;">
                     <a href="<?= BASE_URL ?>/views/usuarios/editar_usuario.php?id=<?= $user['usuario_id'] ?>" class="btn-edit">Editar</a>
            
                      <a href="<?= BASE_URL ?>/views/usuarios/excluir_usuario.php?id=<?= $user['usuario_id'] ?>" 
                       class="btn-del" 
                       onclick="return confirm('Deseja realmente excluir este usuário?')">
                      Excluir
                     </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        </table>
    </div>
</body>
</html>

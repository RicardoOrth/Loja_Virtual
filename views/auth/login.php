<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../../config/bootstrap.php";

// Destino após o login. Aceita ?redirect= (ex.: voltar para a finalização do
// pedido - US05), mas só dentro da própria aplicação, para evitar open redirect.
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';
$destino  = (is_string($redirect) && strpos($redirect, BASE_URL . "/") === 0)
    ? $redirect
    : BASE_URL . "/views/dashboard/dashboard.php";

// Se já estiver logado, vai direto para o destino.
if (isset($_SESSION['usuario_id'])) {
    header("Location: " . $destino);
    exit;
}

$erro = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuarioDAO = new UsuarioDAO(getDB());

    $email = $_POST['email'];
    $senha_digitada = $_POST['senha'];

    // 1. Buscamos o usuário apenas pelo e-mail (via DAO)
    $user = $usuarioDAO->buscarPorEmail($email);

    // 2. Verificamos se o usuário existe E se o hash da senha bate
    // password_verify compara a senha digitada com o hash salvo no banco
    if ($user && password_verify($senha_digitada, $user['senha'])) {
        $_SESSION['usuario_id'] = $user['usuario_id'];
        $_SESSION['usuario_email'] = $user['email'];
        $_SESSION['usuario_tipo'] = $user['tipo'];

        header("Location: " . $destino);
        exit;
    } else {
        $erro = "E-mail ou senha incorretos.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TechStore</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css?v=<?= filemtime(ROOT_PATH . '/css/style.css') ?>">
    <style>
        .login-container {
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #263238;
        }
        .login-box {
            background: white;
            padding: 40px;
            border-radius: 8px;
            width: 100%;
            max-width: 350px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        }
        .login-box h2 {
            text-align: center;
            margin-bottom: 25px;
            color: #333;
        }
        .error {
            color: #d32f2f;
            background: #ffcdd2;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 14px;
            text-align: center;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #1e88e5;
            text-decoration: none;
            font-size: 14px;
        }
    </style>
</head>
<body class="login-container">

    <div class="login-box">
        <h2>Acessar Conta</h2>

        <?php if ($erro): ?>
            <div class="error"><?= $erro ?></div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/views/auth/login.php" method="POST">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
            <label>E-mail</label>
            <input type="email" name="email" required placeholder="exemplo@ucs.com">
            
            <label>Senha</label>
            <input type="password" name="senha" required placeholder="******">
            
            <button type="submit" class="btn" style="width: 100%; margin-top: 10px;">Entrar</button>
        </form>

        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">
        <p style="text-align: center; font-size: 14px;">Não tem uma conta?</p>
       <a href="<?= BASE_URL ?>/views/auth/cadastro.php" class="btn-secondary" style="display: block; text-align: center; text-decoration: none; background: #f5f5f5; color: #333; padding: 10px; border-radius: 4px; border: 1px solid #ccc; font-size: 14px;">Cadastrar-se</a>

       <a href="<?= BASE_URL ?>/public/index.php" class="back-link">← Voltar para a Loja</a>
    </div>

</body>
</html>

<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once "config/Database.php";

// Se já estiver logado, manda para o dashboard
if (isset($_SESSION['usuario_id'])) {
    header("Location: dashboard.php");
    exit;
}

$erro = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    $email = $_POST['email'];
    $senha_digitada = $_POST['senha'];

    // 1. Buscamos o usuário apenas pelo e-mail
    $sql = "SELECT * FROM USUARIO WHERE EMAIL = :email LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":email", $email);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Verificamos se o usuário existe E se o hash da senha bate
    // password_verify compara a senha digitada com o hash salvo no banco
    if ($user && password_verify($senha_digitada, $user['senha'])) {
        $_SESSION['usuario_id'] = $user['usuario_id'];
        $_SESSION['usuario_email'] = $user['email'];
        $_SESSION['usuario_tipo'] = $user['tipo']; 

        header("Location: dashboard.php");
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
    <title>Login - TechStore</title>
    <link rel="stylesheet" href="css/style.css">
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

        <form action="login.php" method="POST">
            <label>E-mail</label>
            <input type="email" name="email" required placeholder="exemplo@ucs.com">
            
            <label>Senha</label>
            <input type="password" name="senha" required placeholder="******">
            
            <button type="submit" class="btn" style="width: 100%; margin-top: 10px;">Entrar</button>
        </form>

        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">
        <p style="text-align: center; font-size: 14px;">Não tem uma conta?</p>
       <a href="cadastro.php" class="btn-secondary" style="display: block; text-align: center; text-decoration: none; background: #f5f5f5; color: #333; padding: 10px; border-radius: 4px; border: 1px solid #ccc; font-size: 14px;">Cadastrar-se</a>

       <a href="index.php" class="back-link">← Voltar para a Loja</a>
    </div>

</body>
</html>
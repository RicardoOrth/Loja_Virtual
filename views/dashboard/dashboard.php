<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../../config/bootstrap.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: " . BASE_URL . "/views/auth/login.php");
    exit;
}


$usuario_email = $_SESSION['usuario_email'];
$usuario_tipo = $_SESSION['usuario_tipo']; // 1. ADMIN, 2. CLIENTE, 3. FORNECEDOR
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Controle - TechStore</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css?v=<?= filemtime(ROOT_PATH . '/css/style.css') ?>">
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .card-menu {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-decoration: none;
            color: #333;
            border: 1px solid #eee;
            transition: 0.3s;
        }
        .card-menu:hover {
            background: #e3f2fd;
            border-color: #2196f3;
        }
        .card-menu .card-icon {
            font-size: 32px;
            color: #1e88e5;
            margin-bottom: 12px;
        }
        .welcome-banner {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 5px solid #1e88e5;
        }
    </style>
</head>
<body>

    <?php include ROOT_PATH . "/views/layouts/header.php"; ?>
    
    <div class="container">
        <div class="welcome-banner">
            <h2>Bem-vindo, <?= htmlspecialchars($usuario_email) ?>!</h2>
            <p>Você está logado como: <strong>
                <?php 
                    if($usuario_tipo == 1) echo "Administrador";
                    elseif($usuario_tipo == 2) echo "Cliente";
                    else echo "Fornecedor";
                ?>
            </strong></p>
        </div>

        <div class="dashboard-grid">
            
            <?php if($usuario_tipo == 1): ?>
                <a href="<?= BASE_URL ?>/views/usuarios/usuarios.php" class="card-menu">
                    <div class="card-icon"><i class="fa-solid fa-users-gear"></i></div>
                    <h3>Gestão de Usuários</h3>
                    <p>Cadastrar e gerenciar contas</p>
                </a>
                <a href="<?= BASE_URL ?>/views/clientes/clientes.php" class="card-menu">
                    <div class="card-icon"><i class="fa-solid fa-user-group"></i></div>
                    <h3>Gestão de Clientes</h3>
                    <p>Cadastrar e listar clientes</p>
                </a>
                <a href="<?= BASE_URL ?>/views/fornecedores/fornecedores.php" class="card-menu">
                    <div class="card-icon"><i class="fa-solid fa-truck-field"></i></div>
                    <h3>Gestão de Fornecedores</h3>
                    <p>Cadastrar e listar parceiros</p>
                </a>
                <a href="<?= BASE_URL ?>/views/produtos/produtos.php" class="card-menu">
                    <div class="card-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                    <h3>Gestão de Produtos</h3>
                    <p>Controle de estoque e preços</p>
                </a>
                <a href="<?= BASE_URL ?>/views/pedidos/pedidos.php" class="card-menu">
                    <div class="card-icon"><i class="fa-solid fa-receipt"></i></div>
                    <h3>Pedidos</h3>
                    <p>Consultar pedidos e itens</p>
                </a>
            <?php endif; ?>

            <?php if($usuario_tipo == 2): ?>
                <a href="<?= BASE_URL ?>/views/pedidos/pedidos.php" class="card-menu">
                    <div class="card-icon"><i class="fa-solid fa-bag-shopping"></i></div>
                    <h3>Meus Pedidos</h3>
                    <p>Acompanhe suas compras</p>
                </a>
                <a href="perfil.php" class="card-menu">
                    <div class="card-icon"><i class="fa-solid fa-id-card"></i></div>
                    <h3>Meu Perfil</h3>
                    <p>Editar endereço e dados</p>
                </a>
            <?php endif; ?>

            <?php if($usuario_tipo == 3): ?>
                <a href="<?= BASE_URL ?>/views/produtos/produtos.php" class="card-menu">
                    <div class="card-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                    <h3>Meus Produtos</h3>
                    <p>Cadastrar e gerenciar seus itens</p>
                </a>
                <a href="<?= BASE_URL ?>/views/pedidos/pedidos.php" class="card-menu">
                    <div class="card-icon"><i class="fa-solid fa-chart-line"></i></div>
                    <h3>Vendas</h3>
                    <p>Pedidos recebidos dos clientes</p>
                </a>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/public/index.php" class="card-menu">
                <div class="card-icon"><i class="fa-solid fa-store"></i></div>
                <h3>Ir para a Loja</h3>
                <p>Ver produtos disponíveis</p>
            </a>

        </div>
    </div>

</body>
</html>

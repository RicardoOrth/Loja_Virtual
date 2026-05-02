<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

require_once "config/Database.php";

$usuario_email = $_SESSION['usuario_email'];
$usuario_tipo = $_SESSION['usuario_tipo']; // 1. ADMIN, 2. CLIENTE, 3. FORNECEDOR
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel de Controle - TechStore</title>
    <link rel="stylesheet" href="css/style.css">
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

    <?php include "header.php"; ?>
    
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
                <a href="fornecedores.php" class="card-menu">
                    <h3>Gestão de Fornecedores</h3>
                    <p>Cadastrar e listar parceiros</p>
                </a>
                <a href="produtos.php" class="card-menu">
                    <h3>Gestão de Produtos</h3>
                    <p>Controle de estoque e preços</p>
                </a>
            <?php endif; ?>

            <?php if($usuario_tipo == 2): ?>
                <a href="meus_pedidos.php" class="card-menu">
                    <h3>Meus Pedidos</h3>
                    <p>Acompanhe suas compras</p>
                </a>
                <a href="perfil.php" class="card-menu">
                    <h3>Meu Perfil</h3>
                    <p>Editar endereço e dados</p>
                </a>
            <?php endif; ?>

            <?php if($usuario_tipo == 3): ?>
                <a href="produtos.php" class="card-menu">
                    <h3>Meus Produtos</h3>
                    <p>Cadastrar e gerenciar seus itens</p>
                </a>
                <a href="vendas_recebidas.php" class="card-menu">
                    <h3>Vendas</h3>
                    <p>Pedidos recebidos dos clientes</p>
                </a>
            <?php endif; ?>

            <a href="index.php" class="card-menu">
                <h3>Ir para a Loja</h3>
                <p>Ver produtos disponíveis</p>
            </a>

            <a href="logout.php" class="card-menu" style="border-color: #ffcdd2;">
                <h3 style="color: #d32f2f;">Sair</h3>
                <p>Encerrar sessão</p>
            </a>

        </div>
    </div>

</body>
</html>
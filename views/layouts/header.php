<?php
    require_once __DIR__ . "/../../config/bootstrap.php";
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<header>
    <div class="header-container">
        <a href="<?= BASE_URL ?>/public/index.php" class="logo"><i class="fa-solid fa-store"></i> TechStore</a>
        <nav>
            <?php $qtdCarrinho = class_exists('Carrinho') ? Carrinho::quantidadeTotal() : 0; ?>
            <?php if (isset($_SESSION['usuario_id'])): ?>
                <a href="<?= BASE_URL ?>/views/dashboard/dashboard.php">
                    <i class="fa-solid fa-table-columns"></i> Painel
                </a>

                <span class="user-greeting">
                    Olá, <?= $_SESSION['usuario_email'] ?>
                </span>

                <a href="<?= BASE_URL ?>/views/carrinho/carrinho.php" class="cart-link" title="Meu carrinho">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span class="cart-badge" id="cart-badge"<?= $qtdCarrinho > 0 ? '' : ' style="display:none;"' ?>><?= $qtdCarrinho ?></span>
                </a>

                <a href="<?= BASE_URL ?>/views/auth/logout.php" class="btn-sair">
                    <i class="fa-solid fa-right-from-bracket"></i> Sair
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/views/carrinho/carrinho.php" class="cart-link" title="Meu carrinho">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span class="cart-badge" id="cart-badge"<?= $qtdCarrinho > 0 ? '' : ' style="display:none;"' ?>><?= $qtdCarrinho ?></span>
                </a>

                <a href="<?= BASE_URL ?>/views/auth/login.php" class="btn-login">
                    <i class="fa-solid fa-right-to-bracket"></i> Entrar
                </a>
            <?php endif; ?>
        </nav>
    </div>
</header>
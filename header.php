<header>
    <div class="header-container">
        <a href="index.php" class="logo">🚀 TechStore</a>
        <nav>
            <?php if (isset($_SESSION['usuario_id'])): ?>
                <a href="dashboard.php">📊 Minha Área</a>
                <span class="user-greeting">Olá, <?= $_SESSION['usuario_email'] ?></span>
                <a href="logout.php" class="btn-sair">Sair</a>
            <?php else: ?>
                <a href="login.php" class="btn-login">Entrar</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
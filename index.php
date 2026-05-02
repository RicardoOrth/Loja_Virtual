<?php
session_start();
require_once "config/Database.php";
require_once "classes/Produto.php";

$db = (new Database())->getConnection();
$prodObj = new Produto($db);

// Busca os produtos para exibir na vitrine
$listaProdutos = $prodObj->consultar(""); 
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Minha Loja Virtual</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <?php include "header.php"; ?>
    
    <div class="container">

    <section class="container">
        <h2>Confira nossos produtos</h2>
        <hr>
        
        <div class="vitrine">
            <?php while($p = $listaProdutos->fetch(PDO::FETCH_ASSOC)): ?>
                <div class="produto-card">
                    <div style="background:#f0f0f0; height:150px; border-radius:4px; display:flex; align-items:center; justify-content:center; color:#ccc;">
                        [Sem Imagem]
                    </div>
                    <h3><?= htmlspecialchars($p['nome']) ?></h3>
                    <p class="fornecedor-tag">Fornecedor: <?= htmlspecialchars($p['fornecedor_nome']) ?></p>
                    <p class="preco">R$ <?= number_format($p['preco'], 2, ',', '.') ?></p>
                    <button class="btn" style="width:100%">Adicionar ao Carrinho</button>
                </div>
            <?php endwhile; ?>

            <?php if($listaProdutos->rowCount() == 0): ?>
                <p>Nenhum produto cadastrado no momento.</p>
            <?php endif; ?>
        </div>
    </section>

</body>
</html>
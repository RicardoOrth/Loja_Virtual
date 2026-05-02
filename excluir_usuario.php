<?php
session_start();
require_once "config/Database.php";

if (isset($_GET['id'])) {
    $db = (new Database())->getConnection();
    $id = $_GET['id'];

    try {
        $db->beginTransaction();

        // 1. Buscar os IDs de usuário e endereço vinculados a este fornecedor
        $sqlBusca = "SELECT usuario_id, endereco_id FROM FORNECEDOR WHERE fornecedor_id = ?";
        $stmtBusca = $db->prepare($sqlBusca);
        $stmtBusca->execute([$id]);
        $f = $stmtBusca->fetch(PDO::FETCH_ASSOC);

        if ($f) {
            $user_id = $f['usuario_id'];

            $stmt1 = $db->prepare("DELETE FROM FORNECEDOR WHERE fornecedor_id = ?");
            $stmt1->execute([$id]);

            $stmt2 = $db->prepare("DELETE FROM USUARIO WHERE usuario_id = ?");
            $stmt2->execute([$user_id]);

            $db->commit();
            header("Location: fornecedores.php?msg=sucesso");
        } else {
            header("Location: fornecedores.php?msg=nao_encontrado");
        }

    } catch (Exception $e) {
        $db->rollBack();
        // Se o erro persistir, pode ser que este fornecedor tenha PRODUTOS vinculados a ele!
        echo "<h3>Erro de Integridade</h3>";
        echo "Não é possível excluir este fornecedor pois ele possui <b>Produtos</b> cadastrados.";
        echo "<br>Remova os produtos deste fornecedor primeiro.";
        echo "<br><br><a href='fornecedores.php'>Voltar</a>";
    }
}
<?php
session_start();

require_once __DIR__ . "/../../config/bootstrap.php";

// Segurança: apenas ADMIN (Tipo 1) pode excluir usuários
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] != 1) {
    header("Location: " . BASE_URL . "/public/index.php");
    exit;
}


if (isset($_GET['id'])) {
    $db = getDB();
    $usuarioDAO = new UsuarioDAO($db);
    $id = $_GET['id'];

    // Impede o admin de excluir a própria conta logada
    if ($id == $_SESSION['usuario_id']) {
        header("Location: " . BASE_URL . "/views/usuarios/usuarios.php?msg=auto_exclusao");
        exit;
    }

    try {
        $db->beginTransaction();
        $usuarioDAO->excluirComDependencias($id);
        $db->commit();
        header("Location: " . BASE_URL . "/views/usuarios/usuarios.php?msg=sucesso");
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        // Geralmente ocorre quando o usuário é um Fornecedor com produtos/pedidos vinculados
        echo "<h3>Não foi possível excluir o usuário</h3>";
        echo "<p>Provavelmente há registros vinculados (ex.: produtos de um fornecedor ou pedidos de um cliente).</p>";
        echo "<p>Remova esses registros primeiro e tente novamente.</p>";
        echo "<a href='<?= BASE_URL ?>/views/usuarios/usuarios.php'>Voltar</a>";
    }
} else {
    header("Location: " . BASE_URL . "/views/usuarios/usuarios.php");
    exit;
}

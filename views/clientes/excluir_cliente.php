<?php
session_start();

require_once __DIR__ . "/../../config/bootstrap.php";

// Segurança: apenas ADMIN (tipo 1)
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] != 1) {
    header("Location: " . BASE_URL . "/public/index.php");
    exit;
}


if (isset($_GET['id'])) {
    $db = getDB();
    $clienteDAO  = new ClienteDAO($db);
    $usuarioDAO  = new UsuarioDAO($db);
    $enderecoDAO = new EnderecoDAO($db);
    $id = $_GET['id'];

    try {
        $db->beginTransaction();

        $v = $clienteDAO->buscarVinculos($id);
        if ($v) {
            $clienteDAO->excluir($id);
            $usuarioDAO->excluir($v['usuario_id']);
            $enderecoDAO->excluir($v['endereco_id']);
            $db->commit();
            header("Location: " . BASE_URL . "/views/clientes/clientes.php?msg=sucesso");
            exit;
        } else {
            $db->rollBack();
            header("Location: " . BASE_URL . "/views/clientes/clientes.php?msg=nao_encontrado");
            exit;
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        // Normalmente ocorre quando o cliente possui pedidos vinculados
        echo "<h3>Não foi possível excluir o cliente</h3>";
        echo "<p>Provavelmente este cliente possui <b>pedidos</b> cadastrados.</p>";
        echo "<a href='<?= BASE_URL ?>/views/clientes/clientes.php'>Voltar</a>";
    }
} else {
    header("Location: " . BASE_URL . "/views/clientes/clientes.php");
    exit;
}

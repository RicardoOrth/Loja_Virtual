<?php
session_start();
require_once __DIR__ . "/../../config/bootstrap.php";

if (isset($_GET['id'])) {
    $db = getDB();
    $fornecedorDAO = new FornecedorDAO($db);
    $usuarioDAO    = new UsuarioDAO($db);
    $enderecoDAO   = new EnderecoDAO($db);
    $id = $_GET['id'];

    try {
        $db->beginTransaction();

        // 1. Buscar os IDs de usuário e endereço vinculados a este fornecedor
        $f = $fornecedorDAO->buscarVinculos($id);

        if ($f) {
            $user_id = $f['usuario_id'];
            $end_id = $f['endereco_id'];

            $fornecedorDAO->excluir($id);

            // 3. DELETAR OS PAIS (USUARIO e ENDERECO)
            $usuarioDAO->excluir($user_id);
            $enderecoDAO->excluir($end_id);

            $db->commit();
            header("Location: " . BASE_URL . "/views/fornecedores/fornecedores.php?msg=sucesso");
        } else {
            header("Location: " . BASE_URL . "/views/fornecedores/fornecedores.php?msg=nao_encontrado");
        }

    } catch (Exception $e) {
        $db->rollBack();
        // Se o erro persistir, pode ser que este fornecedor tenha PRODUTOS vinculados a ele!
        echo "<h3>Erro de Integridade</h3>";
        echo "Não é possível excluir este fornecedor pois ele possui <b>Produtos</b> cadastrados.";
        echo "<br>Remova os produtos deste fornecedor primeiro.";
        echo "<br><br><a href='<?= BASE_URL ?>/views/fornecedores/fornecedores.php'>Voltar</a>";
    }
}
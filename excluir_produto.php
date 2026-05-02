<?php
session_start();
require_once "config/Database.php";
if (isset($_GET['id'])) {
    $db = (new Database())->getConnection();
    try {
        $db->beginTransaction();
        // Remove estoque primeiro
        $db->prepare("DELETE FROM ESTOQUE WHERE produto_id = ?")->execute([$_GET['id']]);
        // Remove produto
        $db->prepare("DELETE FROM PRODUTO WHERE produto_id = ?")->execute([$_GET['id']]);
        $db->commit();
    } catch (Exception $e) { $db->rollBack(); }
}
header("Location: produtos.php");
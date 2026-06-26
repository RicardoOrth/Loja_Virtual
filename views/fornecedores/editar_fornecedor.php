<?php
session_start();
require_once __DIR__ . "/../../config/bootstrap.php";

if (!isset($_SESSION['usuario_id'])) { header("Location: " . BASE_URL . "/public/index.php"); exit; }


$db = getDB();
$usuarioDAO    = new UsuarioDAO($db);
$enderecoDAO   = new EnderecoDAO($db);
$fornecedorDAO = new FornecedorDAO($db);
$mensagem = "";

// 1. Busca os dados atuais (via DAO)
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $dados = $fornecedorDAO->buscarCompletoPorId($id);
    if (!$dados) { die("Fornecedor não encontrado."); }
}

// 2. Processa o Update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();

        // Update Usuário (email e, opcionalmente, nova senha com hash)
        if (!empty($_POST['senha'])) {
            $novaSenha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
        } else {
            $novaSenha = $dados['senha']; // mantém o hash atual
        }
        $usuarioDAO->atualizarCredenciais($dados['usuario_id'], $_POST['email'], $novaSenha);

        // Update Endereço
        $endereco = new Endereco([
            'endereco_id' => $dados['endereco_id'],
            'rua'    => $_POST['rua'],    'numero' => $_POST['numero'],
            'bairro' => $_POST['bairro'], 'cidade' => $_POST['cidade'],
            'estado' => $_POST['estado'], 'cep'    => $_POST['cep']
        ]);
        $enderecoDAO->atualizar($endereco);

        // Update Fornecedor
        $fornecedor = new Fornecedor([
            'fornecedor_id' => $id,
            'nome'      => $_POST['nome'],
            'telefone'  => $_POST['telefone'],
            'descricao' => $_POST['descricao']
        ]);
        $fornecedorDAO->atualizar($fornecedor);

        $db->commit();
        header("Location: " . BASE_URL . "/views/fornecedores/fornecedores.php?msg=sucesso");
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        $mensagem = "Erro ao atualizar: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css?v=<?= filemtime(ROOT_PATH . '/css/style.css') ?>">
    <title>Editar Fornecedor</title>
</head>
<body>
    <?php include ROOT_PATH . "/views/layouts/header.php"; ?>
    
    <div class="container">
        
    <div class="container">
        <h2>Editar Fornecedor: <?= htmlspecialchars($dados['nome']) ?></h2>
        <?php if($mensagem) echo "<p style='color:red'>$mensagem</p>"; ?>
        
        <form method="POST">
            <input type="text" name="nome" value="<?= $dados['nome'] ?>" required>
            <input type="email" name="email" value="<?= $dados['email'] ?>" required>
            <input type="password" name="senha" placeholder="Nova senha (deixe em branco para manter)">
            <input type="text" name="telefone" value="<?= $dados['telefone'] ?>">
            <textarea name="descricao"><?= $dados['descricao'] ?></textarea>
            
            <input type="text" name="rua" value="<?= $dados['rua'] ?>" required>
            <input type="text" name="numero" value="<?= $dados['numero'] ?>" style="width:20%">
            <input type="text" name="bairro" value="<?= $dados['bairro'] ?>" style="width:75%">
            <input type="text" name="cidade" value="<?= $dados['cidade'] ?>" required>
            <input type="text" name="estado" value="<?= $dados['estado'] ?>" maxlength="2">
            <input type="text" name="cep" value="<?= $dados['cep'] ?>">
            
            <button type="submit" class="btn-edit" style="width:100%; padding:15px; cursor:pointer">Salvar Alterações</button>
        </form>
    </div>
</body>
</html>

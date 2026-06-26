<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../../config/bootstrap.php";

// Segurança: apenas ADMIN (tipo 1)
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] != 1) {
    header("Location: " . BASE_URL . "/public/index.php");
    exit;
}


$db = getDB();
$usuarioDAO  = new UsuarioDAO($db);
$enderecoDAO = new EnderecoDAO($db);
$clienteDAO  = new ClienteDAO($db);
$mensagem = "";

// Carrega dados atuais
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $dados = $clienteDAO->buscarCompletoPorId($id);
    if (!$dados) { die("Cliente não encontrado."); }
} else {
    header("Location: " . BASE_URL . "/views/clientes/clientes.php");
    exit;
}

// Processa atualização
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();

        // Usuário: email e senha opcional
        if (!empty($_POST['senha'])) {
            $novaSenha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
        } else {
            $novaSenha = $dados['senha'];
        }
        $usuarioDAO->atualizarCredenciais($dados['usuario_id'], $_POST['email'], $novaSenha);

        // Endereço
        $enderecoDAO->atualizar(new Endereco([
            'endereco_id' => $dados['endereco_id'],
            'rua'    => $_POST['rua'],    'numero' => $_POST['numero'],
            'bairro' => $_POST['bairro'], 'cidade' => $_POST['cidade'],
            'estado' => $_POST['estado'], 'cep'    => $_POST['cep']
        ]));

        // Cliente
        $clienteDAO->atualizar(new Cliente([
            'cliente_id'     => $id,
            'nome'           => $_POST['nome'],
            'telefone'       => $_POST['telefone'],
            'cartao_credito' => $_POST['cartao_credito']
        ]));

        $db->commit();
        header("Location: " . BASE_URL . "/views/clientes/clientes.php?msg=sucesso");
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
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
    <title>Editar Cliente</title>
</head>
<body>
    <?php include ROOT_PATH . "/views/layouts/header.php"; ?>

    <div class="container">
        <h2>Editar Cliente: <?= htmlspecialchars($dados['nome']) ?></h2>
        <?php if($mensagem) echo "<p style='color:red'>$mensagem</p>"; ?>

        <form method="POST">
            <h3>Dados Gerais</h3>
            <input type="text" name="nome" value="<?= htmlspecialchars($dados['nome']) ?>" required>
            <input type="email" name="email" value="<?= htmlspecialchars($dados['email']) ?>" required>
            <input type="password" name="senha" placeholder="Nova senha (deixe em branco para manter)">
            <input type="text" name="telefone" value="<?= htmlspecialchars($dados['telefone']) ?>" placeholder="Telefone">
            <input type="text" name="cartao_credito" value="<?= htmlspecialchars($dados['cartao_credito']) ?>" placeholder="Cartão de Crédito">

            <h3>Endereço</h3>
            <input type="text" name="rua" value="<?= htmlspecialchars($dados['rua']) ?>" required>
            <input type="text" name="numero" value="<?= htmlspecialchars($dados['numero']) ?>" style="width:20%">
            <input type="text" name="bairro" value="<?= htmlspecialchars($dados['bairro']) ?>" style="width:75%">
            <input type="text" name="cidade" value="<?= htmlspecialchars($dados['cidade']) ?>" required>
            <input type="text" name="estado" value="<?= htmlspecialchars($dados['estado']) ?>" maxlength="2">
            <input type="text" name="cep" value="<?= htmlspecialchars($dados['cep']) ?>">

            <button type="submit" class="btn-edit" style="width:100%; padding:15px; cursor:pointer">Salvar Alterações</button>
            <br><br>
            <a href="<?= BASE_URL ?>/views/clientes/clientes.php" style="display:block; text-align:center;">Voltar</a>
        </form>
    </div>
</body>
</html>

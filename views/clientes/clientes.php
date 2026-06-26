<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../../config/bootstrap.php";

// Segurança: apenas usuários internos (ADMIN tipo 1) gerenciam clientes
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] != 1) {
    header("Location: " . BASE_URL . "/public/index.php");
    exit;
}


$db = getDB();
$enderecoDAO = new EnderecoDAO($db);
$usuarioDAO  = new UsuarioDAO($db);
$clienteDAO  = new ClienteDAO($db);
$mensagem = "";

// Inclusão de cliente (endereço + usuário tipo 2 + cliente)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bt_cadastrar'])) {
    try {
        $db->beginTransaction();

        $enderecoId = $enderecoDAO->inserir(new Endereco($_POST));

        $senhaHash = password_hash($_POST['senha'], PASSWORD_DEFAULT);
        $usuarioId = $usuarioDAO->inserir(new Usuario($_POST['email'], $senhaHash, 2));

        $clienteDAO->inserir(new Cliente([
            'usuario_id'     => $usuarioId,
            'endereco_id'    => $enderecoId,
            'nome'           => $_POST['nome'],
            'telefone'       => $_POST['telefone'],
            'cartao_credito' => $_POST['cartao_credito']
        ]));

        $db->commit();
        $mensagem = "Cliente cadastrado com sucesso!";
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $mensagem = "Erro ao cadastrar cliente: " . $e->getMessage();
    }
}

// Consulta (por código ou nome)
$busca = $_GET['search'] ?? "";
$lista = $clienteDAO->consultar($busca);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css?v=<?= filemtime(ROOT_PATH . '/css/style.css') ?>">
    <title>Gestão de Clientes</title>
</head>
<body>
    <?php include ROOT_PATH . "/views/layouts/header.php"; ?>

    <div class="container">
        <h2>Cadastrar Cliente</h2>
        <?php if($mensagem) echo "<p style='color:blue'>$mensagem</p>"; ?>

        <form method="POST">
            <h3>Dados Gerais</h3>
            <input type="text" name="nome" placeholder="Nome Completo" required>
            <input type="email" name="email" placeholder="E-mail (Login)" required>
            <input type="password" name="senha" placeholder="Senha" required>
            <input type="text" name="telefone" placeholder="Telefone">
            <input type="text" name="cartao_credito" placeholder="Cartão de Crédito">

            <h3>Endereço</h3>
            <input type="text" name="rua" placeholder="Rua" required>
            <input type="text" name="numero" placeholder="Número" style="width: 20%;">
            <input type="text" name="bairro" placeholder="Bairro" style="width: 78%;">
            <input type="text" name="cidade" placeholder="Cidade" required>
            <input type="text" name="estado" placeholder="Estado (UF)" maxlength="2">
            <input type="text" name="cep" placeholder="CEP">

            <button type="submit" name="bt_cadastrar" class="btn">Salvar Cliente</button>
        </form>

        <hr>
        <h2>Clientes Registados</h2>

        <form method="GET" style="display:flex; gap:10px; margin-bottom:15px;">
            <input type="text" name="search" placeholder="Buscar por nome ou código..." value="<?= htmlspecialchars($busca) ?>">
            <button type="submit" class="btn">Consultar</button>
            <?php if($busca !== ""): ?>
                <a href="<?= BASE_URL ?>/views/clientes/clientes.php" class="btn-secondary" style="padding:10px; text-decoration:none;">Limpar</a>
            <?php endif; ?>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Cód</th>
                    <th>Nome</th>
                    <th>Cidade</th>
                    <th>E-mail</th>
                    <th>Telefone</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($lista as $c): ?>
                <tr>
                    <td><?= $c['cliente_id'] ?></td>
                    <td><?= htmlspecialchars($c['nome']) ?></td>
                    <td><?= htmlspecialchars($c['cidade']) ?></td>
                    <td><?= htmlspecialchars($c['email']) ?></td>
                    <td><?= htmlspecialchars($c['telefone']) ?></td>
                    <td style="white-space: nowrap;">
                        <a href="<?= BASE_URL ?>/views/clientes/editar_cliente.php?id=<?= $c['cliente_id'] ?>" class="btn-edit">Editar</a>
                        <a href="<?= BASE_URL ?>/views/clientes/excluir_cliente.php?id=<?= $c['cliente_id'] ?>"
                           class="btn-del"
                           onclick="return confirm('Deseja realmente remover este cliente?')">Remover</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(count($lista) == 0): ?>
                <tr><td colspan="6">Nenhum cliente encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

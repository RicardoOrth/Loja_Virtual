<?php
session_start();
require_once __DIR__ . "/../../config/bootstrap.php";

if (!isset($_SESSION['usuario_id'])) { header("Location: " . BASE_URL . "/public/index.php"); exit; }


$db = getDB();
$enderecoDAO   = new EnderecoDAO($db);
$usuarioDAO    = new UsuarioDAO($db);
$fornecedorDAO = new FornecedorDAO($db);
$mensagem = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();

        // 1. Endereço
        $enderecoId = $enderecoDAO->inserir(new Endereco($_POST));

        // 2. Usuário (Tipo 3 = Fornecedor) — senha protegida com hash
        $senhaHash = password_hash($_POST['senha'], PASSWORD_DEFAULT);
        $usuarioId = $usuarioDAO->inserir(new Usuario($_POST['email'], $senhaHash, 3));

        // 3. Fornecedor vinculado ao usuário e endereço
        $fornecedor = new Fornecedor([
            'usuario_id'  => $usuarioId,
            'endereco_id' => $enderecoId,
            'nome'        => $_POST['nome'],
            'descricao'   => $_POST['descricao'],
            'telefone'    => $_POST['telefone']
        ]);
        $fornecedorDAO->inserir($fornecedor);

        $db->commit();
        $mensagem = "Fornecedor cadastrado com sucesso!";
    } catch (Exception $e) {
        $db->rollBack();
        $mensagem = "Erro ao cadastrar fornecedor: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css?v=<?= filemtime(ROOT_PATH . '/css/style.css') ?>">
    <title>Gestão de Fornecedores</title>
</head>
<body>
    <?php include ROOT_PATH . "/views/layouts/header.php"; ?>
    
    <div class="container">

    <div class="container">
        <h2>Cadastrar Fornecedor</h2>
        <?php if($mensagem) echo "<p>$mensagem</p>"; ?>
        
        <form method="POST">
            <h3>Dados Gerais</h3>
            <input type="text" name="nome" placeholder="Nome da Empresa" required>
            <input type="email" name="email" placeholder="E-mail (Login)" required>
            <input type="password" name="senha" placeholder="Senha" required>
            <input type="text" name="telefone" placeholder="Telefone">
            <textarea name="descricao" placeholder="Descrição dos serviços"></textarea>

            <h3>Endereço</h3>
            <input type="text" name="rua" placeholder="Rua" required>
            <input type="text" name="numero" placeholder="Número" style="width: 20%;">
            <input type="text" name="bairro" placeholder="Bairro" style="width: 78%;">
            <input type="text" name="cidade" placeholder="Cidade" required>
            <input type="text" name="estado" placeholder="Estado (UF)" maxlength="2">
            <input type="text" name="cep" placeholder="CEP">

            <button type="submit" class="btn">Salvar Fornecedor</button>
        </form>

        <hr>
        <h2>Fornecedores Registados</h2>

        <?php $busca = $_GET['search'] ?? ""; ?>
        <form method="GET" style="display:flex; gap:10px; margin-bottom:15px;">
            <input type="text" name="search" placeholder="Buscar por nome ou código..." value="<?= htmlspecialchars($busca) ?>">
            <button type="submit" class="btn">Consultar</button>
            <?php if($busca !== ""): ?>
                <a href="<?= BASE_URL ?>/views/fornecedores/fornecedores.php" class="btn-secondary" style="padding:10px; text-decoration:none;">Limpar</a>
            <?php endif; ?>
        </form>

        <table>
            <tr>
                <th>Cód</th>
                <th>Nome</th>
                <th>Cidade</th>
                <th>E-mail</th>
                <th>Ações</th>
            </tr>
            <?php
            $lista = $fornecedorDAO->consultar($busca);
            foreach($lista as $f):
            ?>
            <tr>
               <td><?= $f['fornecedor_id'] ?></td>
               <td><?= htmlspecialchars($f['nome']) ?></td>
               <td><?= htmlspecialchars($f['cidade']) ?></td>
               <td><?= htmlspecialchars($f['email']) ?></td>
               <td style="white-space: nowrap;">
                  <a href="<?= BASE_URL ?>/views/fornecedores/editar_fornecedor.php?id=<?= $f['fornecedor_id'] ?>" class="btn-edit">Editar</a>
        
                  <a href="<?= BASE_URL ?>/views/fornecedores/excluir_fornecedor.php?id=<?= $f['fornecedor_id'] ?>" 
                      class="btn-del" 
                      onclick="return confirm('Deseja realmente remover este fornecedor?')">
                   Remover
                </a>
                </td>
            </tr>
           <?php endforeach; ?>
        </table>
    </div>
</body>
</html>

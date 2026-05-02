<?php
session_start();
require_once "config/Database.php";

$mensagem = "";
$tipo_mensagem = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        $db->beginTransaction();

        // 1. Inserir na tabela ENDERECO
        $sqlEnd = "INSERT INTO ENDERECO (CIDADE, ESTADO, RUA, NUMERO, COMPLEMENTO, BAIRRO, CEP) 
                   VALUES (:cidade, :estado, :rua, :numero, :complemento, :bairro, :cep) RETURNING ENDERECO_ID";

        $stmtEnd = $db->prepare($sqlEnd);
        $stmtEnd->execute([
            ":cidade"      => $_POST['cidade'],
            ":estado"      => $_POST['uf'], 
            ":rua"         => $_POST['rua'],
            ":numero"      => $_POST['numero'],
            ":complemento" => $_POST['complemento'] ?? null,
            ":bairro"      => $_POST['bairro'],
            ":cep"         => $_POST['cep']
        ]);
        
        $rowEnd = $stmtEnd->fetch(PDO::FETCH_ASSOC);
        $endereco_id = $rowEnd['endereco_id'] ?? $rowEnd['ENDERECO_ID'];

        // 2. Inserir na tabela USUARIO
        // Antes de inserir no banco, transformamos a senha em Hash
        $senha_plana = $_POST['senha'];
        $senha_hash = password_hash($senha_plana, PASSWORD_DEFAULT);

        $sqlUser = "INSERT INTO USUARIO (EMAIL, SENHA, TIPO) 
                    VALUES (:email, :senha, :tipo) RETURNING usuario_id";
        $stmtUser = $db->prepare($sqlUser);
        $stmtUser->execute([
            ":email" => $_POST['email'],
            ":senha" => $senha_hash,
            ":tipo"  => $_POST['tipo'] // Receberá 2 ou 3
        ]);
        
        $rowUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $usuario_id = $rowUser['usuario_id'] ?? $rowUser['USUARIO_ID'];

        // 3. Inserir na tabela específica baseada no tipo (2 = Cliente, 3 = Fornecedor)
        if ($_POST['tipo'] == 2) { 
            // CLIENTE
            $sqlCli = "INSERT INTO CLIENTE (USUARIO_ID, ENDERECO_ID, NOME, TELEFONE, CARTAO_CREDITO) 
                       VALUES (:uid, :eid, :nome, :tel, :cartao)";
            $stmtCli = $db->prepare($sqlCli);
            $stmtCli->execute([
                ":uid"    => $usuario_id,
                ":eid"    => $endereco_id,
                ":nome"   => $_POST['nome_cliente'] ?? '',
                ":tel"    => $_POST['telefone_cliente'] ?? '',
                ":cartao" => $_POST['cartao_credito'] ?? ''
            ]);  
        } else if ($_POST['tipo'] == 3) { 
            // FORNECEDOR
            $sqlFor = "INSERT INTO FORNECEDOR (USUARIO_ID, ENDERECO_ID, NOME, DESCRICAO, TELEFONE) 
                       VALUES (:uid, :eid, :nome, :desc, :tel)";
            $stmtFor = $db->prepare($sqlFor);
            $stmtFor->execute([
                ":uid"  => $usuario_id,
                ":eid"  => $endereco_id,
                ":nome" => $_POST['nome_fornecedor'] ?? '',
                ":desc" => $_POST['descricao_fornecedor'] ?? '',
                ":tel"  => $_POST['telefone_fornecedor'] ?? ''
            ]);
        }

        $db->commit();
        $mensagem = "Cadastro realizado com sucesso!";
        $tipo_mensagem = "success";

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $mensagem = "Erro ao cadastrar: " . $e->getMessage();
        $tipo_mensagem = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastro - TechStore</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #263238; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 8px; width: 100%; max-width: 500px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .section-title { background: #eceff1; padding: 8px; font-size: 14px; font-weight: bold; color: #546e7a; margin: 20px 0 10px 0; border-radius: 4px; }
        input, select { width: 100%; padding: 10px; margin-bottom: 12px; border: 1px solid #cfd8dc; border-radius: 4px; box-sizing: border-box; }
        .row { display: flex; gap: 10px; }
        .btn-submit { width: 100%; padding: 12px; background: #1e88e5; color: white; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
        .hidden { display: none; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; text-align: center; }
        .success { background: #c8e6c9; color: #2e7d32; }
        .error { background: #ffcdd2; color: #c62828; }
    </style>
</head>
<body>

<div class="container">
    <h2>Criar Conta</h2>

    <?php if ($mensagem): ?>
        <div class="alert <?= $tipo_mensagem ?>"><?= $mensagem ?></div>
    <?php endif; ?>

    <form action="cadastro.php" method="POST">
        <div class="section-title">ACESSO</div>
        <input type="email" name="email" placeholder="E-mail" required>
        <input type="password" name="senha" placeholder="Senha" required>
        
        <label style="font-size: 12px; color: #666;">Selecione o perfil:</label>
        <select name="tipo" id="tipo_usuario" onchange="toggleFields()">
            <option value="2">Cliente</option>
            <option value="3">Fornecedor</option>
        </select>

        <div class="section-title">ENDEREÇO</div>
        <input type="text" name="rua" placeholder="Rua" required>
        <div class="row">
            <input type="text" name="numero" placeholder="Nº" required>
            <input type="text" name="complemento" placeholder="Comp.">
        </div>
        <div class="row">
            <input type="text" name="bairro" placeholder="Bairro" required>
            <input type="text" name="cep" placeholder="CEP" required>
        </div>
        <div class="row">
            <input type="text" name="cidade" placeholder="Cidade" required style="flex:3;">
            <input type="text" name="uf" placeholder="UF" maxlength="2" required style="flex:1;">
        </div>

        <div id="campos_cliente">
            <div class="section-title">DADOS DO CLIENTE</div>
            <input type="text" name="nome_cliente" placeholder="Nome Completo">
            <input type="text" name="telefone_cliente" placeholder="Telefone">
            <input type="text" name="cartao_credito" placeholder="Cartão de Crédito">
        </div>

        <div id="campos_fornecedor" class="hidden">
            <div class="section-title">DADOS DO FORNECEDOR</div>
            <input type="text" name="nome_fornecedor" placeholder="Nome da Empresa">
            <input type="text" name="descricao_fornecedor" placeholder="Descrição do Negócio">
            <input type="text" name="telefone_fornecedor" placeholder="Telefone Comercial">
        </div>

        <button type="submit" class="btn-submit">Finalizar Cadastro</button>
        <a href="login.php" style="display:block; text-align:center; margin-top:15px; font-size:14px; color:#1e88e5; text-decoration:none;">Voltar ao Login</a>
    </form>
</div>

<script>
function toggleFields() {
    const tipo = document.getElementById('tipo_usuario').value;
    const divCli = document.getElementById('campos_cliente');
    const divFor = document.getElementById('campos_fornecedor');
    
    // 2 é Cliente, 3 é Fornecedor
    if (tipo === '2') {
        divCli.classList.remove('hidden');
        divFor.classList.add('hidden');
    } else {
        divCli.classList.add('hidden');
        divFor.classList.remove('hidden');
    }
}
// Inicializa os campos corretamente ao carregar
window.onload = toggleFields;
</script>

</body>
</html>
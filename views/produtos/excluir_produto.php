<?php
session_start();
require_once __DIR__ . "/../../config/bootstrap.php";

if (isset($_GET['id'])) {
    $db = getDB();
    $produtoDAO = new ProdutoDAO($db);
    $estoqueDAO = new EstoqueDAO($db);
    $fornecedorDAO = new FornecedorDAO($db);
    
    try {
        $idProduto = $_GET['id'];

        if (isset($_SESSION['usuario_tipo']) && (int) $_SESSION['usuario_tipo'] === 3) {
            $fornecedorLogado = $fornecedorDAO->buscarPorUsuarioId($_SESSION['usuario_id']);

            if (
                !$fornecedorLogado ||
                !$produtoDAO->pertenceAoFornecedor($idProduto, $fornecedorLogado['fornecedor_id'])
            ) {
                header("Location: " . BASE_URL . "/views/produtos/produtos.php");
                exit;
            }
        }

        // 1. BUSCA TODAS AS IMAGENS DO PRODUTO ANTES DE DELETAR (Para não perder os caminhos)
        // Usamos o método que já criamos no seu ProdutoDAO
        $imagens = $produtoDAO->buscarImagensPorProdutoId($idProduto);

        $db->beginTransaction();
        
        // 2. Remove o estoque primeiro
        $estoqueDAO->excluirPorProduto($idProduto);
        
        // 3. Remove o produto (isso vai disparar o CASCADE no banco e limpar a tabela PRODUTO_IMAGEM)
        $produtoDAO->excluir($idProduto);
        
        $db->commit();

        // 4. EXCLUSÃO FÍSICA: Se o banco limpou com sucesso, apagamos os arquivos da pasta
        foreach ($imagens as $img) {
            $caminhoBanco = $img['caminho']; // Ex: "/public/uploads/produtos/imagem.jpg"
            
            // Monta o caminho absoluto idêntico à lógica da tela de edição
            $arquivoFisico = rtrim(ROOT_PATH, '/') . '/' . ltrim($caminhoBanco, '/');
            
            if (file_exists($arquivoFisico)) {
                unlink($arquivoFisico);
            } else {
                // Plano B caso a string tenha alguma variação de caminho antigo
                $planoB = rtrim(ROOT_PATH, '/') . str_replace('/public', '', $caminhoBanco);
                if (file_exists($planoB)) {
                    unlink($planoB);
                }
            }
        }

    } catch (Exception $e) { 
        $db->rollBack(); 
        // Opcional: tratar erro ou salvar em log se preferir
    }
}

header("Location: " . BASE_URL . "/views/produtos/produtos.php");
exit;

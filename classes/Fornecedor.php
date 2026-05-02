<?php
class Fornecedor {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function cadastrar($dados) {
        try {
            // Inicia a transação
            $this->conn->beginTransaction();

            // 1. Inserir Endereço
            $sqlEnd = "INSERT INTO ENDERECO (CIDADE, ESTADO, RUA, NUMERO, COMPLEMENTO, BAIRRO, CEP) 
                       VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING ENDERECO_ID";
            $stmtEnd = $this->conn->prepare($sqlEnd);
            $stmtEnd->execute([
                $dados['cidade'], $dados['estado'], $dados['rua'], 
                $dados['numero'], $dados['complemento'] ?? '', $dados['bairro'], $dados['cep']
            ]);
            $endereco_id = $stmtEnd->fetch(PDO::FETCH_ASSOC)['endereco_id'];

            // 2. Inserir Usuário (Tipo 3 = Fornecedor)
            $sqlUser = "INSERT INTO USUARIO (EMAIL, SENHA, TIPO) VALUES (?, ?, 3) RETURNING USUARIO_ID";
            $stmtUser = $this->conn->prepare($sqlUser);
            $stmtUser->execute([$dados['email'], $dados['senha']]);
            $usuario_id = $stmtUser->fetch(PDO::FETCH_ASSOC)['usuario_id'];

            // 3. Inserir Fornecedor vinculado ao Endereço e Usuário
            $sqlForn = "INSERT INTO FORNECEDOR (USUARIO_ID, ENDERECO_ID, NOME, DESCRICAO, TELEFONE) 
                        VALUES (?, ?, ?, ?, ?)";
            $stmtForn = $this->conn->prepare($sqlForn);
            $stmtForn->execute([
                $usuario_id, $endereco_id, $dados['nome'], $dados['descricao'], $dados['telefone']
            ]);

            // Confirma todas as alterações
            $this->conn->commit();
            return true;

        } catch (Exception $e) {
             $this->conn->rollBack();
             // ESSA LINHA ABAIXO É O SEU MELHOR AMIGO AGORA:
             echo "ERRO NO POSTGRES: " . $e->getMessage(); 
             exit; // Para a execução para você ler o erro
}
    }

    public function listarTudo() {
        $sql = "SELECT f.*, e.cidade, u.email 
                FROM FORNECEDOR f
                JOIN ENDERECO e ON f.endereco_id = e.endereco_id
                JOIN USUARIO u ON f.usuario_id = u.usuario_id
                ORDER BY f.nome ASC";
        return $this->conn->query($sql);
    }

    public function excluir($id) {
        // Nota: Por conta das chaves estrangeiras, a exclusão deve ser feita 
        // com cuidado ou usando ON DELETE CASCADE no banco.
        $sql = "DELETE FROM FORNECEDOR WHERE FORNECEDOR_ID = ?";
        return $this->conn->prepare($sql)->execute([$id]);
    }
}
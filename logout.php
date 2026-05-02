<?php
// Garante que nenhum erro "Headers already sent" aconteça
ob_start();
session_start();

// Limpa todos os dados da sessão
$_SESSION = array();

// Se desejar matar o cookie da sessão também
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destrói a sessão
session_destroy();

// Redireciona de forma absoluta
header("Location: index.php");
exit;
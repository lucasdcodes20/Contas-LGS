<?php
/**
 * Script de Encerramento de Sessão - contasLGS
 */
session_start();

// Destrói todas as variáveis de sessão
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Redireciona para a página de login
header('Location: login.php');
exit;

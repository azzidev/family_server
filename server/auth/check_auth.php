<?php
    session_start();

    // Verifique se o usuário está autenticado
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
?>
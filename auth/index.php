<?php
    session_start();

    if (!isset($_SESSION['user_id'])) {
        header("Location: src/pages/login.php");
        exit();
    }else{
        header("Location: ../dashboard/index.php");
        exit();
    }
?>
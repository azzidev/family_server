<?php
    date_default_timezone_set('America/Sao_Paulo');
    include __DIR__ .'/../helpers/environment.php';
    loadEnv(__DIR__ . '/../../.env');

    $host = getenv('SQL_HOST');
    $user = getenv('SQL_USER');
    $password = getenv('SQL_PASSWORD');
    $database = getenv('SQL_DATABASE');

    try {
        $conn = new PDO("mysql:host=$host;dbname=$database;charset=utf8", $user, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Configurar o timezone também no MySQL
        $conn->exec("SET time_zone = '-03:00'");
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
?>
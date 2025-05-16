<?php
// Configurar o timezone para America/Sao_Paulo
date_default_timezone_set('America/Sao_Paulo');

$host = 'localhost';
$user = 'root';
$password = '';
$database = 'family_server';

try {
    $conn = new PDO("mysql:host=$host;dbname=$database;charset=utf8", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Configurar o timezone também no MySQL
    $conn->exec("SET time_zone = '-03:00'");
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
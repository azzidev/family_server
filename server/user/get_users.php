<?php
include '../db/connection.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM users WHERE _id = :user_id");
$stmt->bindParam(':user_id', $userId);
$stmt->execute();

$profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($profiles);
?>
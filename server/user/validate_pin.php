<?php
include '../db/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = $_POST['pin'];
    $profileId = $_POST['profile_id'];

    $stmt = $conn->prepare("SELECT pin FROM profiles WHERE id = :profile_id");
    $stmt->bindParam(':profile_id', $profileId);
    $stmt->execute();

    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($profile && $profile['pin'] === $pin) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid PIN']);
    }
}
?>
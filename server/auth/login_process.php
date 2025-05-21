<?php
    session_start();
    include '../db/connection.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'];
        $password = $_POST['password'];

        try {
            $stmt = $conn->prepare("SELECT _id, _hash FROM users WHERE user = :username AND status = 1");
            $stmt->bindParam(':username', $username);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['_hash'])) {
                $_SESSION['user_id'] = $user['_id'];
                header("Location: ../../dashboard/index.php");
                exit();
            } else {
                $_SESSION['error'] = 'invalid_credentials'; // Set error in session
                header("Location: ../../auth/src/pages/login.php?error=invalid_credentials");
                exit();
            }
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }
?>
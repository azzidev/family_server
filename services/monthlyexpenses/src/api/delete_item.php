<?php
    session_start();
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método não permitido']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        echo json_encode(['success' => false, 'error' => 'Usuário não autenticado']);
        exit;
    }

    require_once __DIR__ . '/../cmd/db.php';

    // Deletar por ID único
    if (!empty($data['item_id']) && empty($data['group_id'])) {
        $stmt = $conn->prepare("DELETE FROM gastosmensal_items WHERE _id = :id AND _id_user = :user_id");
        $stmt->bindParam(':id', $data['item_id']);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        echo json_encode(['success' => true, 'deleted' => 'item']);
        exit;
    }

    // Deletar por group_id (parcelas ou assinatura)
    if (!empty($data['group_id']) && !empty($data['item_id'])) {
        // Descobre se é assinatura ou parcela
        $stmt = $conn->prepare("SELECT price FROM gastosmensal_items WHERE _id = :id AND _id_user = :user_id");
        $stmt->bindParam(':id', $data['item_id']);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            echo json_encode(['success' => false, 'error' => 'Item não encontrado']);
            exit;
        }

        $price = json_decode($item['price'], true);

        if (isset($price['installments']) && $price['installments'] == 9999) {
            // ASSINATURA: deleta só o item escolhido e marca o group_id como cancelado
            $stmt = $conn->prepare("DELETE FROM gastosmensal_items WHERE _id = :id AND _id_user = :user_id");
            $stmt->bindParam(':id', $data['item_id']);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();

            // Marca o group_id como cancelado em uma tabela auxiliar
            $stmt = $conn->prepare("INSERT IGNORE INTO gastosmensal_cancelled_signatures (installment_group_id, cancelled_at) VALUES (:gid, NOW())");
            $stmt->bindParam(':gid', $data['group_id']);
            $stmt->execute();

            echo json_encode(['success' => true, 'deleted' => 'signature']);
            exit;
        } else {
            // PARCELAS: deleta todas as parcelas (passadas, presentes e futuras)
            $stmt = $conn->prepare("DELETE FROM gastosmensal_items WHERE JSON_EXTRACT(price, '$.installment_group_id') = :gid AND _id_user = :user_id");
            $stmt->bindParam(':gid', $data['group_id']);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();

            echo json_encode(['success' => true, 'deleted' => 'installments']);
            exit;
        }
    }

    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
?>
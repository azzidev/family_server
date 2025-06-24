<?php
    session_start();
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método não permitido']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (
        empty($data['list']) ||
        empty($data['name']) ||
        !isset($data['price']) ||
        empty($data['date_buy'])
    ) {
        echo json_encode(['success' => false, 'error' => 'Campos obrigatórios não preenchidos']);
        exit;
    }

    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        echo json_encode(['success' => false, 'error' => 'Usuário não autenticado']);
        exit;
    }

    include __DIR__ .'/../cmd/db.php';
    function generateInstallmentGroupId() {
        return base64_encode('parcela_' . uniqid('', true) . '.' . mt_rand());
    }

    $is_signature = !empty($data['is_signature']);
    $is_installment = !empty($data['is_installment']);
    $price = floatval($data['price']);
    $installments = $is_installment ? intval($data['installments']) : null;
    $current_installment = $is_installment ? intval($data['current_installment']) : null;

    $price_json = [
        'price' => $price,
        'total' => $price,
    ];

    if ($is_signature) {
        $installment_group_id = generateInstallmentGroupId();
        $price_json['installments'] = 9999;
        $price_json['current_installment'] = 1;
        $price_json['installment_group_id'] = $installment_group_id;
        $price_json['total'] = $price * 9999;
    } elseif ($is_installment && $installments > 1) {
        $installment_group_id = generateInstallmentGroupId();
        $price_json['installments'] = $installments;
        $price_json['current_installment'] = $current_installment;
        $price_json['installment_group_id'] = $installment_group_id;
        $price_json['total'] = $price * $installments;
    } else {
        $price_json['installments'] = 1;
        $price_json['current_installment'] = 1;
    }

    $now = date('Y-m-d H:i:s');
    $date_json = json_encode([
        'created' => $now,
        'updated' => $now
    ]);
    $price_json = json_encode($price_json);

    $stmt = $conn->prepare("INSERT INTO gastosmensal_items (_id_user, _id_list, name, price, date_buy, date) VALUES (:user_id, :list_id, :name, :price, :date_buy, :date)");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':list_id', $data['list']);
    $stmt->bindParam(':name', $data['name']);
    $stmt->bindParam(':price', $price_json);
    $stmt->bindParam(':date_buy', $data['date_buy']);
    $stmt->bindParam(':date', $date_json);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'item' => [
            'name' => $data['name'],
            'price' => $price_json,
            'date_buy' => $data['date_buy']
        ]]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erro ao salvar no banco']);
    }
?>
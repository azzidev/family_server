<?php
    include __DIR__ .'/../helpers/ping-session.php';

    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método não permitido']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (
        empty($data['name']) ||
        empty($data['type']) ||
        empty($data['start_date']) ||
        empty($data['end_date'])
    ) {
        echo json_encode(['success' => false, 'error' => 'Campos obrigatórios não preenchidos']);
        exit;
    }

    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        echo json_encode(['success' => false, 'error' => 'Usuário não autenticado']);
        exit;
    }

    $type_map = [
        'cost' => ['type' => 'cost', 'name' => 'Custo', 'color' => 'danger'],
        'receivable' => ['type' => 'receivable', 'name' => 'Entrada', 'color' => 'success']
    ];
    $type_json = json_encode($type_map[$data['type']] ?? $type_map['cost']);

    $period_json = json_encode([
        'start' => $data['start_date'] . ' 00:00:00',
        'end' => $data['end_date'] . ' 23:59:59'
    ]);

    $now = date('Y-m-d H:i:s');
    $date_json = json_encode([
        'created' => $now,
        'updated' => $now
    ]);

    include __DIR__ .'/../cmd/db.php';
    $stmt = $conn->prepare("INSERT INTO gastosmensal_lists (_id_user, name, description, type, period, date) VALUES (:user_id, :name, :description, :type, :period, :date)");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':name', $data['name']);
    $stmt->bindParam(':description', $data['description']);
    $stmt->bindParam(':type', $type_json);
    $stmt->bindParam(':period', $period_json);
    $stmt->bindParam(':date', $date_json);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erro ao salvar no banco']);
    }
?>
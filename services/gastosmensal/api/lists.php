<?php
session_start();
include '../../../server/db/connection.php';

// Verifique se o usuário está autenticado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuário não autenticado.']);
    exit();
}

// Obtenha o método HTTP
$method = $_SERVER['REQUEST_METHOD'];

// Processa a requisição
try {
    if ($method === 'GET') {
        // Obtenha os parâmetros de data
        $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
        $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
        
        // Validação dos parâmetros
        if (!$startDate || !$endDate) {
            throw new Exception('Parâmetros de data não fornecidos.');
        }
        
        // Formatar as datas para comparação
        $startDateFormatted = $startDate . ' 00:00:00';
        $endDateFormatted = $endDate . ' 23:59:59';
        
        // Buscar listas que se encaixam no período
        $stmt = $conn->prepare("
            SELECT gl._id, gl.name, gl.description, gl.type, gl.period, gl.date
            FROM gastosmensal_lists gl
            WHERE gl._id_user = :user_id
            AND (
                (JSON_EXTRACT(gl.period, '$.start') >= :start_date AND JSON_EXTRACT(gl.period, '$.start') <= :end_date)
                OR
                (JSON_EXTRACT(gl.period, '$.end') >= :start_date AND JSON_EXTRACT(gl.period, '$.end') <= :end_date)
                OR
                (JSON_EXTRACT(gl.period, '$.start') <= :start_date AND JSON_EXTRACT(gl.period, '$.end') >= :end_date)
            )
            ORDER BY JSON_EXTRACT(gl.period, '$.start') DESC
        ");
        
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':start_date', $startDateFormatted);
        $stmt->bindParam(':end_date', $endDateFormatted);
        $stmt->execute();
        
        $lists = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Processar os resultados
        $processedLists = [];
        foreach ($lists as $list) {
            // Decodificar os campos JSON
            $list['type'] = json_decode($list['type'], true);
            $list['period'] = json_decode($list['period'], true);
            $list['date'] = json_decode($list['date'], true);
            
            $processedLists[] = $list;
        }
        
        // Retornar os resultados
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'lists' => $processedLists
        ]);
    } else if ($method === 'POST') {
        // Criação de uma nova lista
        $data = json_decode(file_get_contents('php://input'), true);

        // Validação dos dados
        if (empty($data['name']) || empty($data['type']) || empty($data['period'])) {
            throw new Exception('Campos obrigatórios não preenchidos.');
        }

        // Insere a lista no banco de dados
        $stmt = $conn->prepare("
            INSERT INTO gastosmensal_lists (_id_user, name, description, type, period, date)
            VALUES (:user_id, :name, :description, :type, :period, :date)
        ");
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':description', $data['description']);
        $temp_type = json_encode($data['type']);
        $period = json_encode($data['period']);
        $now = json_encode(['created' => date('Y-m-d H:i:s'),'updated' => date('Y-m-d H:i:s')]);
        $stmt->bindParam(':type', $temp_type);
        $stmt->bindParam(':period', $period);
        $stmt->bindParam(':date', $now);

        $result = $stmt->execute();
        
        if ($result) {
            // Obter o ID da lista recém-criada
            $listId = $conn->lastInsertId();
            
            // Buscar a lista completa
            $stmt = $conn->prepare("
                SELECT _id, name, description, type, period, date
                FROM gastosmensal_lists
                WHERE _id = :list_id
            ");
            $stmt->bindParam(':list_id', $listId);
            $stmt->execute();
            $list = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($list) {
                // Preparar os dados para retornar
                $list['type'] = json_decode($list['type'], true);
                $list['period'] = json_decode($list['period'], true);
                $list['date'] = json_decode($list['date'], true);
                
                // Definir o cabeçalho Content-Type antes de enviar a resposta
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Lista adicionada com sucesso.',
                    'list' => $list
                ]);
            } else {
                throw new Exception('Erro ao recuperar a lista criada.');
            }
        } else {
            throw new Exception('Erro ao criar lista.');
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido.']);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
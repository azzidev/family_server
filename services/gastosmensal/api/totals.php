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
        // Filtro por período
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;

        if (!$startDate || !$endDate) {
            throw new Exception('Datas de início e fim são obrigatórias.');
        }

        // Formatar as datas para incluir o horário completo
        $startDateFormatted = $startDate . ' 00:00:00';
        $endDateFormatted = $endDate . ' 23:59:59';

        // Busca as listas que se sobrepõem ao período filtrado
        $stmt = $conn->prepare("
            SELECT l._id, l.name, l.type, l.period
            FROM gastosmensal_lists l
            WHERE l._id_user = :user_id
            AND (
                (JSON_EXTRACT(l.period, '$.start') <= :end_date AND JSON_EXTRACT(l.period, '$.end') >= :start_date)
            )
            ORDER BY JSON_EXTRACT(l.period, '$.start') DESC
        ");
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':start_date', $startDateFormatted);
        $stmt->bindParam(':end_date', $endDateFormatted);
        $stmt->execute();
        $lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Gerar HTML para os accordions
        $costsAccordionHtml = '';
        $receivablesAccordionHtml = '';
        $totalCosts = 0;
        $totalReceivables = 0;
        $totalCostsDetails = 0;
        $totalReceivablesDetails = 0;

        foreach ($lists as $list) {
            $listId = $list['_id'];
            $listName = htmlspecialchars($list['name']);
            $listType = json_decode($list['type'], true);
            $listTypeValue = $listType['type'];
            $period = json_decode($list['period'], true);
            $listTotal = 0;

            // Buscar itens da lista no período filtrado
            $stmt = $conn->prepare("
                SELECT price 
                FROM gastosmensal_items 
                WHERE _id_list = :list_id 
                AND _id_user = :user_id 
                AND date_buy BETWEEN :startDate AND :endDate
            ");
            $stmt->bindParam(':list_id', $listId);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':startDate', $startDateFormatted);
            $stmt->bindParam(':endDate', $endDateFormatted);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as $item) {
                $priceData = json_decode($item['price'], true);
                $price = $priceData['price'];
                $listTotal += $price;

                if ($listTypeValue === 'cost') {
                    $totalCosts += $price;
                } else if ($listTypeValue === 'receivable') {
                    $totalReceivables += $price;
                }
            }

            // Adicionar ao HTML do accordion apropriado se houver itens
            if ($listTotal > 0) {
                $accordionHtml = '<div class="card mb-3">';
                $accordionHtml .= '<div class="card-header">';
                $accordionHtml .= '<p class="card-text text-end mb-0">';
                $accordionHtml .= '<strong>Total da Lista: R$ ' . number_format($listTotal, 2, ',', '.') . '</strong>';
                $accordionHtml .= '</p>';
                $accordionHtml .= '</div>';
                $accordionHtml .= '<div class="card-body">';
                $accordionHtml .= '<h5 class="card-title">' . $listName . '</h5>';
                $accordionHtml .= '<p class="card-text">';
                $accordionHtml .= 'Período: ' . date("d/m/Y", strtotime($period['start'])) . ' até ' . date("d/m/Y", strtotime($period['end']));
                $accordionHtml .= '</p>';
                $accordionHtml .= '</div>';
                $accordionHtml .= '</div>';

                if ($listTypeValue === 'cost') {
                    $costsAccordionHtml .= $accordionHtml;
                    $totalCostsDetails += $listTotal;
                } else if ($listTypeValue === 'receivable') {
                    $receivablesAccordionHtml .= $accordionHtml;
                    $totalReceivablesDetails += $listTotal;
                }
            }
        }

        // Adicionar totais aos accordions
        $costsAccordionHtml .= '<div class="text-end mt-3">';
        $costsAccordionHtml .= '<h5><strong>Total de Custos: R$ ' . number_format($totalCostsDetails, 2, ',', '.') . '</strong></h5>';
        $costsAccordionHtml .= '</div>';

        $receivablesAccordionHtml .= '<div class="text-end mt-3">';
        $receivablesAccordionHtml .= '<h5><strong>Total de Entradas: R$ ' . number_format($totalReceivablesDetails, 2, ',', '.') . '</strong></h5>';
        $receivablesAccordionHtml .= '</div>';

        $balance = $totalReceivables - $totalCosts;

        // Retorna os totais e o HTML dos accordions
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'totals' => [
                'costs' => $totalCosts,
                'receivables' => $totalReceivables,
                'balance' => $balance
            ],
            'accordions' => [
                'costs' => $costsAccordionHtml,
                'receivables' => $receivablesAccordionHtml
            ]
        ]);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido.']);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
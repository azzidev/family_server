<?php
session_start();
include '../../../server/db/connection.php';
include '../../../root/cmd/config.php';

// Verifique se o usuário está autenticado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuário não autenticado.']);
    exit();
}
$config = getConfig();

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
            SELECT l._id, l.name, l.type, l.period, l.date
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

        // Organiza os dados para as listas e o extrato
        $listsHtml = '';
        $statementHtml = '';
        $processedLists = [];
        $allItems = [];

        // Para cada lista, buscar os itens no período filtrado
        foreach ($lists as $list) {
            $listId = $list['_id'];
            $listName = htmlspecialchars($list['name']);
            $listType = json_decode($list['type'], true);
            $listPeriod = json_decode($list['period'], true);
            $listDate = json_decode($list['date'], true);

            // Buscar todos os itens da lista, sem filtro de data
            $stmt = $conn->prepare("
                SELECT _id, name, price, date_buy
                FROM gastosmensal_items
                WHERE _id_list = :list_id
                AND _id_user = :user_id
                ORDER BY date_buy DESC
            ");
            $stmt->bindParam(':list_id', $listId);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Adicionar a lista e seus itens ao array processado
            $processedItems = [];
            foreach ($items as $item) {
                $processedItems[] = [
                    '_id' => $item['_id'],
                    'name' => htmlspecialchars($item['name']),
                    'price' => json_decode($item['price'], true),
                    'date_buy' => $item['date_buy']
                ];

                // Adicionar ao array de todos os itens para o extrato
                $allItems[] = [
                    '_id' => $item['_id'],
                    'name' => htmlspecialchars($item['name']),
                    'list_name' => $listName,
                    'list_type' => $listType,
                    'price' => json_decode($item['price'], true),
                    'date_buy' => $item['date_buy']
                ];
            }

            $processedLists[$listId] = [
                'name' => $listName,
                'type' => $listType,
                'period' => $listPeriod,
                'date' => $listDate,
                'items' => $processedItems
            ];
        }

        // Gera o HTML das listas
        $listsHtml .= '<div class="row">';
        foreach ($processedLists as $list) {
            $listTotal = 0;
        
            $listsHtml .= '<div class="col-md-6 col-sm-12 d-flex align-items-stretch">';
            $listsHtml .= '<div class="card mb-3 flex-fill">';
            $listsHtml .= '<div class="card-header">';
            
            // Configurar locale para português
            setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese');
            
            // Determinar o mês predominante em português
            $startMonth = strftime('%b', strtotime($list['period']['start']));
            $endMonth = strftime('%b', strtotime($list['period']['end']));
            $monthBadge = ucfirst($startMonth);
            if ($startMonth != $endMonth) {
                $monthBadge = ucfirst($startMonth) . '-' . ucfirst($endMonth);
            }
            
            $listsHtml .= '<h5 class="card-title">';
            $listsHtml .= htmlspecialchars($list['name']);
            $listsHtml .= '<span class="badge bg-' . $list['type']['color'] . ' float-end ms-1">' . $list['type']["name"] . '</span>';
            $listsHtml .= '<span class="badge bg-secondary float-end">' . $monthBadge . '</span>';
            $listsHtml .= '</h5>';
            
            $listsHtml .= '<p class="card-text">';
            $listsHtml .= 'Período: ' . date("d/m/Y", strtotime($list['period']['start'])) . ' até ' . date("d/m/Y", strtotime($list['period']['end'])) . '<br>';
            $listsHtml .= 'Criado em: ' . date("d/m/Y H:i:s", strtotime($list['date']['created']));
            $listsHtml .= '</p>';
            $listsHtml .= '</div>';
            $listsHtml .= '<div class="card-body p-0">';
        
            if (empty($list['items'])) {
                $listsHtml .= '<p class="text-warning">Nenhum item encontrado para este período.</p>';
            } else {
                $listsHtml .= '<table class="table  table-striped mt-3">';
                $listsHtml .= '<thead>';
                $listsHtml .= '<tr>';
                $listsHtml .= '<th class="text-center" style="max-width: 90px;">Data</th>';
                $listsHtml .= '<th class="text-center">Nome</th>';
                $listsHtml .= '<th class="text-center">Tipo</th>';
                $listsHtml .= '<th class="text-center">Valor</th>';
                $listsHtml .= '<th class="text-center" style="width: 40px;"></th>';
                $listsHtml .= '</tr>';
                $listsHtml .= '</thead>';
                $listsHtml .= '<tbody>';
        
                foreach ($list['items'] as $item) {
                    $priceData = $item['price'];
                    $price = $priceData['price'];
                    $installments = $priceData['installments'];
                    $listTotal += $price;
        
                    $currentInstallments = $installments ? $priceData['current_installment'] : 1;
                    $totalInstallments = $installments ? $price * $installments : $price;
                    $totalCurrentInstallments = $installments ? $price * $currentInstallments : $price;
        
                    $listsHtml .= '<tr data-item-id="' . $item['_id'] . '" data-installments="' . ($installments ? 'true' : 'false') . '">';
                    $listsHtml .= '<td>' . date("d/m", strtotime($item['date_buy'])) . '</td>';
                    $listsHtml .= '<td>' . htmlspecialchars($item['name']) . '</td>';
                    $listsHtml .= '<td>' . ($installments ? $currentInstallments . '/' . $installments : 'À vista') . '</td>';
                    $listsHtml .= '<td>';
                    $listsHtml .= 'R$ ' . number_format($price, 2, ',', '.');
                    if ($installments) {
                        $listsHtml .= ' <span class="text-muted">(' . number_format($totalCurrentInstallments, 2, ',', '.') . ' - ' . number_format($totalInstallments, 2, ',', '.') . ')</span>';
                    }
                    $listsHtml .= '</td>';
                    $listsHtml .= '<td class="text-center"><button class="btn btn-sm btn-danger d-flex align-items-center justify-content-center py-1 px-1 pb-2 rounded-pill delete-item" data-item-id="' . $item['_id'] . '"><img src="'.$config['base_url'].$config['icon_path'].'/trash_thin.svg"  style="width: 16px; height: 16px; filter: invert(1);"></button></td>';
                    $listsHtml .= '</tr>';
                }
        
                $listsHtml .= '</tbody>';
                $listsHtml .= '<tfoot>';
                $listsHtml .= '<tr>';
                $listsHtml .= '<td colspan="3" class="text-end"><strong>Total:</strong></td>';
                $listsHtml .= '<td><strong>R$ ' . number_format($listTotal, 2, ',', '.') . '</strong></td>';
                $listsHtml .= '<td></td>';
                $listsHtml .= '</tr>';
                $listsHtml .= '</tfoot>';
                $listsHtml .= '</table>';
            }
        
            $listsHtml .= '</div>';
            $listsHtml .= '</div>';
            $listsHtml .= '</div>';
        }
        $listsHtml .= '</div>';
        
        // Gera o HTML do extrato como <ul>
        // Ordenar os itens por data
        usort($allItems, function($a, $b) {
            return strtotime($b['date_buy']) - strtotime($a['date_buy']);
        });
        
        foreach ($allItems as $item) {
            $price = $item['price']['price'];
            $listType = $item['list_type']['type'];
            $badgeClass = $listType === 'receivable' ? 'bg-success' : 'bg-danger';
    
            $statementHtml .= '<ul class="list-group">';
            $statementHtml .= '<li class="list-group-item d-flex justify-content-between align-items-start">';
            $statementHtml .= '<div>';
            $statementHtml .= '<div class="fw-bold">' . htmlspecialchars($item['name']) . '</div>';
            $statementHtml .= '<small class="text-muted">' . htmlspecialchars($item['list_name']) . '</small>';
            $statementHtml .= '</div>';
            $statementHtml .= '<div class="text-end">';
            $statementHtml .= '<span class="badge ' . $badgeClass . '">R$ ' . number_format($price, 2, ',', '.') . '</span><br>';
            $statementHtml .= '<small class="text-muted">' . date("d/m/Y", strtotime($item['date_buy'])) . '</small>';
            $statementHtml .= '</div>';
            $statementHtml .= '</li>';
            $statementHtml .= '</ul>';
        }

        // Calcular totais
        $totalCosts = 0;
        $totalReceivables = 0;
        
        foreach ($allItems as $item) {
            $price = $item['price']['price'];
            $listType = $item['list_type']['type'];
            
            if ($listType === 'cost') {
                $totalCosts += $price;
            } else if ($listType === 'receivable') {
                $totalReceivables += $price;
            }
        }
        
        $balance = $totalReceivables - $totalCosts;

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'lists_html' => $listsHtml,
            'statement_html' => $statementHtml,
            'totals' => [
                'costs' => $totalCosts,
                'receivables' => $totalReceivables,
                'balance' => $balance
            ]
        ]);
    }

    if ($method === 'POST') {
        // Criação de um novo item
        $data = json_decode(file_get_contents('php://input'), true);

        // Validação dos dados
        if (empty($data['name']) || empty($data['date_buy']) || empty($data['price'])) {
            throw new Exception('Campos obrigatórios não preenchidos.');
        }

        // Converter installments e current_installment para inteiros
        $installments = $data['is_installment'] ? intval($data['installments']) : null;
        $current_installment = intval($data['current_installment']);

        // Insere o item no banco de dados
        $stmt = $conn->prepare("
            INSERT INTO gastosmensal_items (_id_user, _id_list, name, price, date_buy, date)
            VALUES (:user_id, :list_id, :name, JSON_OBJECT('price', :price, 'installments', :installments, 'current_installment', :current_installment), :date_buy, JSON_OBJECT('created', NOW(), 'updated', NOW()))
        ");
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':list_id', $data['list']);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':price', $data['price']);
        $stmt->bindParam(':installments', $installments, PDO::PARAM_INT);
        $stmt->bindParam(':current_installment', $current_installment, PDO::PARAM_INT);
        $stmt->bindParam(':date_buy', $data['date_buy']);

        $result = $stmt->execute();
        
        if ($result) {
            // Obter o ID do item recém-criado
            $itemId = $conn->lastInsertId();
            
            // Buscar o item completo
            $stmt = $conn->prepare("
                SELECT i._id, i.name, i.price, i.date_buy, l.name as list_name, l.type
                FROM gastosmensal_items i
                JOIN gastosmensal_lists l ON i._id_list = l._id
                WHERE i._id = :item_id
            ");
            $stmt->bindParam(':item_id', $itemId);
            $stmt->execute();
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($item) {
                // Preparar os dados para retornar
                $item['price'] = json_decode($item['price'], true);
                $item['type'] = json_decode($item['type'], true);
                
                // Definir o cabeçalho Content-Type antes de enviar a resposta
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Item adicionado com sucesso.',
                    'item' => $item
                ]);
            } else {
                throw new Exception('Erro ao recuperar o item criado.');
            }
        } else {
            throw new Exception('Erro ao adicionar item.');
        }
    } elseif ($method === 'PUT') {
        // Atualização de um item existente
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['_id']) || empty($data['name']) || empty($data['date_buy']) || empty($data['price'])) {
            throw new Exception('Campos obrigatórios não preenchidos.');
        }

        // Converter installments e current_installment para inteiros
        $installments = $data['is_installment'] ? intval($data['installments']) : null;
        $current_installment = intval($data['current_installment']);

        // Atualiza o item no banco de dados
        $stmt = $conn->prepare("
            UPDATE gastosmensal_items
            SET name = :name,
                price = JSON_OBJECT('price', :price, 'installments', :installments, 'current_installment', :current_installment),
                date_buy = :date_buy,
                date = JSON_SET(date, '$.updated', NOW())
            WHERE _id = :id AND _id_user = :user_id
        ");
        $stmt->bindParam(':id', $data['_id']);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':price', $data['price']);
        $stmt->bindParam(':installments', $installments, PDO::PARAM_INT);
        $stmt->bindParam(':current_installment', $current_installment, PDO::PARAM_INT);
        $stmt->bindParam(':date_buy', $data['date_buy']);
        $stmt->execute();

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Item atualizado com sucesso.']);
    } elseif ($method === 'DELETE') {
        // Exclusão de um item
        $itemId = isset($_GET['id']) ? $_GET['id'] : null;
        $deleteAllInstallments = isset($_GET['all_installments']) && $_GET['all_installments'] === 'true';
        
        if (!$itemId) {
            throw new Exception('ID do item não fornecido.');
        }
        
        // Verificar se o item existe e pertence ao usuário
        $stmt = $conn->prepare("
            SELECT i.*, JSON_EXTRACT(i.price, '$.installments') as installments, 
                   JSON_EXTRACT(i.price, '$.current_installment') as current_installment,
                   i.name as item_name
            FROM gastosmensal_items i
            WHERE i._id = :id AND i._id_user = :user_id
        ");
        $stmt->bindParam(':id', $itemId);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            throw new Exception('Item não encontrado ou não pertence ao usuário.');
        }
        
        $deletedIds = [$itemId]; // Inicializa com o ID atual
        
        // Verificar se é um item parcelado e se deve excluir todas as parcelas
        if ($item['installments'] > 1 && $deleteAllInstallments) {
            // Buscar todas as parcelas com o mesmo nome
            $stmt = $conn->prepare("
                SELECT _id FROM gastosmensal_items 
                WHERE _id_user = :user_id 
                AND name = :name 
                AND _id != :current_id
            ");
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':name', $item['item_name']);
            $stmt->bindParam(':current_id', $itemId);
            $stmt->execute();
            $relatedItems = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Adicionar os IDs relacionados à lista de exclusão
            $deletedIds = array_merge($deletedIds, $relatedItems);
            
            // Excluir todas as parcelas relacionadas
            if (!empty($relatedItems)) {
                $placeholders = implode(',', array_fill(0, count($relatedItems), '?'));
                $stmt = $conn->prepare("DELETE FROM gastosmensal_items WHERE _id IN ($placeholders) AND _id_user = ?");
                
                // Bind dos parâmetros
                $i = 1;
                foreach ($relatedItems as $id) {
                    $stmt->bindValue($i++, $id);
                }
                $stmt->bindValue($i, $_SESSION['user_id']);
                $stmt->execute();
            }
        }
        
        // Excluir o item atual
        $stmt = $conn->prepare("DELETE FROM gastosmensal_items WHERE _id = :id AND _id_user = :user_id");
        $stmt->bindParam(':id', $itemId);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => $deleteAllInstallments && count($deletedIds) > 1 ? 
                'Item e todas as parcelas relacionadas excluídos com sucesso.' : 
                'Item excluído com sucesso.',
            'deleted_ids' => $deletedIds
        ]);
    }
} catch (Exception $e) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
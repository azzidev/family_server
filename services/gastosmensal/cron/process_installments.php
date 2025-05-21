<?php
/**
 * Script para processar itens parcelados e criar novas parcelas para o próximo período
 * 
 * Este script deve ser executado diariamente via cron job
 * Exemplo de configuração cron: 0 0 * * * php /caminho/para/process_installments.php
 */

// Incluir conexão com o banco de dados
include '../../../server/db/connection.php';

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

// Log de execução
$logFile = __DIR__ . '/installments_log.txt';
function writeLog($message) {
    global $logFile;
    $date = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$date] $message" . PHP_EOL, FILE_APPEND);
}

writeLog("Iniciando processamento de parcelas");

// Adicione este código no início do script para debug
$stmt = $conn->prepare("
    SELECT i.*, l.name as list_name, l._id as list_id, l.period as list_period 
    FROM gastosmensal_items i
    JOIN gastosmensal_lists l ON i._id_list = l._id
    WHERE i._id = 3
");
$stmt->execute();
$debugItem = $stmt->fetch(PDO::FETCH_ASSOC);
writeLog("DEBUG - Item completo: " . print_r($debugItem, true));
writeLog("DEBUG - Lista período: " . $debugItem['list_period']);
writeLog("DEBUG - Lista ID: " . $debugItem['list_id']);

// Verificar se a lista está sendo processada
$stmt = $conn->prepare("
    SELECT * FROM gastosmensal_lists
    WHERE _id_user = :user_id
    AND _id = :list_id
    ORDER BY JSON_EXTRACT(period, '$.start') DESC
");
$stmt->bindParam(':user_id', $debugItem['_id_user']);
$stmt->bindParam(':list_id', $debugItem['list_id']);
$stmt->execute();
$listInfo = $stmt->fetch(PDO::FETCH_ASSOC);
writeLog("DEBUG - Lista encontrada no processamento: " . ($listInfo ? "SIM" : "NÃO"));

try {
    // Obter datas relevantes
    $today = date('Y-m-d');
    $oneMonthAgo = date('Y-m-d', strtotime('-1 month'));
    $oneMonthAhead = date('Y-m-d', strtotime('+1 month'));

    writeLog("Período de processamento: de $oneMonthAgo até $oneMonthAhead");

    // Buscar todos os usuários que têm itens parcelados
    $stmt = $conn->prepare("
        SELECT DISTINCT _id_user FROM gastosmensal_items 
        WHERE JSON_EXTRACT(price, '$.installments') IS NOT NULL
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

    writeLog("Encontrados " . count($users) . " usuários com itens parcelados");

    // Processar cada usuário
    foreach ($users as $userId) {
        writeLog("Processando usuário ID: $userId");
        
        // Buscar TODOS os itens parcelados do usuário que ainda não completaram todas as parcelas
        $stmt = $conn->prepare("
            SELECT i.*, l.name as list_name, l.type as list_type, l.period as list_period, l._id as list_id, l.description as list_description
            FROM gastosmensal_items i
            JOIN gastosmensal_lists l ON i._id_list = l._id
            WHERE i._id_user = :user_id
            AND JSON_EXTRACT(i.price, '$.installments') IS NOT NULL
            AND CAST(JSON_EXTRACT(i.price, '$.installments') AS UNSIGNED) > CAST(JSON_EXTRACT(i.price, '$.current_installment') AS UNSIGNED)
        ");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $allParceledItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        writeLog("Encontrados " . count($allParceledItems) . " itens parcelados para o usuário $userId");
        
        // Agrupar itens por lista
        $itemsByList = [];
        foreach ($allParceledItems as $item) {
            $listId = $item['list_id'];
            if (!isset($itemsByList[$listId])) {
                $itemsByList[$listId] = [
                    'list_info' => [
                        'id' => $listId,
                        'name' => $item['list_name'],
                        'type' => json_decode($item['list_type'], true),
                        'period' => json_decode($item['list_period'], true),
                        'description' => $item['list_description']
                    ],
                    'items' => []
                ];
            }
            $itemsByList[$listId]['items'][] = $item;
        }
        
        // Processar cada lista com itens parcelados
        foreach ($itemsByList as $listId => $listData) {
            $list = $listData['list_info'];
            $items = $listData['items'];
            
            writeLog("Processando lista {$list['name']} (ID: $listId) com " . count($items) . " itens parcelados");
            
            // Verificar se precisamos criar uma lista para o próximo período
            $listPeriod = $list['period'];
            $nextPeriodStart = date('Y-m-d', strtotime('+1 day', strtotime($listPeriod['end'])));
            
            // Só criar lista para o próximo período se estiver dentro do intervalo de processamento
            if (strtotime($nextPeriodStart) <= strtotime($oneMonthAhead)) {
                // Verificar se já existe uma lista para o próximo período
                $nextPeriodEnd = date('Y-m-t 23:59:59', strtotime($nextPeriodStart));
                
                $stmt = $conn->prepare("
                    SELECT * FROM gastosmensal_lists
                    WHERE _id_user = :user_id
                    AND name = :name
                    AND JSON_EXTRACT(type, '$.type') = :type
                    AND (
                        (JSON_EXTRACT(period, '$.start') <= :next_period_end 
                        AND JSON_EXTRACT(period, '$.end') >= :next_period_start)
                    )
                ");
                $stmt->bindParam(':user_id', $userId);
                $stmt->bindParam(':name', $list['name']);
                $typeValue = $list['type']['type'];
                $stmt->bindParam(':type', $typeValue);
                $stmt->bindParam(':next_period_start', $nextPeriodStart);
                $stmt->bindParam(':next_period_end', $nextPeriodEnd);
                $stmt->execute();
                $nextList = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $nextListId = null;
                
                // Se não existir uma lista para o próximo período, criar uma
                if (!$nextList) {
                    writeLog("Criando nova lista para o próximo período: {$list['name']}");
                    
                    // Calcular o novo período
                    $newPeriod = json_encode([
                        'start' => $nextPeriodStart . ' 00:00:00',
                        'end' => $nextPeriodEnd
                    ]);
                    
                    $now = json_encode([
                        'created' => date('Y-m-d H:i:s'),
                        'updated' => date('Y-m-d H:i:s')
                    ]);
                    
                    // Inserir a nova lista
                    $stmt = $conn->prepare("
                        INSERT INTO gastosmensal_lists (_id_user, name, description, type, period, date)
                        VALUES (:user_id, :name, :description, :type, :period, :date)
                    ");
                    $stmt->bindParam(':user_id', $userId);
                    $stmt->bindParam(':name', $list['name']);
                    $stmt->bindParam(':description', $list['description']);
                    $listType = json_encode($list['type']);
                    $stmt->bindParam(':type', $listType);
                    $stmt->bindParam(':period', $newPeriod);
                    $stmt->bindParam(':date', $now);
                    $stmt->execute();
                    
                    $nextListId = $conn->lastInsertId();
                    writeLog("Nova lista criada com ID: $nextListId");
                } else {
                    $nextListId = $nextList['_id'];
                    writeLog("Usando lista existente com ID: $nextListId");
                }
                
                // Processar cada item parcelado
                foreach ($items as $item) {
                    $priceData = json_decode($item['price'], true);
                    $installments = intval($priceData['installments']);
                    $currentInstallment = intval($priceData['current_installment']);
                    $nextInstallment = $currentInstallment + 1;
                    
                    // Verificar se ainda há parcelas a serem processadas
                    if ($nextInstallment <= $installments) {
                        writeLog("Processando item {$item['name']} - Parcela $nextInstallment/$installments");
                        
                        // Verificar se o item já existe na próxima lista
                        $stmt = $conn->prepare("
                            SELECT COUNT(*) FROM gastosmensal_items
                            WHERE _id_list = :list_id
                            AND name = :name
                            AND JSON_EXTRACT(price, '$.current_installment') = :next_installment
                        ");
                        $stmt->bindParam(':list_id', $nextListId);
                        $stmt->bindParam(':name', $item['name']);
                        $stmt->bindParam(':next_installment', $nextInstallment);
                        $stmt->execute();
                        $itemExists = $stmt->fetchColumn() > 0;
                        
                        if (!$itemExists) {
                            // Criar o novo item para a próxima parcela
                            $newDateBuy = date('Y-m-d', strtotime($nextPeriodStart));
                            
                            // Criar o novo objeto de preço com a parcela atualizada
                            $newPrice = json_encode([
                                'price' => $priceData['price'],
                                'installments' => $installments,
                                'current_installment' => $nextInstallment,
                                'total' => $priceData['total'] ?? ($priceData['price'] * $installments)
                            ]);
                            
                            $now = json_encode([
                                'created' => date('Y-m-d H:i:s'),
                                'updated' => date('Y-m-d H:i:s')
                            ]);
                            
                            // Inserir o novo item
                            $stmt = $conn->prepare("
                                INSERT INTO gastosmensal_items (_id_user, _id_list, name, price, date_buy, date)
                                VALUES (:user_id, :list_id, :name, :price, :date_buy, :date)
                            ");
                            $stmt->bindParam(':user_id', $userId);
                            $stmt->bindParam(':list_id', $nextListId);
                            $stmt->bindParam(':name', $item['name']);
                            $stmt->bindParam(':price', $newPrice);
                            $stmt->bindParam(':date_buy', $newDateBuy);
                            $stmt->bindParam(':date', $now);
                            $stmt->execute();
                            
                            $newItemId = $conn->lastInsertId();
                            writeLog("Nova parcela criada com ID: $newItemId para o item {$item['name']} ($nextInstallment/$installments)");
                        } else {
                            writeLog("Item {$item['name']} (parcela $nextInstallment) já existe na próxima lista. Ignorando.");
                        }
                    }
                }
            } else {
                writeLog("Próximo período ({$nextPeriodStart}) está fora do intervalo de processamento. Ignorando.");
            }
        }
    }    
    
    writeLog("Processamento concluído com sucesso");
    
} catch (Exception $e) {
    writeLog("ERRO: " . $e->getMessage());
    exit(1);
}

exit(0);
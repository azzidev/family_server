<?php
    require_once __DIR__ . '/../cmd/db.php';

    // Data de referência: mês seguinte
    $today = new DateTime();
    $nextMonth = (clone $today)->modify('first day of next month');
    $nextMonthStr = $nextMonth->format('Y-m'); // Ex: 2025-07

    // Busca todas as parcelas e assinaturas que precisam ser replicadas
    $sql = "
        SELECT i.*, l.period, l.type, l.name as list_name, l.description as list_description, l._id_user as user_id
        FROM gastosmensal_items i
        INNER JOIN gastosmensal_lists l ON l._id = i._id_list
        WHERE 
            JSON_EXTRACT(i.price, '$.installment_group_id') IS NOT NULL
            AND (
                (JSON_EXTRACT(i.price, '$.installments') = 9999) -- assinatura
                OR (JSON_EXTRACT(i.price, '$.installments') > 1) -- parcelado
            )
    ";
    $stmt = $conn->query($sql);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $item) {
        $userId = $item['user_id'];
        $listName = $item['list_name'];
        $listDescription = $item['list_description'];
        $price = json_decode($item['price'], true);
        $isSignature = ($price['installments'] == 9999);
        $isInstallment = (!$isSignature && $price['installments'] > 1);
        $groupId = $price['installment_group_id'];

        if ($isSignature) {
            $checkCancel = $conn->prepare("SELECT 1 FROM gastosmensal_cancelled_signatures WHERE installment_group_id = :gid");
            $checkCancel->bindParam(':gid', $groupId);
            $checkCancel->execute();
            if ($checkCancel->fetch()) {
                continue; // assinatura cancelada, não gera mais
            }
        }

        // Descobre a próxima parcela/assinatura
        $currentInstallment = intval($price['current_installment']);
        $nextInstallment = $currentInstallment + 1;

        if (!$isSignature && $nextInstallment > intval($price['installments'])) {
            continue;
        }

        // Data da próxima parcela: mesmo dia, mês seguinte
        $dateBuy = new DateTime($item['date_buy']);
        $nextDateBuy = (clone $dateBuy)->modify('first day of next month');
        $nextDateBuyStr = $nextDateBuy->format('Y-m-d');

        // Só cria se for para o mês seguinte
        if ($nextDateBuy->format('Y-m') !== $nextMonthStr) {
            continue;
        }

        // Verifica se já existe item para o próximo mês com o mesmo group_id e current_installment
        $check = $conn->prepare("SELECT 1 FROM gastosmensal_items WHERE JSON_EXTRACT(price, '$.installment_group_id') = :gid AND date_buy = :date_buy");
        $check->bindParam(':gid', $groupId);
        $check->bindParam(':date_buy', $nextDateBuyStr);
        $check->execute();
        if ($check->fetch()) {
            continue; // Já existe, não cria de novo
        }

        // Busca ou cria a lista do mês seguinte (mesmo nome, user, tipo, etc)
        $period = json_decode($item['period'], true);
        $origPeriodStart = new DateTime($period['start']);
        $origPeriodEnd = new DateTime($period['end']);
        $nextPeriodStart = $origPeriodStart->modify('+1 month')->format('Y-m-d 00:00:00');
        $nextPeriodEnd = $origPeriodEnd->modify('+1 month')->format('Y-m-d 23:59:59');
        $periodJson = json_encode([
            'start' => substr($nextPeriodStart, 0, 10),
            'end' => substr($nextPeriodEnd, 0, 10)
        ]);

        // Busca lista existente para o novo período
        $start_period = substr($nextPeriodStart, 0, 10);
        $listStmt = $conn->prepare("SELECT _id FROM gastosmensal_lists WHERE _id_user = :user_id AND name = :name AND JSON_UNQUOTE(JSON_EXTRACT(period, '$.start')) = :start");
        $listStmt->bindParam(':user_id', $userId);
        $listStmt->bindParam(':name', $listName);
        $listStmt->bindParam(':start', $start_period);
        $listStmt->execute();
        $listId = $listStmt->fetchColumn();

        // Se não existe, cria
        if (!$listId) {
            $typeJson = $item['type'];
            $dateNow = date('Y-m-d H:i:s');
            $dateJson = json_encode(['created' => $dateNow, 'updated' => $dateNow]);
            $insertList = $conn->prepare("INSERT INTO gastosmensal_lists (_id_user, name, description, type, period, date) VALUES (:user_id, :name, :description, :type, :period, :date)");
            $insertList->bindParam(':user_id', $userId);
            $insertList->bindParam(':name', $listName);
            $insertList->bindParam(':description', $listDescription);
            $insertList->bindParam(':type', $typeJson);
            $insertList->bindParam(':period', $periodJson);
            $insertList->bindParam(':date', $dateJson);
            $insertList->execute();
            $listId = $conn->lastInsertId();
        }

        // Monta price_json para o novo item
        $newPrice = [
            'price' => $price['price'],
            'installments' => $price['installments'],
            'current_installment' => $isSignature ? 1 : $nextInstallment,
            'installment_group_id' => $groupId,
            'total' => $price['total']
        ];
        $newPriceJson = json_encode($newPrice);

        // Monta date_json
        $dateNow = date('Y-m-d H:i:s');
        $dateJson = json_encode(['created' => $dateNow, 'updated' => $dateNow]);

        // Nome do item (para assinatura pode ser igual, para parcela pode adicionar número)
        $itemName = $item['name'];

        // Insere o novo item
        $insertItem = $conn->prepare("INSERT INTO gastosmensal_items (_id_user, _id_list, name, price, date_buy, date) VALUES (:user_id, :list_id, :name, :price, :date_buy, :date)");
        $insertItem->bindParam(':user_id', $userId);
        $insertItem->bindParam(':list_id', $listId);
        $insertItem->bindParam(':name', $itemName);
        $insertItem->bindParam(':price', $newPriceJson);
        $insertItem->bindParam(':date_buy', $nextDateBuyStr);
        $insertItem->bindParam(':date', $dateJson);
        $insertItem->execute();
    }

    echo json_encode(['success' => true, 'message' => 'Parcelas e assinaturas replicadas para o próximo']);
?>
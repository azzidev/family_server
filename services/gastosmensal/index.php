<?php
    session_start();

    // Verifique se o usuário está autenticado
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../../auth/index.html");
        exit();
    }

    include '../../server/db/connection.php';
    include '../../root/cmd/config.php';
    include 'src/components/head.php';

    $config = getConfig();

    // Fetch lists from the database
    $lists = [];
    try {
        // Verificar se existem cookies com as datas
        if(isset($_COOKIE['gm_start_date']) && isset($_COOKIE['gm_end_date'])) {
            $start_date = $_COOKIE['gm_start_date'];
            $end_date = $_COOKIE['gm_end_date'];
        } else {
            // Definir datas padrão (mês atual)
            $start_date = date('Y-m-01'); // Primeiro dia do mês atual
            $end_date = date('Y-m-t'); // Último dia do mês atual
            
            // Salvar em cookies (válidos por 30 dias)
            setcookie('gm_start_date', $start_date, time() + (86400 * 30), "/");
            setcookie('gm_end_date', $end_date, time() + (86400 * 30), "/");
        }
        
        // Formatar as datas para incluir o horário completo
        $start_date_formatted = $start_date . ' 00:00:00';
        $end_date_formatted = $end_date . ' 23:59:59';

        $stmt = $conn->prepare("SELECT _id, name, description, type, period, date 
            FROM gastosmensal_lists 
            WHERE _id_user = :user_id 
            AND (
                (JSON_EXTRACT(period, '$.start') <= :end_date AND JSON_EXTRACT(period, '$.end') >= :start_date)
            )
            ORDER BY JSON_EXTRACT(period, '$.start') DESC");
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':start_date', $start_date_formatted);
        $stmt->bindParam(':end_date', $end_date_formatted);
        $stmt->execute();
        $lists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error fetching lists: " . $e->getMessage());
    }

    // Calcular totais
    $totalCosts = 0;
    $totalReceivables = 0;

    foreach ($lists as $list) {
        $listType = json_decode($list['type'], true)['type']; // Identifica o tipo da lista
        $period = json_decode($list['period'], true);

        try {
            $stmt = $conn->prepare("SELECT price FROM gastosmensal_items WHERE _id_list = :list_id AND _id_user = :user_id AND date_buy BETWEEN :startDate AND :endDate");
            $stmt->bindParam(':list_id', $list['_id']);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':startDate', $period['start']);
            $stmt->bindParam(':endDate', $period['end']);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as $item) {
                $priceData = json_decode($item['price'], true);
                $price = $priceData['price'];

                if ($listType === 'cost') {
                    $totalCosts += $price;
                } elseif ($listType === 'receivable') {
                    $totalReceivables += $price;
                }
            }
        } catch (PDOException $e) {
            die("Error calculating totals: " . $e->getMessage());
        }
    }

    $balance = $totalReceivables - $totalCosts;
?>

<body>
    <div class="container mt-5">
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <h5 class="card-title fw-bold">Total de Custos</h5>
                        <p class="card-text">R$ <?= number_format($totalCosts, 2, ',', '.') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title fw-bold">Total de Entradas</h5>
                        <p class="card-text">R$ <?= number_format($totalReceivables, 2, ',', '.') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title fw-bold">Balanço Geral</h5>
                        <p class="card-text">R$ <?= number_format($balance, 2, ',', '.') ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="accordion mb-5" id="accordionExample">
            <!-- Custos -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingCosts">
                    <button class="accordion-button bg-danger text-white collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCosts" aria-expanded="true" aria-controls="collapseCosts">
                        Listas de Custos
                    </button>
                </h2>
                <div id="collapseCosts" class="accordion-collapse collapse" aria-labelledby="headingCosts" data-bs-parent="#accordionExample">
                    <div class="accordion-body">
                        <?php
                        $totalCostsDetails = 0;
                        foreach ($lists as $list):
                            $listType = json_decode($list['type'], true)['type'];
                            if ($listType === 'cost'):
                                $period = json_decode($list['period'], true);
                                $listTotal = 0;

                                // Calcular o total da lista
                                try {
                                    $stmt = $conn->prepare("SELECT price FROM gastosmensal_items WHERE _id_list = :list_id AND _id_user = :user_id AND date_buy BETWEEN :startDate AND :endDate");
                                    $stmt->bindParam(':list_id', $list['_id']);
                                    $stmt->bindParam(':user_id', $_SESSION['user_id']);
                                    $stmt->bindParam(':startDate', $period['start']);
                                    $stmt->bindParam(':endDate', $period['end']);
                                    $stmt->execute();
                                    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    foreach ($items as $item) {
                                        $priceData = json_decode($item['price'], true);
                                        $listTotal += $priceData['price'];
                                    }
                                } catch (PDOException $e) {
                                    echo "<p class='text-danger'>Erro ao buscar itens: " . htmlspecialchars($e->getMessage()) . "</p>";
                                }
                                ?>
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <p class="card-text text-end mb-0">
                                            <strong>Total da Lista: R$ <?= number_format($listTotal, 2, ',', '.') ?></strong>
                                        </p>
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($list['name']) ?></h5>
                                        <p class="card-text">
                                            Período: <?= date("d/m/Y", strtotime($period['start'])) ?> até <?= date("d/m/Y", strtotime($period['end'])) ?>
                                        </p>
                                    </div>
                                </div>
                                <?php
                                $totalCostsDetails += $listTotal;
                            endif;
                        endforeach;
                        ?>
                        <div class="text-end mt-3">
                            <h5><strong>Total de Custos: R$ <?= number_format($totalCostsDetails, 2, ',', '.') ?></strong></h5>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Entradas -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingReceivables">
                    <button class="accordion-button bg-success text-white collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseReceivables" aria-expanded="false" aria-controls="collapseReceivables">
                        Listas de Entradas
                    </button>
                </h2>
                <div id="collapseReceivables" class="accordion-collapse collapse" aria-labelledby="headingReceivables" data-bs-parent="#accordionExample">
                    <div class="accordion-body">
                        <?php
                        $totalReceivablesDetails = 0;
                        foreach ($lists as $list):
                            $listType = json_decode($list['type'], true)['type'];
                            if ($listType === 'receivable'):
                                $period = json_decode($list['period'], true);
                                $listTotal = 0;

                                // Calcular o total da lista
                                try {
                                    $stmt = $conn->prepare("SELECT price FROM gastosmensal_items WHERE _id_list = :list_id AND _id_user = :user_id AND date_buy BETWEEN :startDate AND :endDate");
                                    $stmt->bindParam(':list_id', $list['_id']);
                                    $stmt->bindParam(':user_id', $_SESSION['user_id']);
                                    $stmt->bindParam(':startDate', $period['start']);
                                    $stmt->bindParam(':endDate', $period['end']);
                                    $stmt->execute();
                                    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    foreach ($items as $item) {
                                        $priceData = json_decode($item['price'], true);
                                        $listTotal += $priceData['price'];
                                    }
                                } catch (PDOException $e) {
                                    echo "<p class='text-danger'>Erro ao buscar itens: " . htmlspecialchars($e->getMessage()) . "</p>";
                                }
                                ?>
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <p class="card-text text-end mb-0">
                                            <strong>Total da Lista: R$ <?= number_format($listTotal, 2, ',', '.') ?></strong>
                                        </p>
                                        <h5 class="card-title"><?= htmlspecialchars($list['name']) ?></h5>
                                        <p class="card-text">
                                            Período: <?= date("d/m/Y", strtotime($period['start'])) ?> até <?= date("d/m/Y", strtotime($period['end'])) ?>
                                        </p>
                                    </div>
                                </div>
                                <?php
                                $totalReceivablesDetails += $listTotal;
                            endif;
                        endforeach;
                        ?>
                        <div class="text-end mt-3">
                            <h5><strong>Total de Entradas: R$ <?= number_format($totalReceivablesDetails, 2, ',', '.') ?></strong></h5>
                        </div>
                    </div>
                </div>
            </div>
        </div> 

        <div class="row mb-3">
            <div class="col-md-4 d-flex align-items-end justify-content-start">
                <div class="pagination">
                    <li class="page-item cursor-pointer" id="previous-month-btn"><a class="page-link">Mês passado</a></li>
                    <li class="page-item cursor-pointer"><a class="page-link">Período atual</a></li>
                    <li class="page-item cursor-pointer"><a class="page-link">Mês atual</a></li>
                </div>
            </div>
            <div class="col-md-3 ms-auto">
                <div class="form-group">
                    <label for="start-period-filter">Data inicial</label>
                    <input type="date" id="start-period-filter" name="start-period-filter" class="form-control" value="<?= $start_date ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="end-period-filter">Data final</label>
                    <input type="date" id="end-period-filter" name="end-period-filter" class="form-control" value="<?= $end_date ?>">
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs justify-content-end mb-3" id="viewsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#view-list-tab" type="button" role="tab" aria-controls="view-list-tab" aria-selected="true">Listas</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="statement-tab" data-bs-toggle="tab" data-bs-target="#view-statement-tab" type="button" role="tab" aria-controls="view-statement-tab" aria-selected="false">Extrato Mensal</button>
            </li>
        </ul>
        <div class="tab-content" id="viewsTabsContent">
            <div class="tab-pane fade show active" id="view-list-tab" role="tabpanel" aria-labelledby="view-list-tab">
                <h1 class="mb-4">Listas de Gastos</h1>
                <div id="list-container">
                    <?php if (empty($lists)): ?>
                        <h3 class="text-warning">Nenhuma lista encontrada.</h3>
                        <p>Filtre por um período anterior ou inclua uma lista ao período atual.</p>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($lists as $list): ?>
                                <div class="col-md-6 col-sm-12 d-flex align-items-stretch">
                                    <div class="card mb-3 flex-fill">
                                        <div class="card-header">
                                            <?php
                                                // Configurar locale para português
                                                setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese');
                                                
                                                // Determinar o mês predominante em português
                                                $period = json_decode($list['period'], true);
                                                $startMonth = strftime('%b', strtotime($period['start']));
                                                $endMonth = strftime('%b', strtotime($period['end']));
                                                $monthBadge = ucfirst($startMonth);
                                                if ($startMonth != $endMonth) {
                                                    $monthBadge = ucfirst($startMonth) . '-' . ucfirst($endMonth);
                                                }
                                                $listType = json_decode($list['type'], true);
                                            ?>
                                            <h5 class="card-title">
                                                <?= htmlspecialchars($list['name']) ?>
                                                <span class="badge bg-<?=$listType['color']?> float-end ms-1"><?= htmlspecialchars($listType['name']) ?></span>
                                                <span class="badge bg-secondary float-end"><?= $monthBadge ?></span>
                                            </h5>
                                            <p class="card-text">
                                                Período: <?= date("d/m/Y", strtotime(json_decode($list['period'], true)['start'])) ?> até <?= date("d/m/Y H:i:s", strtotime(json_decode($list['period'], true)['end'])) ?><br>
                                                Criado em: <?= date("d/m/Y H:i:s", strtotime(json_decode($list['date'], true)['created'])) ?>
                                            </p>
                                        </div>
                                        <div class="card-body p-0">
                                            <?php
                                                // Fetch items for the current list
                                                $items = [];
                                                try {
                                                    $period = json_decode($list['period'], true);
                                                    $stmt = $conn->prepare("SELECT _id, name, price, date_buy FROM gastosmensal_items WHERE _id_list={$list['_id']} AND  _id_user={$_SESSION['user_id']} AND date_buy BETWEEN :startDate AND :endDate");
                                                    $stmt->bindParam(':startDate', $period['start']);
                                                    $stmt->bindParam(':endDate', $period['end']);
                                                    $stmt->execute();
                                                    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                } catch (PDOException $e) {
                                                    echo "<p class='text-danger'>Erro ao buscar itens: " . htmlspecialchars($e->getMessage()) . "</p>";
                                                }
                                            ?>

                                            <?php if (empty($items)): ?>
                                                <p class="text-warning">Nenhum item encontrado para este período.</p>
                                            <?php else: ?>
                                                <table class="table  table-striped mt-3">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-center" style="max-width: 90px;">Data</th>
                                                            <th class="text-center">Nome</th>
                                                            <th class="text-center">Tipo</th>
                                                            <th class="text-center">Valor</th>
                                                            <th class="text-center" style="width: 40px;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                            $total = 0;
                                                            foreach ($items as $item): 
                                                                $priceData = json_decode($item['price'], true);
                                                                $price = $priceData['price'];
                                                                $installments = $priceData['installments'];
                                                                $total += $price;

                                                                if ($installments) {
                                                                    $currentInstallments = $priceData['current_installment'];
                                                                    $totalInstallments = $price * $installments;
                                                                    $totalCurrentInstallments = $price * $currentInstallments;
                                                                } else {
                                                                    $currentInstallments = 1;
                                                                    $totalInstallments = $price;
                                                                    $totalCurrentInstallments = $price;
                                                                }
                                                        ?>
                                                            <tr data-installments="<?= $installments ? 'true' : 'false' ?>">
                                                                <td><?= date("d/m", strtotime($item['date_buy'])) ?></td>
                                                                <td><?= htmlspecialchars($item['name']) ?></td>
                                                                <td><?= $installments ? $currentInstallments .'/'. $installments : 'À vista' ?></td>
                                                                <td>
                                                                    R$ <?= number_format($price, 2, ',', '.') ?>
                                                                    <?php if ($installments): ?>
                                                                        <span class="text-muted">(<?= number_format($totalCurrentInstallments, 2, ',', '.') .' - '. number_format($totalInstallments, 2, ',', '.') ?>)</span> 
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-center">
                                                                    <button class="btn btn-sm btn-danger d-flex align-items-center justify-content-center py-1 px-1 pb-2 rounded-pill delete-item" data-item-id="<?= $item['_id'] ?>"><img src="<?=$config['base_url'].$config['icon_path']?>/trash_thin.svg"  style="width: 16px; height: 16px; filter: invert(1);"></button>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                    <tfoot>
                                                        <tr>
                                                            <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                                            <td><strong>R$ <?= number_format($total, 2, ',', '.') ?></strong></td>
                                                            <td></td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="tab-pane fade" id="view-statement-tab" role="tabpanel" aria-labelledby="view-statement-tab">
                <h1 class="mb-4">Extrato</h1>
                <div id="statement-container" class="mb-3">
                    <?php
                    $allItems = [];
                    try {
                        // Definir o primeiro e último dia do mês atual
                        $firstDayOfMonth = date('Y-m-01'); // Primeiro dia do mês atual
                        $lastDayOfMonth = date('Y-m-t'); // Último dia do mês atual
                        
                        // Formatar as datas para incluir o horário completo
                        $firstDayFormatted = $firstDayOfMonth . ' 00:00:00';
                        $lastDayFormatted = $lastDayOfMonth . ' 23:59:59';
                        
                        // Buscar todos os itens do mês atual
                        $stmt = $conn->prepare("
                            SELECT i.name AS item_name, i.price, i.date_buy, l.name AS list_name, l.type 
                            FROM gastosmensal_items i 
                            INNER JOIN gastosmensal_lists l ON i._id_list = l._id 
                            WHERE i._id_user = :user_id 
                            AND i.date_buy BETWEEN :start_date AND :end_date 
                            ORDER BY i.date_buy DESC
                        ");
                        $stmt->bindParam(':user_id', $_SESSION['user_id']);
                        $stmt->bindParam(':start_date', $firstDayFormatted);
                        $stmt->bindParam(':end_date', $lastDayFormatted);
                        $stmt->execute();
                        $allItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        echo "<p class='text-danger'>Erro ao buscar extrato: " . htmlspecialchars($e->getMessage()) . "</p>";
                    }
                    ?>

                    <?php if (empty($allItems)): ?>
                        <h3 class="text-warning">Nenhum item encontrado no extrato.</h3>
                        <p>Filtre por um período anterior ou inclua uma lista contendo pelo menos um item referente ao período atual.</p>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($allItems as $item): 
                                $priceData = json_decode($item['price'], true);
                                $price = $priceData['price'];
                                $listType = json_decode($item['type'], true)['type'];
                                $badgeClass = $listType === 'receivable' ? 'bg-success' : 'bg-danger';
                            ?>
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($item['item_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($item['list_name']) ?></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge <?= $badgeClass ?>">R$ <?= number_format($price, 2, ',', '.') ?></span><br>
                                        <small class="text-muted"><?= date("d/m/Y", strtotime($item['date_buy'])) ?></small>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Botão Flutuante -->
    <div class="floating-button">
        <button class="btn btn-secondary btn-lg py-0 px-1 pb-1 rounded-circle" id="actionButton">
            <img src="<?=$config['base_url'].$config['icon_path']?>/tree-dots.svg" style="width: 24px; height: 24px; filter: invert(1);">
        </button>
        <div class="floating-menu" id="actionMenu">
            <button class="btn btn-secondary mb-2" id="addListButton">Adicionar Lista</button>
            <button class="btn btn-secondary mb-2" id="addItemButton">Adicionar Item</button>
        </div>
    </div>

    <!-- Modal para Criar Lista -->
    <div class="modal fade" id="addListModal" tabindex="-1" aria-labelledby="addListModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="addListForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addListModalLabel">Criar Nova Lista</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="listName" class="form-label">Nome da Lista</label>
                            <input type="text" class="form-control" id="listName" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="listDescription" class="form-label">Descrição</label>
                            <textarea class="form-control" id="listDescription" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="listType" class="form-label">Tipo</label>
                            <select class="form-select" id="listType" name="type" required>
                                <option value="" disabled selected>Selecione o tipo</option>
                                <option value="cost">Custo</option>
                                <option value="receivable">Entrada</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="listStartDate" class="form-label">Data de Início</label>
                            <input type="date" class="form-control" id="listStartDate" name="start_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="listEndDate" class="form-label">Data de Fim</label>
                            <input type="date" class="form-control" id="listEndDate" name="end_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Salvar</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para Adicionar Item -->
    <div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="addItemForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addItemModalLabel">Adicionar Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="itemList" class="form-label">Lista</label>
                            <select class="form-select" id="itemList" name="_id_list" required>
                                <option value="" disabled selected>Selecione uma lista</option>
                                <?php foreach ($lists as $list): ?>
                                    <option value="<?= $list['_id'] ?>"><?= htmlspecialchars($list['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="itemName" class="form-label">Nome</label>
                            <input type="text" class="form-control" id="itemName" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="itemDate" class="form-label">Data</label>
                            <input type="date" class="form-control" id="itemDate" name="date_buy" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="itemPrice" class="form-label">Valor</label>
                            <input type="number" step="0.01" class="form-control" id="itemPrice" name="price" required>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="isInstallment" name="is_installment">
                            <label class="form-check-label" for="isInstallment">Parcelado?</label>
                        </div>
                        <div id="installmentFields" style="display: none;">
                            <div class="mb-3">
                                <label for="installments" class="form-label">Número de Parcelas</label>
                                <input type="number" class="form-control" id="installments" name="installments" min="1">
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="currentInstallmentSwitch" name="current_installment_switch" checked>
                                <label class="form-check-label" for="currentInstallmentSwitch">Adicionar Parcela Atual?</label>
                            </div>
                            <div class="mb-3">
                                <label for="currentInstallment" class="form-label">Parcela Atual</label>
                                <input type="number" class="form-control" id="currentInstallment" name="current_installment" value="1" min="1">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Salvar</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php
        include 'src/components/scripts.php';
    ?>
    
    <!-- Modal de confirmação de exclusão -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteConfirmModalLabel">Excluir Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="deleteConfirmMessage">Tem certeza que deseja excluir este item?</p>
                    <div class="form-check delete-installment-options" style="display: none;">
                        <input class="form-check-input" type="checkbox" checked id="deleteAllInstallments">
                        <label class="form-check-label" for="deleteAllInstallments">
                            Excluir todas as parcelas deste item
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="deleteConfirmBtn">Excluir</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Variáveis para exclusão de itens
    let itemToDelete = null;
    let deleteAllInstallments = false;
    
    // Função para mostrar notificações em modal
    function showNotificationModal(title, message, type = 'info') {
        // Verificar se o modal já existe
        if ($('#notificationModal').length === 0) {
            // Criar o modal dinamicamente
            $('body').append(`
                <div class="modal fade" id="notificationModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>
                            <div class="modal-body"></div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            </div>
                        </div>
                    </div>
                </div>
            `);
        }
        
        const $modal = $('#notificationModal');
        $modal.find('.modal-title').text(title);
        $modal.find('.modal-body').html(message);
        
        // Remover classes anteriores
        $modal.find('.modal-header').removeClass('bg-success bg-danger bg-warning bg-info');
        
        // Adicionar classe de acordo com o tipo
        switch(type) {
            case 'success':
                $modal.find('.modal-header').addClass('bg-success text-white');
                break;
            case 'danger':
                $modal.find('.modal-header').addClass('bg-danger text-white');
                break;
            case 'warning':
                $modal.find('.modal-header').addClass('bg-warning');
                break;
            default:
                $modal.find('.modal-header').addClass('bg-info text-white');
        }
        
        // Mostrar o modal
        const notificationModal = new bootstrap.Modal($modal[0]);
        notificationModal.show();
    }
    
    // Manipulação da paginação (Período atual / Mês atual / Mês passado)
    $(document).ready(function() {
        // Manipular clique nos itens da paginação
        $('.pagination .page-item').click(function() {
            // Remover classe active de todos os itens
            $('.pagination .page-item').removeClass('active');
            // Adicionar classe active ao item clicado
            $(this).addClass('active');
            
            // Verificar qual opção foi selecionada
            const selectedOption = $(this).find('.page-link').text();
            
            if (selectedOption === 'Mês atual') {
                // Definir primeiro e último dia do mês atual
                const firstDay = new Date();
                firstDay.setDate(1);
                
                const lastDay = new Date();
                lastDay.setMonth(lastDay.getMonth() + 1);
                lastDay.setDate(0);
                
                // Formatar as datas para o formato YYYY-MM-DD
                const firstDayFormatted = firstDay.toISOString().split('T')[0];
                const lastDayFormatted = lastDay.toISOString().split('T')[0];
                
                // Atualizar os inputs de data
                $('#start-period-filter').val(firstDayFormatted);
                $('#end-period-filter').val(lastDayFormatted);
                
                // Salvar em cookies
                document.cookie = `gm_start_date=${firstDayFormatted}; path=/; max-age=${60*60*24*30}`;
                document.cookie = `gm_end_date=${lastDayFormatted}; path=/; max-age=${60*60*24*30}`;
                
                // Disparar evento change nos inputs
                $('#start-period-filter, #end-period-filter').trigger('change');
            } else if (selectedOption === 'Mês passado') {
                // Definir primeiro e último dia do mês passado
                const today = new Date();
                const firstDay = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                const lastDay = new Date(today.getFullYear(), today.getMonth(), 0);
                
                // Formatar as datas para o formato YYYY-MM-DD
                const firstDayFormatted = firstDay.toISOString().split('T')[0];
                const lastDayFormatted = lastDay.toISOString().split('T')[0];
                
                // Atualizar os inputs de data
                $('#start-period-filter').val(firstDayFormatted);
                $('#end-period-filter').val(lastDayFormatted);
                
                // Salvar em cookies
                document.cookie = `gm_start_date=${firstDayFormatted}; path=/; max-age=${60*60*24*30}`;
                document.cookie = `gm_end_date=${lastDayFormatted}; path=/; max-age=${60*60*24*30}`;
                
                // Disparar evento change nos inputs
                $('#start-period-filter, #end-period-filter').trigger('change');
            } else if (selectedOption === 'Período atual') {
                // Definir data atual e data atual + 1 mês
                const today = new Date();
                const nextMonth = new Date();
                nextMonth.setMonth(nextMonth.getMonth() + 1);
                
                // Formatar as datas para o formato YYYY-MM-DD
                const todayFormatted = today.toISOString().split('T')[0];
                const nextMonthFormatted = nextMonth.toISOString().split('T')[0];
                
                // Atualizar os inputs de data
                $('#start-period-filter').val(todayFormatted);
                $('#end-period-filter').val(nextMonthFormatted);
                
                // Salvar em cookies
                document.cookie = `gm_start_date=${todayFormatted}; path=/; max-age=${60*60*24*30}`;
                document.cookie = `gm_end_date=${nextMonthFormatted}; path=/; max-age=${60*60*24*30}`;
                
                // Disparar evento change nos inputs
                $('#start-period-filter, #end-period-filter').trigger('change');
            }
        });
    });
    
    // Adicionar evento de clique para botões de exclusão
    $(document).on('click', '.delete-item', function(e) {
        e.preventDefault();
        itemToDelete = $(this).data('item-id');
        
        // Verificar se o item tem parcelas pelo texto da coluna "Tipo"
        const tipoText = $(this).closest('tr').find('td:eq(2)').text();
        const isInstallment = tipoText.includes('/');
        
        if (isInstallment) {
            // Se for um item parcelado, mostrar o modal de confirmação
            $('#deleteConfirmModalLabel').text('Excluir Item Parcelado');
            $('#deleteConfirmMessage').html('Deseja excluir apenas esta parcela ou todas as parcelas deste item?<br><small class="text-muted">Excluir todas as parcelas irá remover este item de todas as listas.</small>');
            $('.delete-installment-options').show();
            $('#deleteConfirmModal').modal('show');
        } else {
            // Se não for parcelado, mostrar o modal de confirmação simples
            $('#deleteConfirmModalLabel').text('Excluir Item');
            $('#deleteConfirmMessage').text('Tem certeza que deseja excluir este item?');
            $('.delete-installment-options').hide();
            $('#deleteConfirmModal').modal('show');
        }
    });


    // Confirmar exclusão
    $('#deleteConfirmBtn').on('click', function() {
        deleteAllInstallments = $('#deleteAllInstallments').is(':checked');
        deleteItem(itemToDelete, deleteAllInstallments);
        $('#deleteConfirmModal').modal('hide');
    });
    
    // Função para excluir item
    function deleteItem(itemId, allInstallments) {
        $.ajax({
            url: `./api/items.php?id=${itemId}&all_installments=${allInstallments}`,
            method: 'DELETE',
            success: function(response) {
                console.log('Resposta do servidor (excluir item):', response);
                
                if (response.success) {
                    // Remover os itens da interface
                    if (response.deleted_ids) {
                        console.log('IDs a serem removidos:', response.deleted_ids);
                        
                        response.deleted_ids.forEach(function(id) {
                            // Remover da tabela - usando seletor mais específico
                            const $rows = $('tr').filter(function() {
                                return $(this).data('item-id') == id;
                            });
                            
                            console.log(`Encontradas ${$rows.length} linhas para o item ID ${id}`);
                            $rows.remove();
                            
                            // Remover do extrato
                            const $statementItems = $('.statement-item').filter(function() {
                                return $(this).data('item-id') == id;
                            });
                            
                            console.log(`Encontrados ${$statementItems.length} itens no extrato para o ID ${id}`);
                            $statementItems.remove();
                        });
                    }
                    
                    // Mostrar mensagem de sucesso em um modal
                    showNotificationModal('Sucesso', response.message, 'success');
                } else {
                    showNotificationModal('Erro', 'Erro ao excluir item: ' + (response.error || 'Erro desconhecido'), 'danger');
                }
            },
            error: function(xhr) {
                showNotificationModal('Erro', 'Erro ao excluir item: ' + xhr.responseText, 'danger');
            }
        });
    }

    // Função para atualizar os totais gerais
    function updateTotals() {
        // Recalcular totais
        let totalCosts = 0;
        let totalReceivables = 0;
        
        // Processar cada tabela
        $('table').each(function() {
            const $table = $(this);
            const isReceivable = $table.closest('.card').find('.badge.bg-success').length > 0;
            
            let tableTotal = 0;
            $table.find('tbody tr').each(function() {
                try {
                    const priceText = $(this).find('td:eq(3)').text();
                    if (priceText) {
                        const priceValue = priceText.split('R$ ')[1].split('(')[0].trim();
                        const price = parseFloat(priceValue.replace('.', '').replace(',', '.'));
                        tableTotal += price;
                        
                        if (isReceivable) {
                            totalReceivables += price;
                        } else {
                            totalCosts += price;
                        }
                    }
                } catch (e) {
                    console.error('Erro ao processar preço:', e);
                }
            });
        });
        
        // Atualizar os cards de totais
        $('.bg-danger .card-text').text('R$ ' + formatCurrency(totalCosts));
        $('.bg-success .card-text').text('R$ ' + formatCurrency(totalReceivables));
        $('.bg-primary .card-text').text('R$ ' + formatCurrency(totalReceivables - totalCosts));
    }

    // Função para formatar valores monetários
    function formatCurrency(value) {
        return value.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    </script>
</body>
</html>
<?php
    $start_date = $_GET['start'] ?? $_COOKIE['gm_start_date'];
    $end_date = $_GET['end'] ?? $_COOKIE['gm_end_date'];

include __DIR__ .'/../helpers/get-predominant-month.php';
    if(isset($_GET['start'])) {
        include __DIR__ .'/../cmd/db.php';
        include __DIR__ .'/../helpers/ping-session.php';
        include __DIR__ .'/../helpers/number2currency.php';
    }

    $stmt = $conn->prepare("SELECT * FROM gastosmensal_lists
        WHERE _id_user = :user_id
        AND JSON_UNQUOTE(JSON_EXTRACT(period, '$.start')) <= :end_date
        AND JSON_UNQUOTE(JSON_EXTRACT(period, '$.end')) >= :start_date
        ORDER BY _id DESC;
    ");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

    function getItemsByListId($list_id) {
        global $conn;
        $stmt = $conn->prepare("SELECT * FROM gastosmensal_items WHERE _id_list = :list_id");
        $stmt->bindParam(':list_id', $list_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    function getTotalizer($items) {
        $total = 0;
        foreach($items as $item) {
            $price_json = json_decode($item['price'], true);
            $total += (float)$price_json['price'];
        }
        return number2currency($total);
    }

    function buildRow($items) {
        $html = '';
        if(!$items) {
            return '
            <tr>
                <td colspan="4" class="text-center py-5">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-archive-fill my-3" viewBox="0 0 16 16">
                        <path d="M12.643 15C13.979 15 15 13.845 15 12.5V5H1v7.5C1 13.845 2.021 15 3.357 15zM5.5 7h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1 0-1M.8 1a.8.8 0 0 0-.8.8V3a.8.8 0 0 0 .8.8h14.4A.8.8 0 0 0 16 3V1.8a.8.8 0 0 0-.8-.8z"/>
                    </svg>
                    <p class="my-3">Nenhum item encontrado.</p>
                </td>
            </tr>';
        }
        foreach($items as $item ) {
            $price_json = json_decode($item['price'], true);
            $html .= '
                <tr>
                    <td class="text-center">' . date('d/m/Y', strtotime($item['date_buy'])) . '</td>
                    <td class="text-center">' . $item['name'] . '</td>
                    <td class="text-center">' . ($price_json['installments'] === 9999 ? "Assinatura" : ((int)$price_json['installments'] > 1 ? $price_json['current_installment'] . '/' . $price_json['installments'] : 'À vista')) . '</td>
                    <th class="text-center" scope="row">' . number2currency($price_json['price']) . '</th>
                    <td class="text-center d-flex align-items-center justify-content-center p-1">
                        <button onclick="'.  (isset($price_json['installment_group_id']) ? 'openConfirmActionModal(`Você está excluindo uma parcela ou assinatura`, `Atenção, você está prestes a excluir um conjunto de parcelas ou assinatura. Tem certeza que deseja fazer isso? Confira o item caso não tenha certeza.<p class=\'small alert alert-secondary mt-2\'>Caso seja uma parcela, todas serão deletadas, tanto passadas quanto futuras. Caso seja uma assinatura, somente a atual e futuras serão deletadas.</p>`, `Excluir itens ou cancelar assinatura`, `deleteItemsByGroupId(\'' . $price_json['installment_group_id'] . '\', \''.$item['_id'].'\')`)' : 'openConfirmActionModal(`Você está deletando um item`, `Atenção, você está prestes a deletar um item. Tem certeza que deseja fazer isso? Confira o item caso não tenha certeza.`, `Excluir item`, `deleteItemById(\'' . $item['_id'] . '\')`)')  .'"  class="btn btn-sm btn-outline-danger" style="width: 100%;display: flex;align-items: center;justify-content: center;height: 32px">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16">
                                <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>
                                <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/>
                            </svg>
                        </a>
                    </td>
                </tr>
            ';
        }
        return $html;
    }

    if($lists){
        foreach($lists as $list) {
            $type_json = json_decode($list['type'], true);
            $period_json = json_decode($list['period'], true);
            $temp_items = getItemsByListId($list['_id']);
            echo '
               <div class="masonry-item col-md-6 col-lg-6 col-sm-12 p-4 py-1" data-key="'. $list['_id'] .'">
                    <div class="row '. ($type_json['type'] == 'cost' ? 'bg-danger' : ($type_json['type'] == 'receivable' ? 'bg-success' : 'bg-primary')) . ' text-white" style="border-top-left-radius: 0.5rem; border-top-right-radius: 0.5rem; border-bottom: 3px solid white">
                        <div class="col py-3">
                            <h4 class="mb-0 fw-bold">' . $list['name'] . '</h4>
                            <p class="mb-0">' . $list['description'] . '</p>
                            <h5>
                                <span class="badge bg-light text-dark">
                                    '.getPredominantMonth($start_date, $end_date) .'
                                </span>
                                <span class="badge bg-secondary text-white">
                                    '. date('d/m/Y', strtotime($start_date)) .' até '. date('d/m/Y', strtotime($end_date)) .'
                                </span>
                            </h5>
                        </div>
                    </div>
                    <div class="row">
                        <table class="table table-dark table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center" scope="col">Data</th>
                                    <th class="text-center" scope="col">Nome</th>
                                    <th class="text-center" scope="col">Tipo</th>
                                    <th class="text-center" scope="col">Valor</th>
                                </tr>
                            </thead>
                            <tbody class="table-group-divider">
                                ';

                                    echo buildRow($temp_items);

                                echo '
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th class="text-center" scope="col" colspan="3">Total</th>
                                    <th class="text-center" scope="col">' . getTotalizer($temp_items) . '</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="row '. ($type_json['type'] == 'cost' ? 'bg-danger' : ($type_json['type'] == 'receivable' ? 'bg-success' : 'bg-primary')) . ' text-white" style="border-bottom-left-radius: 0.5rem; border-bottom-right-radius: 0.5rem;">
                        <div class="col mb-3">
                            <!-- somente design --->
                        </div>
                    </div>
               </div>
            ';
        }
    }
?>

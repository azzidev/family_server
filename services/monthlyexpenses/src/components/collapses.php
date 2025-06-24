<?php
    $start_date = $_GET['start'] ?? $_COOKIE['gm_start_date'];
    $end_date = $_GET['end'] ?? $_COOKIE['gm_end_date'];

    if(isset($_GET['start']) && isset($_GET['end'])) {
        include __DIR__ .'/../cmd/db.php';
        include __DIR__ .'/../helpers/ping-session.php';
        include __DIR__ .'/../helpers/number2currency.php';

        $stmt = $conn->prepare("SELECT 
            -SUM(
                CASE 
                WHEN JSON_UNQUOTE(JSON_EXTRACT(gl.type, '$.type')) = 'cost' 
                THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(gi.price, '$.price')) AS DECIMAL(10,2)) 
                ELSE 0 
                END
            ) AS cost,
            SUM(
                CASE 
                WHEN JSON_UNQUOTE(JSON_EXTRACT(gl.type, '$.type')) = 'receivable' 
                THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(gi.price, '$.price')) AS DECIMAL(10,2)) 
                ELSE 0 
                END
            ) AS receivable,
            (
                SUM(
                CASE 
                    WHEN JSON_UNQUOTE(JSON_EXTRACT(gl.type, '$.type')) = 'receivable' 
                    THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(gi.price, '$.price')) AS DECIMAL(10,2)) 
                    ELSE 0 
                END
                ) - 
                SUM(
                CASE 
                    WHEN JSON_UNQUOTE(JSON_EXTRACT(gl.type, '$.type')) = 'cost' 
                    THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(gi.price, '$.price')) AS DECIMAL(10,2)) 
                    ELSE 0 
                END
                )
            ) AS balance
            FROM gastosmensal_lists gl
            INNER JOIN gastosmensal_items gi ON gl._id = gi._id_list
            WHERE gl._id_user = :user_id
            AND JSON_UNQUOTE(JSON_EXTRACT(gl.period, '$.start')) <= :end_date
            AND JSON_UNQUOTE(JSON_EXTRACT(gl.period, '$.end')) >= :start_date;
        ");
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$conn) { die('Erro de conexão com o banco'); }

    $stmt = $conn->prepare("SELECT 
        gl._id AS list_id,
        gl.name AS list_name,
        JSON_UNQUOTE(JSON_EXTRACT(gl.type, '$.type')) AS list_type,
        SUM(CAST(JSON_EXTRACT(gi.price, '$.price') AS DECIMAL(10,2))) AS total_price,
        CASE 
            WHEN JSON_UNQUOTE(JSON_EXTRACT(gl.type, '$.type')) = 'receivable' THEN SUM(CAST(JSON_EXTRACT(gi.price, '$.price') AS DECIMAL(10,2)))
            WHEN JSON_UNQUOTE(JSON_EXTRACT(gl.type, '$.type')) = 'cost' THEN -SUM(CAST(JSON_EXTRACT(gi.price, '$.price') AS DECIMAL(10,2)))
            ELSE 0
        END AS balance
            FROM gastosmensal_lists gl
            INNER JOIN gastosmensal_items gi ON gl._id = gi._id_list
            WHERE gl._id_user = :user_id
                AND JSON_UNQUOTE(JSON_EXTRACT(gl.period, '$.start')) <= :end_date
                AND JSON_UNQUOTE(JSON_EXTRACT(gl.period, '$.end')) >= :start_date
        GROUP BY gl._id
        ORDER BY gl._id DESC;
    ");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if($totals){
        for($i = 0; $i < count($totals); $i++) {
            echo '
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading-collapse-lists-'.$i.'">
                        <button class="accordion-button '.($i == 0 ? 'bg-danger' : ($i == 1 ? 'bg-success' : 'bg-primary')).' text-white collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-lists-'.$i.'" aria-expanded="true" aria-controls="collapse-lists-'.$i.'">
                            '.($i == 0 ? 'Listas de custos' : ($i == 1 ? 'Listas de entradas' : 'Balanço geral')) . '
                        </button>
                    </h2>
                    <div id="collapse-lists-'.$i.'" class="accordion-collapse collapse" aria-labelledby="heading-collapse-lists-'.$i.'" data-bs-parent="#accordion-lists">
                        <div class="accordion-body">
                            <div class="text-end mt-3">
                                <h5><strong>'.($i == 0 ? 'Total de custos' : ($i == 1 ? 'Total de entradas' : 'Balanço geral')) . ': R$ '.number_format($totals[$i == 0 ? 'cost' : ($i == 1 ? 'receivable' : 'balance')], 2, ',', '.').'</strong></h5>
                            </div>
                            <table class="table table-sm table-dark table-hover">
                                <thead>
                                    <tr>
                                        <th scope="col">Nome</th>
                                        <th scope="col">Valor</th>
                                    </tr>
                                </thead>
                                <tbody class="table-group-divider">
                                    ';
                                    foreach($lists as $list) {
                                        if(($i == 0 && $list['list_type'] == 'cost') || ($i == 1 && $list['list_type'] == 'receivable') || ($i == 2)) {
                                            echo '
                                                <tr>
                                                    <td class="'. ($list['balance'] < 0 ? 'bg-danger-subtle' : 'bg-success-subtle') .'">'.$list['list_name'].'</td>
                                                    <td class="'. ($list['balance'] < 0 ? 'bg-danger-subtle' : 'bg-success-subtle') .'">'.number2currency( $i == 1 ? $list['total_price'] : $list['balance']).'</td>
                                                </tr>
                                            ';
                                        }
                                    }
                                    echo '
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            ';
        }
    }
?>
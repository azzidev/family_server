<?php
    include __DIR__ .'/../cmd/db.php';
    include __DIR__ .'/../helpers/get-period.php';
    include __DIR__ .'/../helpers/number2currency.php';
    if(!isset($_SESSION['user_id'])){
        include __DIR__ .'/../helpers/ping-session.php';
    }

    $start_date = $_GET['start'] ?? $_COOKIE['gm_start_date'];
    $end_date = $_GET['end'] ?? $_COOKIE['gm_end_date'];

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

    if($totals){
        for($i = 0; $i < count($totals); $i++) {
            echo '
                <div class="col-4">
                    <div class="card text-white '.($i == 0 ? 'bg-danger' : ($i == 1 ? 'bg-success' : 'bg-primary')) . ' mb-3">
                        <div class="card-body text-center">
                            <p class="small card-title">'.($i == 0 ? 'Total de custos' : ($i == 1 ? 'Total de entradas' : 'Balanço geral')) . '</p>
                            <h3 class="card-text fw-bold">' . number2currency($totals[$i == 0 ? 'cost' : ($i == 1 ? 'receivable' : 'balance')]) . '</h3>
                        </div>
                    </div>
                </div>
            ';
        }
    }
?>
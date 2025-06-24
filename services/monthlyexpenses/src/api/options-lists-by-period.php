<?php
    include __DIR__ .'/../cmd/db.php';
    include __DIR__ .'/../helpers/ping-session.php';
    $start_date = isset($_GET['start']) ? $_GET['start'] : $_COOKIE['gm_start_date'];
    $end_date = isset($_GET['end']) ? $_GET['end'] : $_COOKIE['gm_end_date'];

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

    foreach ($lists as $list) {
        echo '<option value="' . $list['_id'] . '">' . htmlspecialchars($list['name']) .'</option>';
    }
?>
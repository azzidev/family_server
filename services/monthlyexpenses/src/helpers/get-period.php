<?php
    if(isset($_COOKIE['gm_start_date']) && isset($_COOKIE['gm_end_date'])) {
        $start_date = $_COOKIE['gm_start_date'];
        $end_date = $_COOKIE['gm_end_date'];
    } else {
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        
        setcookie('gm_start_date', $start_date, time() + (86400 * 30), "/");
        setcookie('gm_end_date', $end_date, time() + (86400 * 30), "/");
    }
?>
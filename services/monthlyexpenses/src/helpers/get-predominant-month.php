<?php
    function getPredominantMonth($start, $end) {
        $startDate = new DateTime($start);
        $endDate = new DateTime($end);
        
        // Swap dates if start is after end
        if ($startDate > $endDate) {
            list($startDate, $endDate) = array($endDate, $startDate);
        }
        
        $months = array();
        $interval = new DateInterval('P1D'); // 1-day interval
        $period = new DatePeriod($startDate, $interval, $endDate->modify('+1 day')); // Include end date
        
        // Count days in each month
        foreach ($period as $date) {
            $month = $date->format('n'); // Month number (1-12)
            $months[$month] = ($months[$month] ?? 0) + 1;
        }
        
        if (empty($months)) {
            return null; // Return null for invalid range
        }
        
        // Find the month with the highest count
        arsort($months);
        $predominantMonth = key($months);
        
        // Return full month name in English
        $monthNames = array(
            1 => 'Janeiro',
            2 => 'Fevereiro',
            3 => 'Março',
            4 => 'Abril',
            5 => 'Maio',
            6 => 'Junho',
            7 => 'Julho',
            8 => 'Agosto',
            9 => 'Setembro',
            10 => 'Outubro',
            11 => 'Novembro',
            12 => 'Dezembro'
        );
        
        return $monthNames[$predominantMonth];
    }
?>
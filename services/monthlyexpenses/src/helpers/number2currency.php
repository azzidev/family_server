<?php
    function number2currency($number, $currency = 'R$') {
        return $currency .' '. number_format($number, 2, ',', '.');
    }
?>
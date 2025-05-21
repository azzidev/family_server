<?php
function getConfig() {
    // Detecta o ambiente com base no host
    $host = $_SERVER['HTTP_HOST'];

    // Define o caminho do arquivo JSON com base no ambiente
    if (strpos($host, 'localhost') !== false) {
        $configPath = __DIR__ . '/local.json'; // Ambiente local
    } else {
        $configPath = __DIR__ . '/production.json'; // Ambiente de produção
    }

    // Verifica se o arquivo existe
    if (!file_exists($configPath)) {
        die("Arquivo de configuração não encontrado: " . $configPath);
    }

    // Carrega o conteúdo do JSON
    $config = json_decode(file_get_contents($configPath), true);

    // Verifica se o JSON foi carregado corretamente
    if (json_last_error() !== JSON_ERROR_NONE) {
        die("Erro ao carregar o arquivo JSON: " . json_last_error_msg());
    }

    return $config;
}
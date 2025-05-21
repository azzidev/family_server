@echo off
echo Executando processamento de parcelas...
"C:\xampp\php\php.exe" "%~dp0process_installments.php"
echo.
echo Processamento concluído. Verifique o arquivo installments_log.txt para detalhes.
pause
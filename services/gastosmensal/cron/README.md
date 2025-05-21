# Processamento de Parcelas

Este diretório contém scripts para processamento automático de parcelas no sistema de gastos mensais.

## Funcionamento

O script `process_installments.php` faz o seguinte:

1. Busca todos os usuários que têm itens parcelados
2. Para cada usuário, encontra as listas mais recentes agrupadas por tipo (custo/entrada)
3. Verifica se há itens parcelados que precisam ser continuados no próximo período
4. Se necessário, cria uma nova lista para o próximo período
5. Cria os itens para a próxima parcela, evitando duplicidade

O script considera um período de um mês para trás até um mês para frente da data atual, garantindo que não sejam criadas parcelas muito distantes no futuro.

## Configuração do Cron Job

Para configurar o processamento automático de parcelas, adicione a seguinte linha ao seu crontab:

```
0 0 * * * php /caminho/completo/para/family_server/services/gastosmensal/cron/process_installments.php
```

Isso executará o script diariamente à meia-noite.

## Windows (Agendador de Tarefas)

No Windows, você pode usar o Agendador de Tarefas:

1. Abra o Agendador de Tarefas
2. Crie uma nova tarefa
3. Configure para executar diariamente
4. Adicione uma ação para executar:
   ```
   C:\xampp\php\php.exe C:\xampp\htdocs\family_server\services\gastosmensal\cron\process_installments.php
   ```

## Logs

Os logs de execução são armazenados em `installments_log.txt` neste mesmo diretório.
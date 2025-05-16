$(document).ready(function () {
    const $actionButton = $('#actionButton');
    const $actionMenu = $('#actionMenu');
    const $addItemButton = $('#addItemButton');
    const addItemModal = new bootstrap.Modal($('#addItemModal')[0]);
    const $isInstallment = $('#isInstallment');
    const $installmentFields = $('#installmentFields');
    const $currentInstallmentSwitch = $('#currentInstallmentSwitch');
    const $currentInstallment = $('#currentInstallment');
    const $addItemForm = $('#addItemForm');
    
    // Filtros de período
    const $startPeriodFilter = $('#start-period-filter');
    const $endPeriodFilter = $('#end-period-filter');
    const $listContainer = $('#list-container');
    const $statementContainer = $('#statement-container');
    const $costsAccordion = $('#collapseCosts .accordion-body');
    const $receivablesAccordion = $('#collapseReceivables .accordion-body');

    // Modal de criar lista
    const $addListButton = $('#addListButton');
    const addListModal = new bootstrap.Modal($('#addListModal')[0]);
    const $addListForm = $('#addListForm');
    const $listStartDate = $('#listStartDate');
    const $listEndDate = $('#listEndDate');
    const $itemList = $('#itemList');

    // Alterar tipo de input para date
    $listStartDate.attr('type', 'date');
    $listEndDate.attr('type', 'date');

    // Toggle floating menu
    $actionButton.on('click', function () {
        $actionMenu.toggleClass('show');
    });

    // Open Add Item Modal
    $addItemButton.on('click', function () {
        addItemModal.show();
    });

    // Open Add List Modal
    $addListButton.on('click', function () {
        addListModal.show();
    });

    // Toggle installment fields
    $isInstallment.on('change', function () {
        $installmentFields.css('display', $isInstallment.is(':checked') ? 'block' : 'none');
    });

    // Toggle current installment field
    $currentInstallmentSwitch.on('change', function () {
        const isChecked = $currentInstallmentSwitch.is(':checked');
        $currentInstallment.prop('disabled', !isChecked);
        if (!isChecked) {
            $currentInstallment.val(1);
        }
    });

    // Handle Add Item Form Submission
    $addItemForm.on('submit', function (e) {
        e.preventDefault();

        // Prepare data
        const formData = {
            list: $('#itemList').val(),
            name: $('#itemName').val(),
            date_buy: $('#itemDate').val(),
            price: parseFloat($('#itemPrice').val()),
            is_installment: $isInstallment.is(':checked'),
            installments: $isInstallment.is(':checked') ? parseInt($('#installments').val()) : null,
            current_installment: $currentInstallmentSwitch.is(':checked') ? parseInt($currentInstallment.val()) : 1
        };
        console.log('Dados enviados:', formData); // Log para depuração

        // Send data via AJAX
        $.ajax({
            url: './api/items.php',
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            success: function (response) {
                console.log('Resposta do servidor (adicionar item):', response);
                
                // Verificar se a resposta é uma string e tentar convertê-la para objeto
                if (typeof response === 'string') {
                    try {
                        response = JSON.parse(response);
                    } catch (e) {
                        console.error('Erro ao parsear resposta:', e);
                    }
                }
                
                if (response && response.success) {
                    // Atualizar a interface com o novo item
                    addNewItemToUI(response.item, formData);
                    
                    // Limpar o formulário
                    $addItemForm[0].reset();
                    $installmentFields.css('display', 'none');
                    
                    // Fechar o modal
                    addItemModal.hide();
                    
                    // Atualizar os totais
                    fetchFilteredItems();
                } else {
                    const errorMsg = response && response.error ? response.error : 'Erro desconhecido';
                    console.error('Erro ao adicionar item:', response);
                    alert('Erro ao adicionar item: ' + errorMsg);
                }
            },
            error: function (xhr) {
                alert('Erro ao adicionar item: ' + xhr.responseText);
            }
        });
    });

    // Handle Add List Form Submission
    $addListForm.on('submit', function (e) {
        e.preventDefault();

        // Obter as datas e formatar corretamente
        const startDate = $listStartDate.val();
        const endDate = $listEndDate.val();
        
        // Adicionar horários às datas (início às 00:00:00, fim às 23:59:59)
        const formattedStartDate = startDate + ' 00:00:00';
        const formattedEndDate = endDate + ' 23:59:59';

        // Prepare data
        const formData = {
            name: $('#listName').val(),
            description: $('#listDescription').val(),
            type: {
                type: $('#listType').val(),
                name: $('#listType').val() === 'cost' ? 'Custo' : 'Entrada',
                color: $('#listType').val() === 'cost' ? 'danger' : 'success'
            },
            period: {
                start: formattedStartDate,
                end: formattedEndDate
            }
        };
        console.log('Dados enviados para criar lista:', formData); // Log para depuração

        // Send data via AJAX
        $.ajax({
            url: './api/lists.php',
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            success: function (response) {
                console.log('Resposta do servidor (criar lista):', response);
                
                // Verificar se a resposta é uma string e tentar convertê-la para objeto
                if (typeof response === 'string') {
                    try {
                        response = JSON.parse(response);
                    } catch (e) {
                        console.error('Erro ao parsear resposta:', e);
                    }
                }
                
                if (response && response.success) {
                    // Adicionar a nova lista à interface
                    addNewListToUI(response.list);
                    
                    // Atualizar o dropdown de listas no modal de adicionar item
                    updateListDropdown(response.list);
                    
                    // Limpar o formulário
                    $addListForm[0].reset();
                    
                    // Fechar o modal
                    addListModal.hide();
                } else {
                    const errorMsg = response && response.error ? response.error : 'Erro desconhecido';
                    console.error('Erro ao criar lista:', response);
                    alert('Erro ao criar lista: ' + errorMsg);
                }
            },
            error: function (xhr) {
                alert('Erro ao criar lista: ' + xhr.responseText);
            }
        });
    });

    // Função para adicionar nova lista à interface
    function addNewListToUI(list) {
        // Verificar se a lista já existe no container
        if ($(`#list-${list._id}`).length > 0) {
            return;
        }
        
        // Criar o HTML da nova lista
        const listHtml = `
            <div class="col-md-6 col-sm-12 d-flex align-items-stretch" id="list-${list._id}">
                <div class="card mb-3 flex-fill">
                    <div class="card-header">
                        <h5 class="card-title">
                            ${list.name}
                            <span class="badge bg-${list.type.color} float-end">${list.type.name}</span>
                        </h5>
                        <p class="card-text">
                            Período: ${formatDate(list.period.start)} até ${formatDate(list.period.end)}<br>
                            Criado em: ${formatDateTime(list.date.created)}
                        </p>
                    </div>
                    <div class="card-body">
                        <p class="text-warning">Nenhum item encontrado para este período.</p>
                    </div>
                </div>
            </div>
        `;
        
        // Adicionar ao container de listas
        if ($listContainer.find('.row').length === 0) {
            $listContainer.html('<div class="row"></div>');
        }
        $listContainer.find('.row').append(listHtml);
        
        // Adicionar ao accordion apropriado
        const accordionHtml = `
            <div class="card mb-3" id="accordion-list-${list._id}">
                <div class="card-header">
                    <p class="card-text text-end mb-0">
                        <strong>Total da Lista: R$ 0,00</strong>
                    </p>
                    <h5 class="card-title">${list.name}</h5>
                    <p class="card-text">
                        Período: ${formatDate(list.period.start)} até ${formatDate(list.period.end)}
                    </p>
                </div>
            </div>
        `;
        
        if (list.type.type === 'cost') {
            $costsAccordion.append(accordionHtml);
        } else {
            $receivablesAccordion.append(accordionHtml);
        }
    }
    
    // Função para atualizar o dropdown de listas
    function updateListDropdown(list) {
        // Adicionar a nova lista ao dropdown
        $itemList.append(`<option value="${list._id}">${list.name}</option>`);
    }
    
    // Função para adicionar novo item à interface
    function addNewItemToUI(item, formData) {
        const listId = formData.list;
        const $listCard = $(`#list-${listId}`);
        
        if ($listCard.length === 0) {
            return;
        }
        
        const $cardBody = $listCard.find('.card-body');
        
        // Remover mensagem de "nenhum item encontrado"
        if ($cardBody.find('.text-warning').length > 0) {
            $cardBody.empty();
            
            // Adicionar tabela
            $cardBody.html(`
                <table class="table table-bordered mt-3">
                    <thead>
                        <tr>
                            <th style="max-width: 90px;">Data</th>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Total:</strong></td>
                            <td><strong>R$ 0,00</strong></td>
                        </tr>
                    </tfoot>
                </table>
            `);
        }
        
        // Adicionar o item à tabela
        const $tbody = $cardBody.find('tbody');
        const price = formData.price;
        const installments = formData.is_installment ? formData.installments : null;
        const currentInstallment = formData.current_installment || 1;
        const totalInstallments = installments ? price * installments : price;
        const totalCurrentInstallments = installments ? price * currentInstallment : price;
        
        const itemHtml = `
            <tr>
                <td>${formatDate(formData.date_buy)}</td>
                <td>${formData.name}</td>
                <td>${installments ? currentInstallment + '/' + installments : 'À vista'}</td>
                <td>
                    R$ ${formatCurrency(price)}
                    ${installments ? `<span class="text-muted">(${formatCurrency(totalCurrentInstallments)} - ${formatCurrency(totalInstallments)})</span>` : ''}
                </td>
            </tr>
        `;
        
        $tbody.append(itemHtml);
        
        // Atualizar o total
        updateListTotal($listCard);
        
        // Adicionar ao extrato
        addItemToStatement(formData);
    }
    
    // Função para adicionar item ao extrato
    function addItemToStatement(formData) {
        const listName = $itemList.find('option:selected').text();
        const listType = $itemList.find('option:selected').data('type') || 'cost';
        const badgeClass = listType === 'receivable' ? 'bg-success' : 'bg-danger';
        
        const statementHtml = `
            <ul class="list-group">
                <li class="list-group-item d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-bold">${formData.name}</div>
                        <small class="text-muted">${listName}</small>
                    </div>
                    <div class="text-end">
                        <span class="badge ${badgeClass}">R$ ${formatCurrency(formData.price)}</span><br>
                        <small class="text-muted">${formatDate(formData.date_buy)}</small>
                    </div>
                </li>
            </ul>
        `;
        
        $statementContainer.prepend(statementHtml);
    }
    
    // Função para atualizar o total de uma lista
    function updateListTotal($listCard) {
        let total = 0;
        
        $listCard.find('tbody tr').each(function() {
            const priceText = $(this).find('td:last').text().split('R$')[1].split('(')[0].trim();
            total += parseFloat(priceText.replace('.', '').replace(',', '.'));
        });
        
        $listCard.find('tfoot strong:last').text('R$ ' + formatCurrency(total));
    }

    // Função para buscar itens filtrados
    function fetchFilteredItems() {
        const startDate = $startPeriodFilter.val();
        const endDate = $endPeriodFilter.val();

        // Verifica se as datas estão preenchidas
        if (!startDate || !endDate) {
            alert('Por favor, preencha as datas de início e fim.');
            return;
        }

        // Envia a requisição AJAX para buscar os itens
        $.ajax({
            url: './api/items.php',
            method: 'GET',
            data: {
                start_date: startDate,
                end_date: endDate
            },
            success: function (response) {
                console.log('Resposta do servidor (itens):', response); // Log para depuração
                if (response.success) {
                    // Atualiza as listas
                    $listContainer.html(response.lists_html);
        
                    // Atualiza o extrato
                    $statementContainer.html(response.statement_html);
                    
                    // Atualiza os totais nos cards
                    if (response.totals) {
                        updateTotalsCards(response.totals);
                    }
                    
                    // Busca os dados para os accordions
                    fetchAccordionData(startDate, endDate);
                } else {
                    alert('Erro ao buscar itens: ' + response.error);
                }
            },
            error: function (xhr) {
                alert('Erro ao buscar itens: ' + xhr.responseText);
            }
        });
    }
    
    // Função para buscar dados dos accordions
    function fetchAccordionData(startDate, endDate) {
        $.ajax({
            url: './api/totals.php',
            method: 'GET',
            data: {
                start_date: startDate,
                end_date: endDate
            },
            success: function (response) {
                console.log('Resposta do servidor (accordions):', response); // Log para depuração
                if (response.success && response.accordions) {
                    updateAccordions(response.accordions);
                } else if (response.error) {
                    console.error('Erro ao buscar dados dos accordions:', response.error);
                }
            },
            error: function (xhr) {
                console.error('Erro ao buscar dados dos accordions:', xhr.responseText);
            }
        });
    }
    
    // Função para atualizar os cards de totais
    function updateTotalsCards(totals) {
        // Atualiza o card de custos
        $('.bg-danger .card-text').text('R$ ' + formatCurrency(totals.costs));
        
        // Atualiza o card de entradas
        $('.bg-success .card-text').text('R$ ' + formatCurrency(totals.receivables));
        
        // Atualiza o card de balanço
        $('.bg-primary .card-text').text('R$ ' + formatCurrency(totals.balance));
    }
    
    // Função para atualizar os accordions
    function updateAccordions(accordions) {
        // Atualiza o accordion de custos
        $costsAccordion.html(accordions.costs);
        
        // Atualiza o accordion de entradas
        $receivablesAccordion.html(accordions.receivables);
    }
    
    // Função para formatar valores monetários
    function formatCurrency(value) {
        return parseFloat(value).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    // Função para formatar data (DD/MM/YYYY)
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('pt-BR');
    }
    
    // Função para formatar data e hora (DD/MM/YYYY HH:MM:SS)
    function formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR');
    }

    // Adiciona evento de mudança nos filtros
    $startPeriodFilter.on('change', fetchFilteredItems);
    $endPeriodFilter.on('change', fetchFilteredItems);
});
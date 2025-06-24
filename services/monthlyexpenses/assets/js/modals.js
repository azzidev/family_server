const $addItemForm = $('#addItemForm');
const $addListForm = $('#addListForm');
const $isInstallment = $('#isInstallment');
const $installmentFields = $('#installmentFields');
const $currentInstallmentSwitch = $('#currentInstallmentSwitch');
const $currentInstallment = $('#currentInstallment');

$isInstallment.on('change', function () {
    $installmentFields.css('display', $isInstallment.is(':checked') ? 'block' : 'none');
});

$addItemForm.on('submit', function (e) {
    e.preventDefault();

    const isSignature = $('#isSignature').is(':checked');
    const isInstallment = $isInstallment.is(':checked');
    const installments = isInstallment ? parseInt($('#installments').val()) : null;
    const currentInstallment = $currentInstallmentSwitch.is(':checked') ? parseInt($currentInstallment.val()) : 1;
    const rawPrice = $('#itemPrice').val();
    const price = parseFloat(
        rawPrice
            .replace('R$', '')    // remove prefixo
            .replace(/\./g, '')   // remove separador de milhar
            .replace(',', '.')    // troca vírgula por ponto decimal
            .trim()
    );

    const formData = {
        list: $('#itemList').val(),
        name: $('#itemName').val(),
        date_buy: $('#itemDate').val(),
        price: price,
        is_signature: isSignature,
        is_installment: isInstallment,
        installments: isInstallment ? installments : null,
        current_installment: isInstallment ? currentInstallment : null
    };

    $.ajax({
        url: './src/api/items.php',
        method: 'POST',
        data: JSON.stringify(formData),
        contentType: 'application/json',
        success: function (response) {
            console.log('Resposta do servidor (adicionar item):', response);
            if (typeof response === 'string') {
                try {
                    response = JSON.parse(response);
                } catch (e) {
                    console.error('Erro ao parsear resposta:', e);
                }
            }

            if (response && response.success) {
                $addItemForm[0].reset();
                $installmentFields.css('display', 'none');
                $('#isSignature').prop('checked', false).closest('div').show();
                $('#isInstallment').prop('checked', false).closest('div').show();

                addItemModal.hide();

                const picker = $('input[name="period-filter"]').data('daterangepicker');
                reloadViewPort(picker.startDate.format('YYYY-MM-DD'), picker.endDate.format('YYYY-MM-DD'));
            } else {
                const errorMsg = response && response.error ? response.error : 'Erro desconhecido';
                toast('danger', 'Erro', 'Erro ao adicionar item: ' + errorMsg, 5000);
            }
        },
        error: function (xhr) {
            toast('danger', 'Erro', 'Erro ao adicionar item: ' + xhr.responseText, 5000);
        }
    });
});

$addListForm.on('submit', function (e) {
    e.preventDefault();

    const name = $('.modal #listName').val();
    const description = $('.modal #listDescription').val();
    const type = $('.modal #btn-cost-list').is(':checked') ? 'cost' : 'receivable';
    const picker = $('.modal #period-list').data('daterangepicker');

    const data = {
        name: name,
        description: description,
        type: type,
        start_date: picker.startDate.format('YYYY-MM-DD'),
        end_date: picker.endDate.format('YYYY-MM-DD')
    };

    $.ajax({
        url: './src/api/lists.php',
        method: 'POST',
        data: JSON.stringify(data),
        contentType: 'application/json',
        success: function (response) {
            if (typeof response === 'string') {
                try { response = JSON.parse(response); } catch {}
            }
            if (response && response.success) {
                toast('success', 'Sucesso', 'Lista criada com sucesso!');
                $('#addListModal').modal('hide');
                $('#addListForm')[0].reset();
                reloadViewPort(picker.startDate.format('YYYY-MM-DD'), picker.endDate.format('YYYY-MM-DD')); 
            } else {
                toast('danger', 'Erro', response.error || 'Erro ao criar lista');
            }
        },
        error: function (xhr) {
            toast('danger', 'Erro', xhr.responseText || 'Erro ao criar lista');
        }
    });
});

$(function() {
    $('#addListModal').on('shown.bs.modal', function() {
        if (!$('.modal #period-list').data('daterangepicker')) {
            $('.modal #period-list').daterangepicker({
                opens: 'left',
                locale: {
                    format: "DD/MM/YYYY",
                    separator: " - ",
                    applyLabel: "Aplicar",
                    cancelLabel: "Cancelar",
                    fromLabel: "De",
                    toLabel: "Para",
                    daysOfWeek: ["Dom","Seg","Ter","Qua","Qui","Sex","Sab"],
                    monthNames: ["Janeiro","Fevereiro","Março","Abril","Maio","Junho","Julho","Agosto","Setembro","Outubro","Novembro","Dezembro"],
                }
            });
        }
    });

    Inputmask({
      alias: 'numeric',
      groupSeparator: '.',
      radixPoint: ',',
      prefix: 'R$ ',
      autoGroup: true,
      digits: 2,
      digitsOptional: false,
      placeholder: '0',
      rightAlign: false,
      removeMaskOnSubmit: false
    }).mask('#itemPrice');
});

function deleteItemById(itemId) {
    $.ajax({
        url: 'src/api/delete_item.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ item_id: itemId }),
        success: function (res) {
            $('#confirmActionModal').modal('hide');
            const picker = $('input[name="period-filter"]').data('daterangepicker');
            reloadViewPort(picker.startDate.format('YYYY-MM-DD'), picker.endDate.format('YYYY-MM-DD'));

            toast('success', 'Sucesso', 'Item excluído com sucesso!');
        }
    });
}

function deleteItemsByGroupId(groupId, itemId) {
    $.ajax({
        url: 'src/api/delete_item.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ group_id: groupId, item_id: itemId }),
        success: function (res) {
            $('#confirmActionModal').modal('hide');
            const picker = $('input[name="period-filter"]').data('daterangepicker');
            reloadViewPort(picker.startDate.format('YYYY-MM-DD'), picker.endDate.format('YYYY-MM-DD'));

            toast('success', 'Sucesso', 'Itens excluídos com sucesso!');
        }
    });
}
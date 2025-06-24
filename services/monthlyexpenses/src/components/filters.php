<?php
    $start_date = $_GET['start'] ?? $_COOKIE['gm_start_date'];
    $end_date = $_GET['end'] ?? $_COOKIE['gm_end_date'];
?>
<div class="row mt-5">
    <div class="col">
        <div class="filter-group" role="group" aria-label="Pré-definições de filtro">
            <input type="radio" name="filter-radio" class="btn-check" <?php echo isset($_COOKIE['last-month-filter']) == true ? 'checked' : '' ?> id="last-month-filter" autocomplete="off">
            <label class="btn btn-outline-secondary" for="last-month-filter">Mês passado</label>

            <input type="radio" name="filter-radio" class="btn-check" <?php echo isset($_COOKIE['current-month-filter']) == true ? 'checked' : '' ?> id="current-month-filter" autocomplete="off">
            <label class="btn btn-outline-secondary" for="current-month-filter">Mês atual</label>

            <input type="radio" name="filter-radio" class="btn-check" <?php echo isset($_COOKIE['next-month-filter']) == true ? 'checked' : '' ?> id="next-month-filter" autocomplete="off">
            <label class="btn btn-outline-secondary" for="next-month-filter">Mês seguinte</label>
        </div>
    </div>
    <div class="d-flex col">
        <input type="text" name="period-filter" value="<?=date('d/m/Y', strtotime($start_date))?> - <?=date('d/m/Y', strtotime($end_date))?>" class="form-control text-center ms-auto me-3" style="width: fit-content" id="period-filter">
        <div class="d-flex gap-3">
            <button class="btn btn-secondary rounded-pill" onclick="openListModal()">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-card-list" viewBox="0 0 16 16">
                    <path d="M14.5 3a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5zm-13-1A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2z"/>
                    <path d="M5 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 5 8m0-2.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5m0 5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5m-1-5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0M4 8a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0m0 2.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0"/>
                </svg>
            </button>
            <button class="btn btn-secondary rounded-pill" onclick="openItemModal()">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-arrow-down-up" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M11.5 15a.5.5 0 0 0 .5-.5V2.707l3.146 3.147a.5.5 0 0 0 .708-.708l-4-4a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L11 2.707V14.5a.5.5 0 0 0 .5.5m-7-14a.5.5 0 0 1 .5.5v11.793l3.146-3.147a.5.5 0 0 1 .708.708l-4 4a.5.5 0 0 1-.708 0l-4-4a.5.5 0 0 1 .708-.708L4 13.293V1.5a.5.5 0 0 1 .5-.5"/>
                </svg>
            </button>
            <button class="btn btn-danger rounded-pill" id="btnActionDeleteLists" onclick="openConfirmActionModal('Você está prestes a entrar no modo de exclusão', 'Atente-se, ao utilizar este modo, solicitamos cautela. As ações aqui realizadas não podem ser revertidas.', 'Entrar no modo', 'openDeleteListMode()')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16">
                    <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>
                    <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 .8-.98L6 .8A1.01,1.01,0,0,1,7,.8l3-.02A1,1,0,0,1,11,2h3.5a1,1,0,0,1,1,1zM4.118,4L4,4.059V13a1,1,0,0,0,1,1h6a1,1,0,0,0,1-1V4.059L11.882,4Z"/>
                    <path d="M2.5,3h11V2h-11Z"/>
                </svg>
            </button>
        </div>
        <div class="d-none d-flex gap-3 ms-3" id="btnsDeleteLists">
            <button onclick="closeDeleteListMode()" class="btn btn-small btn-secondary rounded-pill">
                Cancelar
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-x-lg" viewBox="0 0 16 16">
                    <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/>
                </svg>
            </button>
            <button onclick="openConfirmActionModal('Está ação é irreversível', 'Você não poderá recuperar a lista e itens após a exclusão. Certifique-se de que selecionou apenas as listas desejadas.', 'Excluir lista(s)', 'deleteLists()')" class="btn btn-small btn-danger rounded-pill">
                Excluir
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-ui-checks-grid" viewBox="0 0 16 16">
                    <path d="M2 10h3a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1m9-9h3a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-3a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1m0 9a1 1 0 0 0-1 1v3a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-3a1 1 0 0 0-1-1zm0-10a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h3a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2zM2 9a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h3a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2zm7 2a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-3a2 2 0 0 1-2-2zM0 2a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2zm5.354.854a.5.5 0 1 0-.708-.708L3 3.793l-.646-.647a.5.5 0 1 0-.708.708l1 1a.5.5 0 0 0 .708 0z"/>
                </svg>
            </button>
        </div>
    </div>
</div>
<script>    
    window.onload = function() {
        $(function() {
            $('input[name="period-filter"]').daterangepicker({
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
            }, function(start, end, label) {
                setCookie('gm_start_date', start.format('YYYY-MM-DD'), 30);
                setCookie('gm_end_date', end.format('YYYY-MM-DD'), 30);
                reloadViewPort(start.format('YYYY-MM-DD'), end.format('YYYY-MM-DD'));
                $('.modal #period-list').data('daterangepicker').setStartDate(start);
                $('.modal #period-list').data('daterangepicker').setEndDate(end);
            });

            $('input[name="filter-radio"]').on('change', function() {
                 $('input[name="filter-radio"]').each(function() {
                    if (!this.checked) {
                        emptyCookie(this.id);
                    }
                });

                let startDate, endDate;
                const today = new Date();
                switch ($(this).attr('id')) {
                    case 'last-month-filter':
                        startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                        endDate = new Date(today.getFullYear(), today.getMonth(), 0);
                        reloadViewPort(startDate.toISOString().split('T')[0], endDate.toISOString().split('T')[0]);
                        break;
                    case 'current-month-filter':
                        startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                        endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                        reloadViewPort(startDate.toISOString().split('T')[0], endDate.toISOString().split('T')[0]);
                        break;
                    case 'next-month-filter':
                        startDate = new Date(today.getFullYear(), today.getMonth() + 1, 1);
                        endDate = new Date(today.getFullYear(), today.getMonth() + 2, 0);
                        reloadViewPort(startDate.toISOString().split('T')[0], endDate.toISOString().split('T')[0]);
                        break;
                }

                setCookie($(this).attr('id'), true, 30);                
                setCookie('gm_start_date', startDate.toISOString().split('T')[0], 30);
                setCookie('gm_end_date', endDate.toISOString().split('T')[0], 30);
                $('input[name="period-filter"]').data('daterangepicker').setStartDate(startDate);
                $('input[name="period-filter"]').data('daterangepicker').setEndDate(endDate);
                $('.modal #period-list').data('daterangepicker').setStartDate(startDate);
                $('.modal #period-list').data('daterangepicker').setEndDate(endDate);
            });
        });
    };
</script>
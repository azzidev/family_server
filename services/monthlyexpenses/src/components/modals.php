<!-- Modal para Criar Lista -->
<div class="modal fade" id="addListModal" tabindex="-1" aria-labelledby="addListModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="addListForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="addListModalLabel">Criar Nova Lista</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="listName" class="form-label">Nome da Lista</label>
                        <input type="text" class="form-control" id="listName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="listDescription" class="form-label">Descrição</label>
                        <textarea class="form-control" id="listDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="btn-type-list" id="btn-cost-list" autocomplete="off" checked>
                            <label class="btn btn-outline-danger" for="btn-cost-list">Custo</label>

                            <input type="radio" class="btn-check" name="btn-type-list" id="btn-receivable-list" autocomplete="off">
                            <label class="btn btn-outline-success" for="btn-receivable-list">Entrada</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="period-list" class="form-label">Periodo</label>
                        <input type="text" class="form-control text-center" id="period-list" name="period-list" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Adicionar Item -->
<div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="addItemForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="addItemModalLabel">Adicionar Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="itemDate" class="form-label">Data</label>
                        <input type="date" class="form-control" id="itemDate" name="date_buy" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="itemList" class="form-label">Lista</label>
                        <select class="form-select" id="itemList" name="_id_list" required>
                            <option value="" disabled selected>Selecione uma lista</option>
                            <?php foreach ($lists as $list): ?>
                                <option value="<?= $list['_id'] ?>"><?= htmlspecialchars($list['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="itemName" class="form-label">Nome</label>
                        <input type="text" class="form-control" id="itemName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="itemPrice" class="form-label">Valor</label>
                        <input type="text" step="0.01" class="form-control" id="itemPrice" name="price" required>
                    </div>
                    <div class="d-flex gap-3">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="isSignature" name="is_signature" onchange="if (this.checked) {$('#isInstallment').prop('checked', false);$('#isInstallment').closest('div').hide();} else {$('#isInstallment').closest('div').show();}">
                            <label class="form-check-label" for="isSignature">Assinatura?</label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="isInstallment" name="is_installment" onchange="if (this.checked) {$('#isSignature').prop('checked', false);$('#isSignature').closest('div').hide();} else {$('#isSignature').closest('div').show();}">
                            <label class="form-check-label" for="isInstallment">Parcelado?</label>
                        </div>
                    </div>
                    <div id="installmentFields" style="display: none;">
                        <div class="mb-3">
                            <label for="installments" class="form-label">Número de Parcelas</label>
                            <input type="number" class="form-control" id="installments" name="installments" min="1">
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="currentInstallmentSwitch" name="current_installment_switch" checked>
                            <label class="form-check-label" for="currentInstallmentSwitch">Adicionar Parcela Atual?</label>
                        </div>
                        <div class="mb-3">
                            <label for="currentInstallment" class="form-label">Parcela Atual</label>
                            <input type="number" class="form-control" id="currentInstallment" name="current_installment" value="1" min="1">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal modal-lg" id="confirmActionModal" tabindex="-1" aria-labelledby="confirmActionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmActionModalLabel">${title}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="confirmActionModalBody">
                <p>${message}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirmActionButton">Confirmar</button>
            </div>
        </div>
    </div>
</div>
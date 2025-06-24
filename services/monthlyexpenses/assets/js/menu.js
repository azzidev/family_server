const $actionButton = $('#actionButton');
const $actionMenu = $('#actionMenu');
const addItemModal = new bootstrap.Modal($('#addItemModal')[0]);
const addListModal = new bootstrap.Modal($('#addListModal')[0]);

$actionButton.on('click', function () {
    $actionMenu.toggleClass('show');
});

function openItemModal() {
    addItemModal.show();
};

function openListModal() {
    addListModal.show();
};
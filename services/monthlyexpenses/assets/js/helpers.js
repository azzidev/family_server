$(document).ready(function () {
    
  $('#masonry-grid').imagesLoaded(function () {
    $('#masonry-grid').masonry({
        itemSelector: '.masonry-item',
        percentPosition: true
    });
})
});

function toast(type, title, message, hide = true, delay = 5000) {
    const id = `toast-${Date.now()}-${Math.floor(Math.random() * 1000)}`;
    const toastHTML = `
        <div id="${id}" class="toast" role="alert" aria-live="assertive" aria-atomic="true"
             style="position: absolute; top: 10px; right: 10px; z-index: 1055; min-width: 250px;">
            <div class="toast-header bg-${type} text-white justify-content-between">
                <strong class="me-auto">${title}</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        </div>
    `;

    const $toast = $(toastHTML);
    $('body').append($toast);
    $toast.fadeIn(200);

    if (hide) {
        setTimeout(() => {
            $toast.fadeOut(200, () => {
                $toast.remove();
            });
        }, delay);
    }
}

function setCookie(name, value, days) {
    let expires = "";
    if (days) {
        let date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + (value || "") + expires + "; path=/";
}

function emptyCookie(name) {
    document.cookie = name + "=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
}

function showTotalsSkeleton() {
    $('#totals-skeleton').show();
    $('#totals-container').hide();
}
function hideTotalsSkeleton() {
    $('#totals-skeleton').hide();
    $('#totals-container').show();
}

function showCollapsesSkeleton() {
    $('#collapses-skeleton').show();
    $('#collapses-container').hide();
}
function hideCollapsesSkeleton() {
    $('#collapses-skeleton').hide();
    $('#collapses-container').show();
}

function showListsSkeleton() {
    $('#lists-skeleton').show();
    $('#lists-container').hide();
}
function hideListsSkeleton() {
    $('#lists-skeleton').hide();
    $('#lists-container').show();
}

function reloadViewPort(start, end) {
    $(".viewport-totalizer").html("");
    $(".viewport-collapses").html("");
    $(".viewport-lists").html("");

    showTotalsSkeleton();
    const totalsPromise = $.ajax({
        url: "src/components/totals.php",
        type: "GET",
        data: {
            start: start,
            end: end
        },
        async: true
    });

    showCollapsesSkeleton();
    const collapsesPromise = $.ajax({
        url: "src/components/collapses.php",
        type: "GET",
        data: {
            start: start,
            end: end
        },
        async: true
    });

    showListsSkeleton()
    const listsPromise = $.ajax({
        url: "src/components/lists.php",
        type: "GET",
        data: {
            start: start,
            end: end
        },
        async: true
    });

    const optionsListsByPeriodPromisse = $.ajax({
        url: "src/api/options-lists-by-period.php",
        type: "GET",
        data: {
            start: start,
            end: end
        },
        async: true
    });

    Promise.all([totalsPromise, collapsesPromise, listsPromise, optionsListsByPeriodPromisse])
    .then(function(responses) {
        setTimeout(function(){
            $(".viewport-totalizer").append(responses[0]);
            hideTotalsSkeleton()
            $(".viewport-collapses").append(responses[1]);
            hideCollapsesSkeleton();
            $(".viewport-lists").append(responses[2]);
            hideListsSkeleton();
            $('#itemList').html(responses[3]);
 
            $('#masonry-grid').masonry('reloadItems').masonry('layout');
        }, 350)
    })
    .catch(function(error) {
        console.error("Erro ao carregar os componentes:", error);
        toast("danger", "Erro", "Ocorreu um erro ao carregar os dados.");
    });
}

function openConfirmActionModal(title, message, action, callback){
    $('#confirmActionModalLabel').text(title);
    $('#confirmActionModalBody').html("<p>"+message+"</p>");
    $('#confirmActionButton').text(action);
    $('#confirmActionButton').attr("onclick", callback);
    

    const confirmActionModal = new bootstrap.Modal($('#confirmActionModal')[0]);
    confirmActionModal.show();
}

const selectedItems = new Set();
function openDeleteListMode() {
  const confirmActionModal = bootstrap.Modal.getOrCreateInstance('#confirmActionModal');
  confirmActionModal.hide();

  $('#btnActionDeleteLists').addClass('d-none');
  $('#btnsDeleteLists').removeClass('d-none');

  $(document).off('click.deleteMode').on('click.deleteMode', '.masonry-item', function () {
    const key = $(this).data('key');

    if ($(this).hasClass('selected')) {
      $(this).removeClass('selected');
      selectedItems.delete(key);
    } else {
      $(this).addClass('selected');
      selectedItems.add(key);
    }

    console.log('Selecionados:', Array.from(selectedItems));
  });
}

function closeDeleteListMode() {
  $('.masonry-item').removeClass('selected');
  selectedItems.clear();

  $('#btnActionDeleteLists').removeClass('d-none');
  $('#btnsDeleteLists').addClass('d-none');

  $(document).off('click.deleteMode');
}

function deleteLists(){
    if (selectedItems.size === 0) {
      alert('Nenhuma lista selecionada!');
      return;
    }

    const ids = Array.from(selectedItems);
}
<?php
    include 'src/helpers/ping-session.php';
    include '../../root/cmd/config.php';
    include 'src/components/head.php';
?>
<body data-bs-theme="dark" data-theme="dark">
    <div class="viewport container my-3">
        <div id="totals-skeleton" class="skeleton" style="display:none;">
            <div class="skeleton-card"></div>
        </div>
        <div class="viewport-totalizer row g-3">
            <?php
                include 'src/components/totals.php';
            ?>
        </div>
        <div id="collapses-skeleton" class="skeleton" style="display:none;">
            <div class="skeleton-card" style="height: 160px"></div>
        </div>
        <div class="viewport-collapses accordion my-3" id="accordion-lists">
            <?php
                include 'src/components/collapses.php';
            ?>
        </div>
        <?php
            include 'src/components/filters.php';
        ?>
        <div id="lists-skeleton" class="skeleton" style="display:none;">
            <div class="skeleton-card"></div>
            <div class="skeleton-card"></div>
            <div class="skeleton-card"></div>
            <div class="skeleton-card"></div>
        </div>
        <div class="viewport-lists row my-3 g-3" id="masonry-grid">
            <?php
                include 'src/components/lists.php';
            ?>
        </div>
    </div>
    <?php
        //include 'src/components/float-menu.php';
        include 'src/components/modals.php';
    ?>
</body>
<?php
    include 'src/components/scripts.php';
?>
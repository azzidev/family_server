<?php
    include '../server/auth/check_auth.php';
    include '../server/db/connection.php';
    include '../root/cmd/config.php';
    include 'src/components/head.php';

    $config = getConfig();
    $services = [];
    try {
        $stmt = $conn->prepare("SELECT id, name, icon, path FROM services WHERE status = 1");
        $stmt->execute();
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error fetching services: " . $e->getMessage());
    }
?>

<body>
    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="text-center">
            <h1 class="mb-4">Selecione um Serviço</h1>
            <div id="service-list" class="d-flex flex-wrap justify-content-center">
                <?php if (empty($services)): ?>
                    <p class="text-danger">Nenhum serviço disponível.</p>
                <?php else: ?>
                    <?php foreach ($services as $service): ?>
                        <div class="service-icon text-center m-3">
                            <button class="btn btn-outline-primary rounded-circle select-service" data-service-path="<?= htmlspecialchars($service['path']) ?>" style="width: 100px; height: 100px; font-size: 24px; display: flex; justify-content: center; align-items: center;">
                                <img src="<?= htmlspecialchars($config['base_url'].$config['icon_path'].$service['icon']) ?>" alt="<?= htmlspecialchars($service['name']) ?>" style="width: 50px; height: 50px; filter: invert(1);">
                            </button>
                            <p class="mt-2"><?= htmlspecialchars($service['name']) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
        include 'src/components/scripts.php';
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Handle service selection
            document.querySelectorAll('.select-service').forEach(function (button) {
                button.addEventListener('click', function () {
                    const servicePath = this.getAttribute('data-service-path');
                    const baseUrl = "<?= $config['base_url'] ?>";
                    window.location.href = `${baseUrl}services/${servicePath}/`;
                });
            });
        });
    </script>
</body>
</html>
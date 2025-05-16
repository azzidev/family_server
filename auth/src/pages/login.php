<?php
    session_start(); // Inicie a sessão

    include '../components/head.php';
    include '../../../server/db/connection.php';

    // Fetch users directly from the database
    $users = [];
    try {
        $stmt = $conn->prepare("SELECT _id, user FROM users WHERE status = 1");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error fetching users: " . $e->getMessage());
    }

    // Function to generate initials from a name
    function getInitials($name) {
        $parts = explode(' ', $name);
        $initials = '';
        foreach ($parts as $part) {
            $initials .= strtoupper($part[0]);
        }
        return substr($initials, 0, 2); // Limit to 2 characters
    }

    // Check for error parameter
    $error = isset($_GET['error']) && $_GET['error'] === 'invalid_credentials';
?>

<body>
    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="text-center">
            <h1 class="mb-4">Quem está aí?</h1>
            <div id="user-list" class="d-flex flex-wrap justify-content-center">
                <?php if (empty($users)): ?>
                    <p class="text-danger">Não encontramos nenhum usuário.</p>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <div class="user-avatar text-center m-3">
                            <button class="btn btn-outline-primary rounded-circle select-user" data-username="<?= htmlspecialchars($user['user']) ?>" style="width: 100px; height: 100px; font-size: 24px; display: flex; justify-content: center; align-items: center;">
                                <?= htmlspecialchars(getInitials($user['user'])) ?>
                            </button>
                            <p class="mt-2"><?= htmlspecialchars($user['user']) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <form id="login-form" action="../../../server/auth/login_process.php" method="POST" style="display: none;">
                <input type="hidden" id="username" name="username">
                <div class="form-group mb-2">
                    <label for="password">Senha</label>
                    <input type="password" class="form-control rounded-pill" id="password" name="password" placeholder="Digite sua senha" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 rounded-pill btn-block">Entrar</button>
            </form>
        </div>
    </div>

    <!-- Toast for invalid credentials -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div id="errorToast" class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    Login falhou. Verifique suas credenciais.
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
    
    <?php
        include '../components/scripts.php';
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const userList = document.getElementById('user-list');
            const loginForm = document.getElementById('login-form');

            // Show toast if error exists
            <?php if ($error): ?>
                const errorToast = new bootstrap.Toast(document.getElementById('errorToast'));
                errorToast.show();

                // Remove the error parameter from the URL
                const url = new URL(window.location.href);
                url.searchParams.delete('error');
                window.history.replaceState({}, document.title, url.toString());
            <?php endif; ?>

            // Handle user selection
            document.querySelectorAll('.select-user').forEach(function (button) {
                button.addEventListener('click', function () {
                    const username = this.getAttribute('data-username');
                    document.getElementById('username').value = username;

                    // Highlight the selected user and fade out others
                    const avatars = userList.querySelectorAll('.user-avatar');
                    avatars.forEach(function (avatar) {
                        const button = avatar.querySelector('.select-user');
                        if (button.getAttribute('data-username') !== username) {
                            avatar.style.opacity = '0';
                            setTimeout(() => avatar.style.display = 'none', 200); // Smooth fade-out
                        } else {
                            avatar.style.opacity = '1';
                        }
                    });

                    // Show the login form after a short delay
                    setTimeout(() => {
                        loginForm.style.display = 'block';
                        loginForm.style.opacity = '1';
                    }, 200); // Match animation duration
                });
            });
        });
    </script>
</body>
</html>
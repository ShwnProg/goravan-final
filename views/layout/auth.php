<?php
require_once "../../autoload.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'GoraVan') ?></title>
    <?php include '../includes/shared/head.php'; ?>
</head>

<body>
    <script>
        window.GV_BASE_URL = <?= json_encode(rtrim(BASE_URL, '/')) ?>;
    </script>

    <?php if (isset($_SESSION['success'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                Swal.fire({
                    title: 'Success',
                    text: <?= json_encode($_SESSION['success']) ?>,
                    icon: 'success'
                });
            });
        </script>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <?php
        $authErrors = is_array($_SESSION['error']) ? $_SESSION['error'] : [$_SESSION['error']];
        $errorHtml = count($authErrors) > 1
            ? '<ul style="text-align:left;margin:0;padding-left:18px;">' . implode('', array_map(fn($error) => '<li>' . htmlspecialchars((string) $error, ENT_QUOTES) . '</li>', $authErrors)) . '</ul>'
            : htmlspecialchars((string) ($authErrors[0] ?? 'Something went wrong.'), ENT_QUOTES);
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                Swal.fire({
                    title: 'Please check the form',
                    html: <?= json_encode($errorHtml) ?>,
                    icon: 'error'
                });
            });
        </script>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <main class="auth-page">
        <div class="left-panel">
            <div class="left-panel__brand">
                <a href="../../index.php">
                    <img src="/images/logo_white.png" alt="GoraVan logo" class="brand-logo">
                </a>
                <span class="brand-name">Gora<span>Van</span></span>
            </div>

            <div class="left-panel__content">
                <h1><?= $left_headline ?? 'Your seat is<br><em>waiting.</em>' ?></h1>
                <p><?= htmlspecialchars($left_desc ?? '') ?></p>
            </div>

            <?php if (!empty($left_features)): ?>
                <ul class="left-panel__features">
                    <?php foreach ($left_features as $feature): ?>
                        <li>
                            <span class="feature-icon">
                                <i class="<?= htmlspecialchars($feature['icon']) ?>"></i>
                            </span>
                            <div>
                                <strong><?= htmlspecialchars($feature['title']) ?></strong>
                                <span><?= htmlspecialchars($feature['desc']) ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="right-panel">
            <?= $content ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../../assets/js/vanny-ui.js"></script>
    <script src="../../assets/js/password-strength.js"></script>

    <?php if (!empty($page_js)): ?>
        <script src="<?= $page_js ?>"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (window.initAuthPage) {
                    window.initAuthPage();
                }
            });
        </script>
    <?php endif; ?>
</body>

</html>

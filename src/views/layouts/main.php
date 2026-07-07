<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'MediConnect', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <?php require ROOT . '/src/views/partials/nav.php'; ?>

    <main class="container">
        <?php
        $flash = \controllers\BaseController::getFlash();
        if ($flash): ?>
            <div class="flash flash--<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?= $content ?>
    </main>

    <script src="/assets/js/app.js"></script>
</body>
</html>

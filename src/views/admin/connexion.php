<?php
$e = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div class="form-card" style="border-top:4px solid #1d6fa4;">
    <h1>Administration MediConnect</h1>

    <?php if ($error ?? null): ?>
        <div class="flash flash--error"><?= $e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/admin/connexion" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-group">
            <label for="email">Adresse e-mail administrateur</label>
            <input type="email" id="email" name="email" required autofocus>
        </div>

        <div class="form-group">
            <label for="mot_de_passe">Mot de passe</label>
            <input type="password" id="mot_de_passe" name="mot_de_passe" required>
        </div>

        <button type="submit" class="btn btn--primary btn--full">Accéder au panneau d'administration</button>
    </form>
</div>

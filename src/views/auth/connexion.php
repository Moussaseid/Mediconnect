<?php
$e = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div class="form-card">
    <h1>Connexion</h1>

    <?php if ($error ?? null): ?>
        <div class="flash flash--error"><?= $e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/connexion" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-group">
            <label for="email">Adresse e-mail</label>
            <input type="email" id="email" name="email" required autofocus>
        </div>

        <div class="form-group">
            <label for="mot_de_passe">Mot de passe</label>
            <input type="password" id="mot_de_passe" name="mot_de_passe" required>
        </div>

        <button type="submit" class="btn btn--primary btn--full">Se connecter</button>
    </form>

    <p style="margin-top:1rem;text-align:center;">
        Pas encore de compte patient ? <a href="/patient/inscription">Créer un compte</a>
    </p>
    <p style="text-align:center;">
        Médecin ? <a href="/medecin/demande">Demander un accès</a>
    </p>
    <p style="text-align:center;font-size:.85rem;">
        <a href="/mot-de-passe-oubli">Mot de passe oublié ?</a>
    </p>
    <p style="text-align:center;font-size:.85rem;color:#6b7280;">
        <a href="/admin/connexion">Accès administrateur</a>
    </p>
</div>

<?php
// TODO: CSRF — protection absente dans le projet
$e = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div class="form-card">
    <h1>Connexion patient</h1>

    <?php if ($error ?? null): ?>
        <div class="flash flash--error"><?= $e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/patient/connexion" novalidate>
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
        Pas encore de compte ? <a href="/patient/inscription">Créer un compte</a>
    </p>
    <p style="text-align:center;">
        Vous êtes médecin ? <a href="/medecin/demande">Faire une demande de compte</a>
    </p>
</div>

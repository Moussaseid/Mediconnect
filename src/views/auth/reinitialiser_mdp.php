<?php
$e = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div class="form-card" style="max-width:480px;">
    <h1>Réinitialiser le mot de passe</h1>

    <?php if ($tokenInvalide): ?>
        <div class="flash flash--error" style="margin-bottom:1rem;">
            Ce lien est invalide ou a expiré.
        </div>
        <p style="text-align:center;">
            <a href="/mot-de-passe-oubli" class="btn btn--primary">Faire une nouvelle demande</a>
        </p>

    <?php elseif ($succes): ?>
        <div class="flash flash--success" style="margin-bottom:1rem;">
            Mot de passe mis à jour avec succès !
        </div>
        <p style="text-align:center;">
            <a href="/connexion" class="btn btn--primary">Se connecter</a>
        </p>

    <?php else: ?>
        <?php if (isset($errors['global'])): ?>
            <div class="flash flash--error" style="margin-bottom:1rem;"><?= $e($errors['global']) ?></div>
        <?php endif; ?>

        <form method="POST" action="/reinitialiser-mdp" novalidate>
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="token" value="<?= $e($token) ?>">

            <div class="form-group">
                <label for="mot_de_passe">Nouveau mot de passe * <small>(8 caractères min.)</small></label>
                <input type="password" id="mot_de_passe" name="mot_de_passe"
                       autocomplete="new-password" autofocus
                       class="<?= isset($errors['mot_de_passe']) ? 'input-error' : '' ?>">
                <?php if (isset($errors['mot_de_passe'])): ?>
                    <span class="field-error"><?= $e($errors['mot_de_passe']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="mot_de_passe_confirm">Confirmer le mot de passe *</label>
                <input type="password" id="mot_de_passe_confirm" name="mot_de_passe_confirm"
                       autocomplete="new-password"
                       class="<?= isset($errors['mot_de_passe_confirm']) ? 'input-error' : '' ?>">
                <?php if (isset($errors['mot_de_passe_confirm'])): ?>
                    <span class="field-error"><?= $e($errors['mot_de_passe_confirm']) ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn--primary btn--full">Changer le mot de passe</button>
        </form>
    <?php endif; ?>

    <p style="margin-top:1.25rem;text-align:center;font-size:.9rem;">
        <a href="/connexion">Retour à la connexion</a>
    </p>
</div>

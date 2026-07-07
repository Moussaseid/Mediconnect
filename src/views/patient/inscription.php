<?php
$e = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div class="form-card">
    <h1>Créer un compte patient</h1>

    <form method="POST" action="/patient/inscription" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-group">
            <label for="nom">Nom *</label>
            <input type="text" id="nom" name="nom"
                   value="<?= $e($old['nom'] ?? '') ?>"
                   class="<?= isset($errors['nom']) ? 'input-error' : '' ?>">
            <?php if (isset($errors['nom'])): ?>
                <span class="field-error"><?= $e($errors['nom']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="prenom">Prénom *</label>
            <input type="text" id="prenom" name="prenom"
                   value="<?= $e($old['prenom'] ?? '') ?>"
                   class="<?= isset($errors['prenom']) ? 'input-error' : '' ?>">
            <?php if (isset($errors['prenom'])): ?>
                <span class="field-error"><?= $e($errors['prenom']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="email">Adresse e-mail *</label>
            <input type="email" id="email" name="email"
                   value="<?= $e($old['email'] ?? '') ?>"
                   class="<?= isset($errors['email']) ? 'input-error' : '' ?>">
            <?php if (isset($errors['email'])): ?>
                <span class="field-error"><?= $e($errors['email']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="mot_de_passe">Mot de passe * <small>(8 car. min, 1 majuscule, 1 chiffre)</small></label>
            <input type="password" id="mot_de_passe" name="mot_de_passe"
                   class="<?= isset($errors['mot_de_passe']) ? 'input-error' : '' ?>">
            <?php if (isset($errors['mot_de_passe'])): ?>
                <span class="field-error"><?= $e($errors['mot_de_passe']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="confirmation_mot_de_passe">Confirmation du mot de passe *</label>
            <input type="password" id="confirmation_mot_de_passe" name="confirmation_mot_de_passe"
                   class="<?= isset($errors['confirmation_mot_de_passe']) ? 'input-error' : '' ?>">
            <?php if (isset($errors['confirmation_mot_de_passe'])): ?>
                <span class="field-error"><?= $e($errors['confirmation_mot_de_passe']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="telephone">Téléphone</label>
            <input type="tel" id="telephone" name="telephone"
                   value="<?= $e($old['tel'] ?? '') ?>"
                   class="<?= isset($errors['telephone']) ? 'input-error' : '' ?>">
            <?php if (isset($errors['telephone'])): ?>
                <span class="field-error"><?= $e($errors['telephone']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="adresse">Adresse *</label>
            <input type="text" id="adresse" name="adresse"
                   value="<?= $e($old['adresse'] ?? '') ?>"
                   class="<?= isset($errors['adresse']) ? 'input-error' : '' ?>">
            <?php if (isset($errors['adresse'])): ?>
                <span class="field-error"><?= $e($errors['adresse']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="ville">Ville *</label>
            <input type="text" id="ville" name="ville"
                   value="<?= $e($old['ville'] ?? '') ?>"
                   class="<?= isset($errors['ville']) ? 'input-error' : '' ?>">
            <?php if (isset($errors['ville'])): ?>
                <span class="field-error"><?= $e($errors['ville']) ?></span>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn btn--primary btn--full">Créer mon compte</button>
    </form>

    <p style="margin-top:1rem;text-align:center;">
        Déjà inscrit ? <a href="/connexion">Se connecter</a>
    </p>
</div>

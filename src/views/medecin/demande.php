<?php
$e = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div class="form-card" style="max-width:600px;">
    <h1>Demande de compte médecin</h1>
    <p style="margin-bottom:1.25rem;color:#4b5563;">
        Remplissez ce formulaire pour soumettre votre candidature.
        L'administrateur examinera votre dossier et vous contactera.
    </p>

    <form method="POST" action="/medecin/demande" novalidate>
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
            <label for="specialisation">Spécialité *</label>
            <input type="text" id="specialisation" name="specialisation"
                   value="<?= $e($old['specialisation'] ?? '') ?>"
                   class="<?= isset($errors['specialisation']) ? 'input-error' : '' ?>">
            <?php if (isset($errors['specialisation'])): ?>
                <span class="field-error"><?= $e($errors['specialisation']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="email">Adresse e-mail professionnelle *</label>
            <input type="email" id="email" name="email"
                   value="<?= $e($old['email'] ?? '') ?>"
                   class="<?= isset($errors['email']) ? 'input-error' : '' ?>">
            <?php if (isset($errors['email'])): ?>
                <span class="field-error"><?= $e($errors['email']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="numero_rpps">Numéro RPPS * <small>(11 chiffres)</small></label>
            <input type="text" id="numero_rpps" name="numero_rpps"
                   value="<?= $e($old['rpps'] ?? '') ?>"
                   maxlength="11"
                   class="<?= isset($errors['numero_rpps']) ? 'input-error' : '' ?>">
            <?php if (isset($errors['numero_rpps'])): ?>
                <span class="field-error"><?= $e($errors['numero_rpps']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="adresse_cabinet">Adresse du cabinet *</label>
            <input type="text" id="adresse_cabinet" name="adresse_cabinet"
                   value="<?= $e($old['adresseCabinet'] ?? '') ?>"
                   class="<?= isset($errors['adresse_cabinet']) ? 'input-error' : '' ?>">
            <?php if (isset($errors['adresse_cabinet'])): ?>
                <span class="field-error"><?= $e($errors['adresse_cabinet']) ?></span>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn btn--primary btn--full">Soumettre ma demande</button>
    </form>
</div>

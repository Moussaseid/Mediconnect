<?php
$e   = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$val = fn(string $field) => $e((string)($medecin[$field] ?? ''));
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
    <h1>Modifier le profil médecin</h1>
    <a href="/admin/medecins" class="btn btn--secondary">Annuler</a>
</div>

<div class="form-card" style="max-width:640px;">
    <form method="POST"
          action="/admin/medecin/<?= (int)$medecin['id'] ?>/modifier"
          novalidate>
        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

        <h2 style="font-size:1rem;color:#6b7280;margin-bottom:1rem;">Informations personnelles</h2>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
                <label for="nom">Nom *</label>
                <input type="text" id="nom" name="nom"
                       value="<?= $val('nom') ?>"
                       class="<?= isset($errors['nom']) ? 'input-error' : '' ?>">
                <?php if (isset($errors['nom'])): ?>
                    <span class="field-error"><?= $e($errors['nom']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="prenom">Prénom *</label>
                <input type="text" id="prenom" name="prenom"
                       value="<?= $val('prenom') ?>"
                       class="<?= isset($errors['prenom']) ? 'input-error' : '' ?>">
                <?php if (isset($errors['prenom'])): ?>
                    <span class="field-error"><?= $e($errors['prenom']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-group">
            <label for="email">E-mail *</label>
            <input type="email" id="email" name="email"
                   value="<?= $val('email') ?>"
                   class="<?= isset($errors['email']) ? 'input-error' : '' ?>">
            <?php if (isset($errors['email'])): ?>
                <span class="field-error"><?= $e($errors['email']) ?></span>
            <?php endif; ?>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
                <label for="telephone">Téléphone</label>
                <input type="text" id="telephone" name="telephone"
                       value="<?= $val('telephone') ?>">
            </div>
            <div class="form-group">
                <label for="ville">Ville</label>
                <input type="text" id="ville" name="ville"
                       value="<?= $val('ville') ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="adresse">Adresse personnelle</label>
            <input type="text" id="adresse" name="adresse"
                   value="<?= $val('adresse') ?>">
        </div>

        <h2 style="font-size:1rem;color:#6b7280;margin:1.25rem 0 1rem;">Profil professionnel</h2>

        <div class="form-group">
            <label for="specialisation">Spécialité *</label>
            <input type="text" id="specialisation" name="specialisation"
                   value="<?= $val('specialisation') ?>"
                   class="<?= isset($errors['specialisation']) ? 'input-error' : '' ?>">
            <?php if (isset($errors['specialisation'])): ?>
                <span class="field-error"><?= $e($errors['specialisation']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="adresse_cabinet">Adresse du cabinet *</label>
            <input type="text" id="adresse_cabinet" name="adresse_cabinet"
                   value="<?= $val('adresse_cabinet') ?>">
        </div>

        <div class="form-group">
            <label for="duree_rdv">Durée d'un rendez-vous (minutes) *</label>
            <select id="duree_rdv" name="duree_rdv"
                    class="<?= isset($errors['duree_rdv']) ? 'input-error' : '' ?>">
                <?php foreach ([15, 30, 45, 60] as $d): ?>
                    <option value="<?= $d ?>"
                        <?= (int)$medecin['duree_rdv'] === $d ? 'selected' : '' ?>>
                        <?= $d ?> min
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['duree_rdv'])): ?>
                <span class="field-error"><?= $e($errors['duree_rdv']) ?></span>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn btn--primary btn--full">Enregistrer les modifications</button>
    </form>
</div>

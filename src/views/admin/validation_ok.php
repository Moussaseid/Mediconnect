<?php
$e = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div class="confirmation-box">
    <h1>Compte médecin créé</h1>
    <p>
        Le compte de <strong><?= $e($demande['prenom'] . ' ' . $demande['nom']) ?></strong>
        (<?= $e($demande['specialisation']) ?>) a été activé.
    </p>

    <p style="margin-top:1rem;">
        Communiquez ce mot de passe temporaire au médecin.<br>
        <strong>Il ne sera affiché qu'une seule fois.</strong>
    </p>
    <div class="tmp-password"><?= $e($mdpTemporaire) ?></div>

    <p style="color:#6b7280;font-size:.875rem;">
        L'adresse e-mail associée au compte est : <?= $e($demande['email']) ?>
    </p>

    <a href="/admin/dashboard" class="btn btn--primary" style="margin-top:1rem;">
        Retour au tableau de bord
    </a>
</div>

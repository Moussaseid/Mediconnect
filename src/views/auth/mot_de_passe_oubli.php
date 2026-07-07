<?php
$e = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div class="form-card" style="max-width:480px;">
    <h1>Mot de passe oublié</h1>
    <p style="margin-bottom:1.25rem;color:#4b5563;">
        Saisissez votre adresse e-mail. Si un compte lui est associé,
        un lien de réinitialisation vous sera fourni.
    </p>

    <?php if ($error): ?>
        <div class="flash flash--error" style="margin-bottom:1rem;"><?= $e($error) ?></div>
    <?php endif; ?>

    <?php if ($resetUrl !== null && $resetUrl !== '__not_found__'): ?>
        <div class="flash flash--success" style="margin-bottom:1rem;">
            <strong>Lien de réinitialisation :</strong><br>
            <a href="<?= $e($resetUrl) ?>"><?= $e($resetUrl) ?></a>
            <p style="margin-top:.5rem;font-size:.85rem;color:#374151;">
                Ce lien est valable <strong>1 heure</strong>.
                En production, il serait envoyé par e-mail.
            </p>
        </div>
    <?php elseif ($resetUrl === '__not_found__'): ?>
        <div class="flash flash--success" style="margin-bottom:1rem;">
            Si cette adresse e-mail est enregistrée, un lien de réinitialisation a été envoyé.
        </div>
    <?php else: ?>
        <form method="POST" action="/mot-de-passe-oubli" novalidate>
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">
                <label for="email">Adresse e-mail *</label>
                <input type="email" id="email" name="email"
                       value="<?= $e($_POST['email'] ?? '') ?>"
                       autocomplete="email" autofocus>
            </div>

            <button type="submit" class="btn btn--primary btn--full">Envoyer le lien</button>
        </form>
    <?php endif; ?>

    <p style="margin-top:1.25rem;text-align:center;font-size:.9rem;">
        <a href="/connexion">Retour à la connexion</a>
    </p>
</div>

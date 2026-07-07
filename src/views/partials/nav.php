<nav class="navbar">
    <a class="navbar__brand" href="/">MediConnect</a>
    <ul class="navbar__links">
        <?php if (isset($_SESSION['user'])): ?>
            <li>Bonjour, <?= htmlspecialchars($_SESSION['user']['nom'], ENT_QUOTES, 'UTF-8') ?></li>
            <li><a href="/deconnexion">Déconnexion</a></li>
        <?php else: ?>
            <li><a href="/connexion">Connexion</a></li>
            <li><a href="/patient/inscription">Créer un compte</a></li>
        <?php endif; ?>
    </ul>
</nav>

<?php
$e = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<h1>Mon espace patient</h1>
<p>Bienvenue, <?= $e($_SESSION['user']['nom']) ?> !</p>
<p><em>(Tableau de bord patient — à implémenter dans les prochaines issues.)</em></p>

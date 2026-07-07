<?php
$e = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<h1>Espace médecin</h1>
<p>Bienvenue, Dr <?= $e($_SESSION['user']['nom']) ?> !</p>
<p><em>(Tableau de bord médecin — à implémenter dans les prochaines issues.)</em></p>

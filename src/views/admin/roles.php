<?php
$e = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$rolesLabels = [
    'patient'        => 'Patient',
    'medecin'        => 'Médecin',
    'pharmacie'      => 'Pharmacie',
    'centre_sante'   => 'Centre de santé',
    'centre_analyse' => "Centre d'analyse",
];
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
    <h1>Attribution des rôles institutionnels</h1>
    <a href="/admin/dashboard" class="btn btn--secondary">Retour</a>
</div>
<p style="color:#4b5563;margin-bottom:1.5rem;">
    <?= (int)$total ?> utilisateur<?= $total > 1 ? 's' : '' ?> (hors admins).
    Modifiez le rôle de chaque utilisateur selon ses droits institutionnels.
</p>

<?php if (!empty($errors)): ?>
    <div class="flash flash--error" style="margin-bottom:1rem;">
        <?php foreach ($errors as $err): ?>
            <div><?= $e($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (empty($users)): ?>
    <p>Aucun utilisateur à gérer.</p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>E-mail</th>
                    <th>Rôle actuel</th>
                    <th>Statut</th>
                    <th>Changer le rôle</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= $e($u['prenom'] . ' ' . $u['nom']) ?></td>
                        <td><?= $e($u['email']) ?></td>
                        <td>
                            <span class="badge badge--<?= $u['role'] === 'patient' ? 'neutral' : 'info' ?>">
                                <?= $e($rolesLabels[$u['role']] ?? $u['role']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge--<?= $u['statut'] === 'actif' ? 'success' : 'danger' ?>">
                                <?= $e($u['statut']) ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" action="/admin/roles"
                                  style="display:flex;gap:.5rem;align-items:center;">
                                <input type="hidden" name="csrf_token"
                                       value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <select name="role"
                                        style="padding:.25rem .5rem;border:1px solid #d1d5db;border-radius:.375rem;">
                                    <?php foreach ($rolesLabels as $val => $label): ?>
                                        <option value="<?= $e($val) ?>"
                                            <?= $u['role'] === $val ? 'selected' : '' ?>>
                                            <?= $e($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn--sm btn--primary">Appliquer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="pagination" aria-label="Navigation pages" style="margin-top:1.5rem;">
        <span class="pagination__info" style="color:#6b7280;font-size:.9rem;">
            <?= (int)$total ?> utilisateur<?= $total > 1 ? 's' : '' ?>
            — page <?= (int)$page ?> / <?= (int)$totalPages ?>
        </span>
        <div class="pagination__controls" style="display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.5rem;">
            <?php if ($page > 1): ?>
                <a href="?page=1"
                   class="btn btn--sm btn--outline">« Premier</a>
                <a href="?page=<?= $page - 1 ?>"
                   class="btn btn--sm btn--outline">‹ Précédent</a>
            <?php endif; ?>

            <?php
            $debut = max(1, $page - 2);
            $fin   = min($totalPages, $page + 2);
            for ($i = $debut; $i <= $fin; $i++):
            ?>
                <a href="?page=<?= $i ?>"
                   class="btn btn--sm <?= $i === $page ? 'btn--primary' : 'btn--outline' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>"
                   class="btn btn--sm btn--outline">Suivant ›</a>
                <a href="?page=<?= $totalPages ?>"
                   class="btn btn--sm btn--outline">Dernier »</a>
            <?php endif; ?>
        </div>
    </nav>
    <?php endif; ?>
<?php endif; ?>

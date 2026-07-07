<?php
$e = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
    <h1>Patients inscrits</h1>
    <a href="/admin/dashboard" class="btn btn--secondary">Retour</a>
</div>
<p style="color:#4b5563;margin-bottom:1.25rem;"><?= (int)$total ?> patient(s) inscrit(s).</p>

<?php if (empty($patients)): ?>
    <p>Aucun patient inscrit.</p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>E-mail</th>
                    <th>Téléphone</th>
                    <th>Ville</th>
                    <th>Statut</th>
                    <th>Inscription</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($patients as $p): ?>
                    <tr>
                        <td><?= $e($p['prenom'] . ' ' . $p['nom']) ?></td>
                        <td><?= $e($p['email']) ?></td>
                        <td><?= $e($p['telephone'] ?? '—') ?></td>
                        <td><?= $e($p['ville'] ?? '—') ?></td>
                        <td>
                            <span class="badge badge--<?= $p['statut'] === 'actif' ? 'success' : 'danger' ?>">
                                <?= $e($p['statut']) ?>
                            </span>
                        </td>
                        <td><?= $e(date('d/m/Y', strtotime($p['created_at']))) ?></td>
                        <td>
                            <?php $nouveauStatut = $p['statut'] === 'actif' ? 'suspendu' : 'actif'; ?>
                            <form method="POST"
                                  action="/admin/utilisateur/<?= (int)$p['id'] ?>/suspendre"
                                  style="display:inline;">
                                <input type="hidden" name="csrf_token"
                                       value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="statut" value="<?= $e($nouveauStatut) ?>">
                                <input type="hidden" name="redirect_to" value="/admin/patients">
                                <button type="submit"
                                        class="btn btn--sm <?= $p['statut'] === 'actif' ? 'btn--danger' : 'btn--success' ?>">
                                    <?= $p['statut'] === 'actif' ? 'Suspendre' : 'Réactiver' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Pagination">
            <?php for ($pg = 1; $pg <= $pages; $pg++): ?>
                <?php if ($pg === $page): ?>
                    <span class="current"><?= $pg ?></span>
                <?php else: ?>
                    <a href="/admin/patients?page=<?= $pg ?>"><?= $pg ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

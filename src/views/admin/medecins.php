<?php
$e = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
    <h1>Médecins</h1>
    <a href="/admin/dashboard" class="btn btn--secondary">Retour</a>
</div>
<p style="color:#4b5563;margin-bottom:1.25rem;"><?= (int)$total ?> médecin(s) enregistré(s).</p>

<?php if (empty($medecins)): ?>
    <p>Aucun médecin enregistré.</p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Spécialité</th>
                    <th>E-mail</th>
                    <th>RPPS</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($medecins as $m): ?>
                    <tr>
                        <td><?= $e($m['prenom'] . ' ' . $m['nom']) ?></td>
                        <td><?= $e($m['specialisation']) ?></td>
                        <td><?= $e($m['email']) ?></td>
                        <td><?= $e($m['numero_rpps']) ?></td>
                        <td>
                            <span class="badge badge--<?= $m['statut'] === 'actif' ? 'success' : 'danger' ?>">
                                <?= $e($m['statut']) ?>
                            </span>
                        </td>
                        <td style="display:flex;gap:.4rem;flex-wrap:wrap;">
                            <a href="/admin/medecin/<?= (int)$m['id'] ?>/modifier"
                               class="btn btn--sm btn--primary">Modifier</a>

                            <?php $nouveauStatut = $m['statut'] === 'actif' ? 'suspendu' : 'actif'; ?>
                            <form method="POST"
                                  action="/admin/utilisateur/<?= (int)$m['id'] ?>/suspendre"
                                  style="display:inline;">
                                <input type="hidden" name="csrf_token"
                                       value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="statut" value="<?= $e($nouveauStatut) ?>">
                                <button type="submit"
                                        class="btn btn--sm <?= $m['statut'] === 'actif' ? 'btn--danger' : 'btn--success' ?>"
                                        data-confirm="<?= $m['statut'] === 'actif' ? 'Suspendre' : 'Réactiver' ?> ce médecin ?">
                                    <?= $m['statut'] === 'actif' ? 'Suspendre' : 'Réactiver' ?>
                                </button>
                            </form>

                            <form method="POST"
                                  action="/admin/medecin/<?= (int)$m['id'] ?>/supprimer"
                                  style="display:inline;">
                                <input type="hidden" name="csrf_token"
                                       value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit"
                                        class="btn btn--sm btn--danger"
                                        data-confirm="Supprimer définitivement <?= $e($m['prenom'] . ' ' . $m['nom']) ?> ?">
                                    Supprimer
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
                    <a href="/admin/medecins?page=<?= $pg ?>"><?= $pg ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

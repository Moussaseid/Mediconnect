<?php
$e = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.5rem;">
    <a href="/admin/patients" class="btn btn--secondary">Patients</a>
    <a href="/admin/medecins" class="btn btn--secondary">Médecins</a>
    <a href="/admin/roles"    class="btn btn--secondary">Rôles institutionnels</a>
</div>

<h1>Tableau de bord — Demandes médecins</h1>
<p style="margin-bottom:1.5rem;color:#4b5563;">
    <?= $total ?> demande(s) en attente de traitement.
</p>

<?php if (empty($demandes)): ?>
    <p>Aucune demande en attente.</p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Spécialité</th>
                    <th>E-mail</th>
                    <th>RPPS</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($demandes as $d): ?>
                    <tr>
                        <td><?= $e($d['prenom'] . ' ' . $d['nom']) ?></td>
                        <td><?= $e($d['specialisation']) ?></td>
                        <td><?= $e($d['email']) ?></td>
                        <td><?= $e($d['numero_rpps']) ?></td>
                        <td><?= $e(date('d/m/Y', strtotime($d['created_at']))) ?></td>
                        <td style="display:flex;gap:.5rem;">
                            <form method="POST"
                                  action="/admin/demande/<?= (int)$d['id'] ?>/valider">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit"
                                        class="btn btn--success"
                                        data-confirm="Valider la demande de <?= $e($d['prenom'] . ' ' . $d['nom']) ?> ?">
                                    Valider
                                </button>
                            </form>
                            <form method="POST"
                                  action="/admin/demande/<?= (int)$d['id'] ?>/rejeter">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit"
                                        class="btn btn--danger"
                                        data-confirm="Rejeter la demande de <?= $e($d['prenom'] . ' ' . $d['nom']) ?> ?">
                                    Rejeter
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
            <?php for ($p = 1; $p <= $pages; $p++): ?>
                <?php if ($p === $page): ?>
                    <span class="current"><?= $p ?></span>
                <?php else: ?>
                    <a href="/admin/dashboard?page=<?= $p ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

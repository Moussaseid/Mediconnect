<?php
namespace models;

class CentreAnalyseModel
{
    public function __construct(private \PDO $pdo) {}

    // ── Résolution du centre associé à l'utilisateur connecté ───────────────

    public function getCentreIdForUser(int $utilisateurId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT centre_id FROM gestionnaires_centre_analyse WHERE utilisateur_id = :uid LIMIT 1'
        );
        $stmt->execute([':uid' => $utilisateurId]);
        $row = $stmt->fetchColumn();
        return $row !== false ? (int) $row : null;
    }

    // ── Infos du centre ──────────────────────────────────────────────────────

    public function getCentre(int $centreId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM centres_analyse WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $centreId]);
        return $stmt->fetch() ?: null;
    }

    // ── CRUD analyses propres ────────────────────────────────────────────────

    public function lister(int $centreId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM centre_analyses WHERE centre_id = :cid ORDER BY nom ASC'
        );
        $stmt->execute([':cid' => $centreId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id, int $centreId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM centre_analyses WHERE id = :id AND centre_id = :cid LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':cid' => $centreId]);
        return $stmt->fetch() ?: null;
    }

    public function creer(int $centreId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO centre_analyses (centre_id, nom, description, prix, duree_minutes, disponible)
             VALUES (:cid, :nom, :desc, :prix, :duree, :dispo)'
        );
        $stmt->execute([
            ':cid'   => $centreId,
            ':nom'   => $data['nom'],
            ':desc'  => $data['description'] ?? null,
            ':prix'  => (float) ($data['prix'] ?? 0),
            ':duree' => (int)   ($data['dureeMinutes'] ?? 30),
            ':dispo' => isset($data['disponible']) ? (int) $data['disponible'] : 1,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function modifier(int $id, int $centreId, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE centre_analyses
             SET nom = :nom, description = :desc, prix = :prix,
                 duree_minutes = :duree, disponible = :dispo
             WHERE id = :id AND centre_id = :cid'
        );
        return $stmt->execute([
            ':nom'   => $data['nom'],
            ':desc'  => $data['description'] ?? null,
            ':prix'  => (float) ($data['prix'] ?? 0),
            ':duree' => (int)   ($data['dureeMinutes'] ?? 30),
            ':dispo' => isset($data['disponible']) ? (int) $data['disponible'] : 1,
            ':id'    => $id,
            ':cid'   => $centreId,
        ]);
    }

    public function toggleDisponible(int $id, int $centreId): ?bool
    {
        $analyse = $this->findById($id, $centreId);
        if ($analyse === null) return null;

        $newVal = $analyse['disponible'] ? 0 : 1;
        $stmt   = $this->pdo->prepare(
            'UPDATE centre_analyses SET disponible = :dispo WHERE id = :id AND centre_id = :cid'
        );
        $stmt->execute([':dispo' => $newVal, ':id' => $id, ':cid' => $centreId]);
        return (bool) $newVal;
    }

    public function supprimer(int $id, int $centreId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM centre_analyses WHERE id = :id AND centre_id = :cid'
        );
        $stmt->execute([':id' => $id, ':cid' => $centreId]);
        return $stmt->rowCount() > 0;
    }
}

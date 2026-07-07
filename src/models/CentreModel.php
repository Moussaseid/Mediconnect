<?php
namespace models;

/**
 * CentreModel — Gestion des centres de santé et d'analyse.
 *
 * Toutes les méthodes acceptent $type = 'sante' | 'analyse'.
 * Le nom de table est résolu via une liste blanche interne — jamais depuis
 * l'entrée utilisateur directement.
 */
class CentreModel
{
    private const TABLES = [
        'sante'   => 'centres_sante',
        'analyse' => 'centres_analyse',
    ];

    public function __construct(private \PDO $pdo) {}

    public function lister(string $type): array
    {
        $t = $this->table($type);
        return $this->pdo->query("SELECT * FROM $t ORDER BY nom")->fetchAll();
    }

    public function findById(string $type, int $id): ?array
    {
        $t    = $this->table($type);
        $stmt = $this->pdo->prepare("SELECT * FROM $t WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function creer(string $type, array $data): int
    {
        $t    = $this->table($type);
        $stmt = $this->pdo->prepare($this->sqlInsert($t, $type));
        $stmt->execute($this->bind($type, $data));
        return (int) $this->pdo->lastInsertId();
    }

    public function modifier(string $type, int $id, array $data): void
    {
        $t    = $this->table($type);
        $stmt = $this->pdo->prepare($this->sqlUpdate($t, $type));
        $stmt->execute(array_merge($this->bind($type, $data), [':id' => $id]));
    }

    public function supprimer(string $type, int $id): void
    {
        $t = $this->table($type);
        $this->pdo->prepare("DELETE FROM $t WHERE id = :id")->execute([':id' => $id]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function table(string $type): string
    {
        return self::TABLES[$type] ?? throw new \InvalidArgumentException("Type invalide: $type");
    }

    private function bind(string $type, array $data): array
    {
        $base = [
            ':nom'       => $data['nom'],
            ':adresse'   => $data['adresse']   ?? null,
            ':latitude'  => isset($data['latitude'])  ? (float) $data['latitude']  : null,
            ':longitude' => isset($data['longitude']) ? (float) $data['longitude'] : null,
            ':telephone' => $data['telephone'] ?? null,
            ':email'     => $data['email']     ?? null,
            ':actif'     => isset($data['actif']) ? (int) $data['actif'] : 1,
        ];

        if ($type === 'sante') {
            $base[':description'] = $data['description'] ?? null;
            $base[':specialites'] = $data['specialites'] ?? null;
            $base[':services']    = $data['services']    ?? null;
        }

        return $base;
    }

    private function sqlInsert(string $t, string $type): string
    {
        $cols = 'nom, adresse, latitude, longitude, telephone, email, actif';
        $vals = ':nom, :adresse, :latitude, :longitude, :telephone, :email, :actif';

        if ($type === 'sante') {
            $cols .= ', description, specialites, services';
            $vals .= ', :description, :specialites, :services';
        }

        return "INSERT INTO $t ($cols) VALUES ($vals)";
    }

    private function sqlUpdate(string $t, string $type): string
    {
        $set = 'nom = :nom, adresse = :adresse, latitude = :latitude,
                longitude = :longitude, telephone = :telephone, email = :email, actif = :actif';

        if ($type === 'sante') {
            $set .= ', description = :description, specialites = :specialites, services = :services';
        }

        return "UPDATE $t SET $set WHERE id = :id";
    }
}
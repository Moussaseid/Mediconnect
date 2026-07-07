<?php
namespace models;

class PharmacieModel
{
    public function __construct(private \PDO $pdo) {}

    public function lister(): array
    {
        return $this->pdo->query(
            "SELECT id_pharmacie AS id, nom, adresse, code_postal, ville, telephone, email,
                    latitude, longitude, actif, created_at, updated_at
             FROM pharmacies
             ORDER BY nom"
        )->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_pharmacie AS id, nom, adresse, code_postal, ville, telephone, email,
                    latitude, longitude, actif, created_at, updated_at
             FROM pharmacies WHERE id_pharmacie = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function creer(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO pharmacies
                 (nom, adresse, code_postal, ville, telephone, email, latitude, longitude, actif)
             VALUES
                 (:nom, :adresse, :code_postal, :ville, :telephone, :email, :latitude, :longitude, :actif)"
        );
        $stmt->execute($this->bind($data));
        return (int) $this->pdo->lastInsertId();
    }

    public function modifier(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE pharmacies
             SET nom = :nom, adresse = :adresse, code_postal = :code_postal,
                 ville = :ville, telephone = :telephone, email = :email,
                 latitude = :latitude, longitude = :longitude, actif = :actif
             WHERE id_pharmacie = :id"
        );
        $stmt->execute(array_merge($this->bind($data), [':id' => $id]));
    }

    public function supprimer(int $id): void
    {
        $this->pdo->prepare("DELETE FROM pharmacies WHERE id_pharmacie = :id")->execute([':id' => $id]);
    }

    private function bind(array $data): array
    {
        return [
            ':nom'         => $data['nom'],
            ':adresse'     => $data['adresse']    ?? null,
            ':code_postal' => $data['codePostal'] ?? null,
            ':ville'       => $data['ville']      ?? null,
            ':telephone'   => $data['telephone']  ?? null,
            ':email'       => $data['email']      ?? null,
            ':latitude'    => isset($data['latitude'])  ? (float) $data['latitude']  : null,
            ':longitude'   => isset($data['longitude']) ? (float) $data['longitude'] : null,
            ':actif'       => isset($data['actif'])     ? (int)   $data['actif']     : 1,
        ];
    }
}

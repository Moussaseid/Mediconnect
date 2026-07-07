<?php
namespace models;

class CentreSanteModel
{
    public function __construct(private \PDO $pdo) {}

    public function getCentreIdForUser(int $utilisateurId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT centre_id FROM gestionnaires_centre_sante WHERE utilisateur_id = :uid LIMIT 1'
        );
        $stmt->execute([':uid' => $utilisateurId]);
        $row = $stmt->fetchColumn();
        return $row !== false ? (int) $row : null;
    }

    public function getCentre(int $centreId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM centres_sante WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $centreId]);
        return $stmt->fetch() ?: null;
    }

    public function modifier(int $centreId, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE centres_sante
             SET nom = :nom, adresse = :adresse, telephone = :telephone, email = :email,
                 description = :description, specialites = :specialites, services = :services
             WHERE id = :id'
        );
        return $stmt->execute([
            ':nom'          => $data['nom'],
            ':adresse'      => $data['adresse']      ?? null,
            ':telephone'    => $data['telephone']    ?? null,
            ':email'        => $data['email']        ?? null,
            ':description'  => $data['description']  ?? null,
            ':specialites'  => $data['specialites']  ?? null,
            ':services'     => $data['services']     ?? null,
            ':id'           => $centreId,
        ]);
    }

    public function setPhoto(int $centreId, string $photoPath): bool
    {
        $stmt = $this->pdo->prepare('UPDATE centres_sante SET photo_path = :path WHERE id = :id');
        return $stmt->execute([':path' => $photoPath, ':id' => $centreId]);
    }
}

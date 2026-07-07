<?php
namespace models;

class PatientModel
{
    public function __construct(private \PDO $pdo) {}

    /**
     * Recherche un utilisateur par email (toutes rôles confondus).
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM utilisateurs WHERE email = :email LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Insère un nouveau patient dans utilisateurs.
     * Les données sont stockées brutes — l'échappement se fait à l'affichage.
     */
    public function creer(array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe_hash, telephone, adresse, ville, role, statut)
             VALUES (:nom, :prenom, :email, :hash, :telephone, :adresse, :ville, \'patient\', \'actif\')'
        );
        return $stmt->execute([
            ':nom'       => $data['nom'],
            ':prenom'    => $data['prenom'],
            ':email'     => $data['email'],
            ':hash'      => password_hash($data['mot_de_passe'], PASSWORD_BCRYPT),
            ':telephone' => $data['telephone'],
            ':adresse'   => $data['adresse'],
            ':ville'     => $data['ville'],
        ]);
    }
}

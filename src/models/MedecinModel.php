<?php
namespace models;

class MedecinModel
{
    public function __construct(private \PDO $pdo) {}

    /**
     * Vérifie si l'email existe déjà dans demandes_professionnels OU dans utilisateurs (rôle médecin).
     */
    public function emailDejaUtilise(string $email): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM demandes_professionnels WHERE email = :email LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            return true;
        }

        $stmt = $this->pdo->prepare(
            "SELECT u.id FROM utilisateurs u WHERE u.email = :email AND u.role = 'medecin' LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        return (bool) $stmt->fetch();
    }

    /**
     * Vérifie si le numéro RPPS existe déjà dans demandes_professionnels OU dans medecins.
     */
    public function rppsDejaUtilise(string $rpps): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM demandes_professionnels WHERE numero_rpps = :rpps LIMIT 1'
        );
        $stmt->execute([':rpps' => $rpps]);
        if ($stmt->fetch()) {
            return true;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id FROM medecins WHERE numero_rpps = :rpps LIMIT 1'
        );
        $stmt->execute([':rpps' => $rpps]);
        return (bool) $stmt->fetch();
    }

    /**
     * Insère une demande de création de compte médecin.
     */
    public function creerDemande(array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO demandes_professionnels (nom, prenom, specialisation, email, numero_rpps, adresse_cabinet)
             VALUES (:nom, :prenom, :specialisation, :email, :rpps, :adresse_cabinet)'
        );
        return $stmt->execute([
            ':nom'             => $data['nom'],
            ':prenom'          => $data['prenom'],
            ':specialisation'  => $data['specialisation'],
            ':email'           => $data['email'],
            ':rpps'            => $data['numero_rpps'],
            ':adresse_cabinet' => $data['adresse_cabinet'],
        ]);
    }

    /**
     * Retourne les demandes en_attente, paginées.
     */
    public function listerEnAttente(int $limite, int $offset): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM demandes_professionnels WHERE statut = 'en_attente'
             ORDER BY created_at DESC LIMIT :limite OFFSET :offset"
        );
        $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Compte le total des demandes en_attente (pour la pagination).
     */
    public function compterEnAttente(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM demandes_professionnels WHERE statut = 'en_attente'"
        );
        return (int) $stmt->fetchColumn();
    }

    /**
     * Retourne une demande par son ID.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM demandes_professionnels WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Met à jour le statut d'une demande.
     */
    public function mettreAJourStatut(int $id, string $statut): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE demandes_professionnels SET statut = :statut WHERE id = :id'
        );
        return $stmt->execute([':statut' => $statut, ':id' => $id]);
    }

    /**
     * Valide une demande : crée le compte utilisateur + médecin en transaction.
     * Retourne l'ID utilisateur créé.
     *
     * @param array  $demande      Ligne de demandes_professionnels
     * @param string $mdpTemporaire Mot de passe en clair généré (sera hashé ici)
     * @param int    $adminId      ID de l'admin qui valide (traçabilité)
     */
    public function approuver(array $demande, string $mdpTemporaire, int $adminId = 0): int
    {
        $this->pdo->beginTransaction();
        try {
            // 1. Créer le compte utilisateur
            $stmt = $this->pdo->prepare(
                "INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe_hash, role, statut)
                 VALUES (:nom, :prenom, :email, :hash, 'medecin', 'actif')"
            );
            $stmt->execute([
                ':nom'    => $demande['nom'],
                ':prenom' => $demande['prenom'],
                ':email'  => $demande['email'],
                ':hash'   => password_hash($mdpTemporaire, PASSWORD_BCRYPT),
            ]);
            $utilisateurId = (int) $this->pdo->lastInsertId();

            // 2. Créer le profil médecin avec traçabilité de validation
            $stmt = $this->pdo->prepare(
                'INSERT INTO medecins (utilisateur_id, specialisation, numero_rpps, adresse_cabinet, valide_par, valide_le)
                 VALUES (:uid, :spe, :rpps, :adresse, :valide_par, NOW())'
            );
            $stmt->execute([
                ':uid'        => $utilisateurId,
                ':spe'        => $demande['specialisation'],
                ':rpps'       => $demande['numero_rpps'],
                ':adresse'    => $demande['adresse_cabinet'],
                ':valide_par' => $adminId ?: null,
            ]);

            // 3. Marquer la demande comme approuvée
            $this->mettreAJourStatut((int) $demande['id'], 'approuve');

            $this->pdo->commit();
            return $utilisateurId;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}

<?php
namespace models;

class AdminModel
{
    public function __construct(private \PDO $pdo) {}

    /**
     * Recherche un utilisateur admin par email.
     */
    public function findAdminByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM utilisateurs WHERE email = :email AND role = 'admin' AND statut = 'actif' LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        return $stmt->fetch() ?: null;
    }

    // ------------------------------------------------------------------ #21

    /**
     * Retourne la liste paginée des patients inscrits.
     */
    public function listerPatients(int $limite, int $offset): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, nom, prenom, email, telephone, ville, statut, created_at
             FROM utilisateurs
             WHERE role = 'patient'
             ORDER BY created_at DESC
             LIMIT :limite OFFSET :offset"
        );
        $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Compte le total des patients inscrits.
     */
    public function compterPatients(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM utilisateurs WHERE role = 'patient'"
        );
        return (int) $stmt->fetchColumn();
    }

    // ------------------------------------------------------------------ #30

    /**
     * Retourne la liste paginée des médecins avec leur profil.
     */
    public function listerMedecins(int $limite, int $offset): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.nom, u.prenom, u.email, u.statut,
                    m.id AS medecin_id, m.specialisation, m.numero_rpps, m.adresse_cabinet
             FROM utilisateurs u
             JOIN medecins m ON m.utilisateur_id = u.id
             WHERE u.role = 'medecin'
             ORDER BY u.created_at DESC
             LIMIT :limite OFFSET :offset"
        );
        $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Compte le total des médecins.
     */
    public function compterMedecins(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM utilisateurs WHERE role = 'medecin'"
        );
        return (int) $stmt->fetchColumn();
    }

    /**
     * Retourne un médecin complet (utilisateur + profil medecin) par ID utilisateur.
     */
    public function findMedecinParId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.nom, u.prenom, u.email, u.telephone, u.adresse, u.ville, u.statut,
                    m.id AS medecin_id, m.specialisation, m.numero_rpps, m.adresse_cabinet,
                    m.latitude, m.longitude, m.duree_rdv
             FROM utilisateurs u
             JOIN medecins m ON m.utilisateur_id = u.id
             WHERE u.id = :id AND u.role = 'medecin'
             LIMIT 1"
        );
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Met à jour le profil d'un médecin (utilisateur + medecins).
     */
    public function modifierMedecin(int $userId, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE utilisateurs SET nom = :nom, prenom = :prenom, email = :email,
             telephone = :telephone, adresse = :adresse, ville = :ville
             WHERE id = :id'
        );
        $stmt->execute([
            ':nom'       => $data['nom'],
            ':prenom'    => $data['prenom'],
            ':email'     => $data['email'],
            ':telephone' => $data['telephone'],
            ':adresse'   => $data['adresse'],
            ':ville'     => $data['ville'],
            ':id'        => $userId,
        ]);

        $stmt = $this->pdo->prepare(
            'UPDATE medecins SET specialisation = :spe, adresse_cabinet = :cabinet, duree_rdv = :duree
             WHERE utilisateur_id = :uid'
        );
        $stmt->execute([
            ':spe'     => $data['specialisation'],
            ':cabinet' => $data['adresse_cabinet'],
            ':duree'   => (int) $data['duree_rdv'],
            ':uid'     => $userId,
        ]);
    }

    /**
     * Change le statut d'un utilisateur (actif / suspendu).
     */
    public function changerStatutUtilisateur(int $userId, string $statut): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE utilisateurs SET statut = :statut WHERE id = :id'
        );
        $stmt->execute([':statut' => $statut, ':id' => $userId]);
    }

    /**
     * Supprime un médecin (utilisateur + profil en cascade).
     */
    public function supprimerMedecin(int $userId): void
    {
        // ON DELETE CASCADE sur medecins.utilisateur_id supprime le profil automatiquement
        $stmt = $this->pdo->prepare('DELETE FROM utilisateurs WHERE id = :id');
        $stmt->execute([':id' => $userId]);
    }

    // ------------------------------------------------------------------ #29 gestion des rôles

    /**
     * Retourne les utilisateurs hors admin et médecin, paginés, triés alphabétiquement.
     * Les médecins ont leur propre page de gestion (/admin/medecins).
     */
    public function listerUtilisateursGestion(int $limite, int $offset): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, nom, prenom, email, role, statut
             FROM utilisateurs
             WHERE role NOT IN ('admin', 'medecin')
             ORDER BY nom, prenom
             LIMIT :limite OFFSET :offset"
        );
        $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Compte le total des utilisateurs gérables (hors admin et médecin).
     */
    public function compterUtilisateursGestion(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM utilisateurs WHERE role NOT IN ('admin', 'medecin')"
        );
        return (int) $stmt->fetchColumn();
    }

    /**
     * Change le rôle d'un utilisateur (hors admin — protégé en base).
     */
    public function attribuerRole(int $userId, string $role): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE utilisateurs SET role = :role WHERE id = :id AND role != 'admin'"
        );
        $stmt->execute([':role' => $role, ':id' => $userId]);
    }

    // ------------------------------------------------------------------ #20

    /**
     * Crée ou remplace un token de réinitialisation pour un email donné.
     */
    public function creerTokenReset(string $email, string $token): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM utilisateurs WHERE email = :email AND role != 'admin' LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        if (!$stmt->fetch()) {
            return false; // email introuvable
        }

        // Supprimer les anciens tokens
        $stmt = $this->pdo->prepare(
            'DELETE FROM reset_tokens WHERE email = :email'
        );
        $stmt->execute([':email' => $email]);

        // Insérer le nouveau token (expire dans 1 heure)
        $stmt = $this->pdo->prepare(
            'INSERT INTO reset_tokens (email, token, expire_le)
             VALUES (:email, :token, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
        );
        $stmt->execute([':email' => $email, ':token' => $token]);
        return true;
    }

    /**
     * Vérifie qu'un token de reset est valide et non expiré.
     * Retourne l'email associé ou null.
     */
    public function trouverTokenReset(string $token): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT email FROM reset_tokens
             WHERE token = :token AND expire_le > NOW()
             LIMIT 1'
        );
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();
        return $row ? $row['email'] : null;
    }

    /**
     * Réinitialise le mot de passe et supprime le token utilisé.
     */
    public function reinitialiserMotDePasse(string $token, string $nouveauMdp): bool
    {
        $email = $this->trouverTokenReset($token);
        if ($email === null) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE utilisateurs SET mot_de_passe_hash = :hash WHERE email = :email'
        );
        $stmt->execute([
            ':hash'  => password_hash($nouveauMdp, PASSWORD_BCRYPT),
            ':email' => $email,
        ]);

        $stmt = $this->pdo->prepare('DELETE FROM reset_tokens WHERE token = :token');
        $stmt->execute([':token' => $token]);
        return true;
    }
}

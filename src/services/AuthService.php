<?php
namespace services;

/**
 * Service d'authentification centralisé.
 * Interroge les utilisateurs dans l'ordre : admins → medecins → patients.
 * Retourne les données utilisateur + role, ou null si authentification échouée.
 */
class AuthService
{
    public function __construct(private \PDO $pdo) {}

    /**
     * Tente d'authentifier un utilisateur quel que soit son rôle.
     *
     * @return array{id: int, nom: string, email: string, role: string}|null
     */
    public function login(string $email, string $password): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, nom, prenom, email, mot_de_passe_hash, role, statut
             FROM utilisateurs
             WHERE email = :email
             LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }
        if ($user['statut'] !== 'actif') {
            return null;
        }
        if (!password_verify($password, $user['mot_de_passe_hash'])) {
            return null;
        }

        return [
            'id'         => (int) $user['id'],
            'nom'        => $user['nom'],
            'prenom'     => $user['prenom'] ?? '',
            'email'      => $user['email'],
            'role'       => $user['role'],
            'photo_path' => null,
        ];
    }

    /**
     * Retourne l'URL de redirection post-connexion selon le rôle.
     * Logue une anomalie si le rôle est inconnu.
     */
    public function urlParRole(string $role): string
    {
        return match ($role) {
            'patient' => '/patient/dashboard',
            'medecin' => '/medecin/dashboard',
            'admin'   => '/admin/dashboard',
            default   => $this->roleInconnu($role),
        };
    }

    private function roleInconnu(string $role): string
    {
        error_log('[MediConnect] AuthService: rôle inconnu "' . $role . '" — redirection vers /connexion');
        return '/connexion';
    }
}

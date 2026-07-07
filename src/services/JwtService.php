<?php
namespace services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

/**
 * JwtService — Génération et vérification des tokens JWT
 *
 * Payload : { sub, email, role, iat, exp }
 */
class JwtService
{
    private string $secret;
    private int    $expiresIn;
    private string $algorithm;

    public function __construct()
    {
        $cfg             = require ROOT . '/config/jwt.php';
        $this->secret    = $cfg['secret'];
        $this->expiresIn = $cfg['expires_in'];
        $this->algorithm = $cfg['algorithm'];
    }

    /**
     * Génère un JWT signé pour l'utilisateur donné.
     *
     * @param array $user  Ligne utilisateurs (id, email, role, nom, prenom)
     * @return string      Token signé
     */
    public function generer(array $user): string
    {
        $now = time();

        $payload = [
            'iss'   => 'mediconnect',
            'iat'   => $now,
            'exp'   => $now + $this->expiresIn,
            'sub'   => (int) $user['id'],
            'email' => $user['email'],
            'role'  => $user['role'],
            'nom'   => $user['nom']    ?? '',
            'prenom'=> $user['prenom'] ?? '',
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * Vérifie et décode un JWT.
     *
     * @param  string $token     Bearer token (sans "Bearer ")
     * @return object            Payload décodé
     * @throws \RuntimeException Si invalide ou expiré
     */
    public function verifier(string $token): object
    {
        try {
            return JWT::decode($token, new Key($this->secret, $this->algorithm));
        } catch (ExpiredException $e) {
            throw new \RuntimeException('Token expiré', 401);
        } catch (SignatureInvalidException $e) {
            throw new \RuntimeException('Signature invalide', 401);
        } catch (\Exception $e) {
            throw new \RuntimeException('Token invalide', 401);
        }
    }

    /**
     * Extrait le token Bearer du header Authorization.
     *
     * @return string|null  Token brut ou null si absent
     */
    public static function extraireToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
               ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
               ?? '';

        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return $m[1];
        }

        return null;
    }

    public function getExpiresIn(): int
    {
        return $this->expiresIn;
    }
}

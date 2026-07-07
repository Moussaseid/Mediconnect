<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * GeoUtils — Utilitaires de géolocalisation
 * ═══════════════════════════════════════════════════════════════════════════
 * Fonctions pour :
 *  - Calculer la distance entre deux points GPS (formule de Haversine)
 *  - Géocoder une adresse française via l'API BAN (Base Adresse Nationale)
 *  - Géocodage inverse (coordonnées → adresse)
 * ═══════════════════════════════════════════════════════════════════════════
 */

namespace Utils;

class GeoUtils
{
    /**
     * Rayon de la Terre en kilomètres
     */
    private const EARTH_RADIUS_KM = 6371;

    /**
     * URL de base de l'API BAN (Base Adresse Nationale)
     */
    private const BAN_API_URL = 'https://api-adresse.data.gouv.fr';

    /**
     * ───────────────────────────────────────────────────────────────────────
     * Calcule la distance en kilomètres entre deux points GPS
     * ───────────────────────────────────────────────────────────────────────
     * Utilise la formule de Haversine pour une précision correcte sur
     * de courtes distances (<1000 km).
     *
     * @param float $lat1 Latitude du point 1 (degrés décimaux)
     * @param float $lng1 Longitude du point 1 (degrés décimaux)
     * @param float $lat2 Latitude du point 2 (degrés décimaux)
     * @param float $lng2 Longitude du point 2 (degrés décimaux)
     * @return float Distance en kilomètres
     */
    public static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }

    /**
     * ───────────────────────────────────────────────────────────────────────
     * Géocode une adresse française via l'API BAN
     * ───────────────────────────────────────────────────────────────────────
     * Convertit une adresse textuelle en coordonnées GPS (latitude, longitude).
     *
     * @param string $adresse Numéro et nom de rue (ex: "10 rue de la Paix")
     * @param string|null $codePostal Code postal (ex: "75002")
     * @param string|null $ville Nom de la ville (ex: "Paris")
     * @return array|null ['lat' => float, 'lng' => float] ou null si échec
     */
    public static function geocodeAdresse(string $adresse, ?string $codePostal = null, ?string $ville = null): ?array
    {
        // Construire la requête complète
        $queryParts = [$adresse];
        if ($codePostal) $queryParts[] = $codePostal;
        if ($ville) $queryParts[] = $ville;
        $query = urlencode(implode(' ', $queryParts));

        $url = self::BAN_API_URL . "/search/?q={$query}&limit=1";

        $response = @file_get_contents($url);
        if ($response === false) {
            error_log("[GeoUtils] Erreur lors de l'appel à l'API BAN : $url");
            return null;
        }

        $data = json_decode($response, true);
        if (empty($data['features'])) {
            error_log("[GeoUtils] Aucun résultat pour l'adresse : $adresse $codePostal $ville");
            return null;
        }

        $coords = $data['features'][0]['geometry']['coordinates'];
        return [
            'lng' => (float) $coords[0],
            'lat' => (float) $coords[1]
        ];
    }

    /**
     * ───────────────────────────────────────────────────────────────────────
     * Géocodage inverse : coordonnées GPS → adresse lisible
     * ───────────────────────────────────────────────────────────────────────
     * Retourne l'adresse la plus proche des coordonnées fournies.
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @return string|null Adresse complète ou null si échec
     */
    public static function reverseGeocode(float $lat, float $lng): ?string
    {
        $url = self::BAN_API_URL . "/reverse/?lon={$lng}&lat={$lat}";

        $response = @file_get_contents($url);
        if ($response === false) {
            error_log("[GeoUtils] Erreur lors du géocodage inverse : lat=$lat, lng=$lng");
            return null;
        }

        $data = json_decode($response, true);
        if (empty($data['features'])) {
            return null;
        }

        return $data['features'][0]['properties']['label'] ?? null;
    }

    /**
     * ───────────────────────────────────────────────────────────────────────
     * Calcule le delta de latitude/longitude pour un rayon donné
     * ───────────────────────────────────────────────────────────────────────
     * Approximation : 1 degré de latitude ≈ 111 km
     * Utilisé pour créer une "bounding box" et optimiser les requêtes SQL.
     *
     * @param float $rayonKm Rayon de recherche en kilomètres
     * @return float Delta en degrés
     */
    public static function getDeltaDegres(float $rayonKm): float
    {
        return $rayonKm / 111;
    }

    /**
     * ───────────────────────────────────────────────────────────────────────
     * Valide des coordonnées GPS
     * ───────────────────────────────────────────────────────────────────────
     * Vérifie que latitude et longitude sont dans les plages valides.
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @return bool true si valide, false sinon
     */
    public static function validateCoordinates(float $lat, float $lng): bool
    {
        return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
    }
}

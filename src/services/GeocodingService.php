<?php
namespace services;

/**
 * Service de géocodage utilisant l'API Nominatim (OpenStreetMap).
 * Convertit une adresse en latitude/longitude.
 */
class GeocodingService
{
    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';

    /**
     * Géocode une adresse.
     * @param string $address Adresse à géocoder
     * @return array|null ['lat' => float, 'lon' => float] ou null si échec
     */
    public static function geocode(string $address): ?array
    {
        if (empty($address)) {
            return null;
        }

        $url = self::NOMINATIM_URL . '?' . http_build_query([
            'format' => 'json',
            'q' => $address,
            'limit' => 1,
            'countrycodes' => 'FR', // Limiter à la France pour précision
        ]);

        $context = stream_context_create([
            'http' => [
                'timeout' => 10, // Timeout 10s
                'user_agent' => 'MediConnect/1.0 (contact@example.com)', // User-Agent requis par Nominatim
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return null; // Erreur réseau
        }

        $data = json_decode($response, true);
        if (empty($data) || !isset($data[0]['lat'], $data[0]['lon'])) {
            return null; // Pas de résultats
        }

        return [
            'lat' => (float) $data[0]['lat'],
            'lon' => (float) $data[0]['lon'],
        ];
    }
}

<?php
namespace services;

/**
 * MongoLogService — Journalisation des événements auth dans MongoDB
 *
 * Collection : auth_logs (mediconnect_analytics)
 * Graceful degradation : si MongoDB est absent, log en fichier texte.
 */
class MongoLogService
{
    private ?\MongoDB\Collection $collection = null;
    private bool $mongoActif = false;

    public function __construct()
    {
        $uri    = ($_ENV['MONGO_URI'] ?? '') ?: (getenv('MONGO_URI') ?: 'mongodb://localhost:27017');
        $dbName = ($_ENV['MONGO_DB']  ?? '') ?: (getenv('MONGO_DB')  ?: 'mediconnect_analytics');

        if (!class_exists('\MongoDB\Client')) {
            return; // Extension PHP MongoDB absente — fallback fichier
        }

        try {
            $client               = new \MongoDB\Client($uri);
            $this->collection     = $client->selectCollection($dbName, 'auth_logs');
            $this->mongoActif     = true;
        } catch (\Exception $e) {
            // MongoDB inaccessible — fallback silencieux
        }
    }

    /**
     * Enregistre un événement d'authentification.
     *
     * @param string      $action   Voir IAuthLog.action dans interfaces.ts
     * @param int|null    $userId   ID utilisateur (null si login échoué)
     * @param string|null $email    Email tenté
     * @param string|null $role     Rôle de l'utilisateur
     * @param string      $statut   'succes' | 'echec'
     * @param array       $extra    Données supplémentaires (context)
     */
    public function log(
        string  $action,
        ?int    $userId,
        ?string $email,
        ?string $role,
        string  $statut = 'succes',
        array   $extra  = []
    ): void {
        $document = array_merge([
            'userId'    => $userId,
            'email'     => $email,
            'action'    => $action,
            'role'      => $role,
            'ip'        => $_SERVER['REMOTE_ADDR']     ?? null,
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'timestamp' => date('Y-m-d\TH:i:s\Z'),   // ISO string — remplacé par UTCDateTime si MongoDB actif
            'statut'    => $statut,
        ], $extra);

        if ($this->mongoActif && $this->collection !== null) {
            // Remplacer le timestamp par un vrai UTCDateTime pour MongoDB
            $document['timestamp'] = new \MongoDB\BSON\UTCDateTime();
            try {
                $this->collection->insertOne($document);
                return;
            } catch (\Exception $e) {
                // Fallback si insertion échoue
            }
        }

        // Fallback : log fichier texte
        $this->logFichier($document);
    }

    /**
     * Retourne les statistiques agrégées sur les 30 derniers jours.
     *
     * @return array{parJour:array, topIpsEchecs:array, totalSucces:int, totalEchecs:int, source:string}
     */
    public function statistiques(): array
    {
        $depuis = new \DateTime('-30 days');

        if ($this->mongoActif && $this->collection !== null) {
            try {
                $tsDebut = new \MongoDB\BSON\UTCDateTime($depuis->getTimestamp() * 1000);

                $pipeline = [
                    ['$match' => ['timestamp' => ['$gte' => $tsDebut]]],
                    ['$group' => [
                        '_id' => [
                            'jour'   => ['$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$timestamp']],
                            'statut' => '$statut',
                        ],
                        'nb' => ['$sum' => 1],
                    ]],
                    ['$sort' => ['_id.jour' => 1]],
                ];

                $jours = [];
                foreach ($this->collection->aggregate($pipeline) as $row) {
                    $date   = $row['_id']['jour']   ?? '';
                    $statut = $row['_id']['statut'] ?? '';
                    if (!isset($jours[$date])) $jours[$date] = ['connexions' => 0, 'echecs' => 0];
                    if ($statut === 'succes') $jours[$date]['connexions'] += $row['nb'];
                    else                      $jours[$date]['echecs']     += $row['nb'];
                }

                $parJour = [];
                foreach ($jours as $d => $v) {
                    $parJour[] = ['date' => $d, 'connexions' => $v['connexions'], 'echecs' => $v['echecs']];
                }

                $ipPipeline = [
                    ['$match' => ['statut' => 'echec', 'timestamp' => ['$gte' => $tsDebut]]],
                    ['$group' => ['_id' => '$ip', 'tentatives' => ['$sum' => 1]]],
                    ['$sort'  => ['tentatives' => -1]],
                    ['$limit' => 10],
                ];

                $topIps = [];
                foreach ($this->collection->aggregate($ipPipeline) as $row) {
                    $topIps[] = ['ip' => $row['_id'] ?? '?', 'tentatives' => $row['tentatives']];
                }

                return [
                    'parJour'      => $parJour,
                    'topIpsEchecs' => $topIps,
                    'totalSucces'  => array_sum(array_column($parJour, 'connexions')),
                    'totalEchecs'  => array_sum(array_column($parJour, 'echecs')),
                    'source'       => 'mongodb',
                ];
            } catch (\Exception $e) { /* fallback */ }
        }

        return $this->statistiquesFichier($depuis);
    }

    private function statistiquesFichier(\DateTime $depuis): array
    {
        $logs    = $this->lireLogsFichier(5000, null);
        $jours   = [];
        $ipEchec = [];

        foreach ($logs as $log) {
            try { $ts = new \DateTime($log['timestamp']); } catch (\Exception) { continue; }
            if ($ts < $depuis) continue;
            $date = $ts->format('Y-m-d');
            if (!isset($jours[$date])) $jours[$date] = ['connexions' => 0, 'echecs' => 0];
            if ($log['statut'] === 'succes') $jours[$date]['connexions']++;
            else {
                $jours[$date]['echecs']++;
                $ip = $log['ip'] ?? '?';
                $ipEchec[$ip] = ($ipEchec[$ip] ?? 0) + 1;
            }
        }

        ksort($jours);
        $parJour = [];
        foreach ($jours as $d => $v) {
            $parJour[] = ['date' => $d, 'connexions' => $v['connexions'], 'echecs' => $v['echecs']];
        }

        arsort($ipEchec);
        $topIps = [];
        foreach (array_slice($ipEchec, 0, 10, true) as $ip => $n) {
            $topIps[] = ['ip' => $ip, 'tentatives' => $n];
        }

        return [
            'parJour'      => $parJour,
            'topIpsEchecs' => $topIps,
            'totalSucces'  => array_sum(array_column($parJour, 'connexions')),
            'totalEchecs'  => array_sum(array_column($parJour, 'echecs')),
            'source'       => 'file',
        ];
    }

    /**
     * Lit les dernières entrées de log (MongoDB ou fichier).
     *
     * @param int         $limite  Nombre max d'entrées à retourner
     * @param string|null $action  Filtrer par action (null = toutes)
     * @return array<array>
     */
    public function lireLogs(int $limite = 50, ?string $action = null): array
    {
        if ($this->mongoActif && $this->collection !== null) {
            try {
                $filtre = $action !== null ? ['action' => $action] : [];
                $cursor = $this->collection->find(
                    $filtre,
                    [
                        'sort'  => ['timestamp' => -1],
                        'limit' => $limite,
                    ]
                );
                $logs = [];
                foreach ($cursor as $doc) {
                    $ts = $doc['timestamp'] ?? null;
                    $logs[] = [
                        'userId'    => $doc['userId']    ?? null,
                        'email'     => $doc['email']     ?? null,
                        'action'    => $doc['action']    ?? '',
                        'role'      => $doc['role']      ?? null,
                        'ip'        => $doc['ip']        ?? null,
                        'statut'    => $doc['statut']    ?? '',
                        'timestamp' => $ts instanceof \MongoDB\BSON\UTCDateTime
                            ? date('Y-m-d H:i:s', (int) ($ts->toDateTime()->getTimestamp()))
                            : (string) $ts,
                    ];
                }
                return $logs;
            } catch (\Exception $e) {
                // Fallback fichier
            }
        }

        return $this->lireLogsFichier($limite, $action);
    }

    /**
     * Lit les logs depuis le fichier texte (fallback).
     */
    private function lireLogsFichier(int $limite, ?string $action): array
    {
        $fichier = ROOT . '/var/logs/auth.log';
        if (!file_exists($fichier)) return [];

        $lignes = file($fichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lignes === false) return [];

        $lignes = array_reverse($lignes);
        $logs   = [];

        foreach ($lignes as $ligne) {
            if (count($logs) >= $limite) break;

            $pos  = strpos($ligne, ' {');
            if ($pos === false) continue;

            $horodatage = substr($ligne, 0, $pos);
            $json       = substr($ligne, $pos + 1);
            $doc        = json_decode($json, true);

            if (!is_array($doc)) continue;
            if ($action !== null && ($doc['action'] ?? '') !== $action) continue;

            $logs[] = [
                'userId'    => $doc['userId']    ?? null,
                'email'     => $doc['email']     ?? null,
                'action'    => $doc['action']    ?? '',
                'role'      => $doc['role']      ?? null,
                'ip'        => $doc['ip']        ?? null,
                'statut'    => $doc['statut']    ?? '',
                'timestamp' => $horodatage,
            ];
        }

        return $logs;
    }

    /**
     * Fallback : écriture dans var/logs/auth.log
     */
    private function logFichier(array $document): void
    {
        $logDir = ROOT . '/var/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $line = date('Y-m-d H:i:s') . ' '
              . json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
              . PHP_EOL;

        @file_put_contents($logDir . '/auth.log', $line, FILE_APPEND | LOCK_EX);
    }
}

<?php
namespace services;

class SlotService
{
    public static function genererSemaine(
        \PDO $pdo,
        int $medecinId,
        \DateTimeImmutable $lundiSemaine
    ): array {
        $duree = self::getDureeRdv($pdo, $medecinId);
        if ($duree <= 0) return [];

        $horaires = self::chargerHoraires($pdo, $medecinId);
        if (empty($horaires)) return [];

        $finSemaine = $lundiSemaine->modify('+6 days')->setTime(23, 59, 59);
        $indispos   = self::chargerIndispos($pdo, $medecinId, $lundiSemaine, $finSemaine);
        $rdvPoses   = self::chargerRdvPoses($pdo, $medecinId, $lundiSemaine, $finSemaine);
        $now        = new \DateTime();

        $joursNoms = ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        $moisNoms  = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
                      'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        $result = [];

        for ($i = 0; $i <= 6; $i++) {
            $jour        = $lundiSemaine->modify("+$i days");
            $jourSemaine = (int) $jour->format('N');

            if (!isset($horaires[$jourSemaine])) continue;

            $h      = $horaires[$jourSemaine];
            $cursor = new \DateTime($jour->format('Y-m-d') . ' ' . $h['heure_debut']);
            $limite = new \DateTime($jour->format('Y-m-d') . ' ' . $h['heure_fin']);
            $creneaux = [];

            while (true) {
                $slotFin = (clone $cursor)->modify("+{$duree} minutes");
                if ($slotFin > $limite) break;

                $disponible = ($cursor >= $now);

                if ($disponible) {
                    foreach ($indispos as $ind) {
                        if ($ind['debut'] < $slotFin && $ind['fin'] > $cursor) {
                            $disponible = false;
                            break;
                        }
                    }
                }

                if ($disponible) {
                    foreach ($rdvPoses as $rdv) {
                        if ($rdv['date_heure'] >= $cursor && $rdv['date_heure'] < $slotFin) {
                            $disponible = false;
                            break;
                        }
                    }
                }

                $creneaux[] = [
                    'debut'      => $cursor->format('H:i'),
                    'fin'        => $slotFin->format('H:i'),
                    'disponible' => $disponible,
                ];

                $cursor->modify("+{$duree} minutes");
            }

            if (!empty($creneaux)) {
                $dateKey = $jour->format('Y-m-d');
                $label   = $joursNoms[$jourSemaine] . ' '
                         . $jour->format('j') . ' '
                         . $moisNoms[(int) $jour->format('n')];

                $result[$dateKey] = ['label' => $label, 'creneaux' => $creneaux];
            }
        }

        return $result;
    }

    private static function chargerHoraires(\PDO $pdo, int $medecinId): array
    {
        $stmt = $pdo->prepare(
            'SELECT jour_semaine, heure_debut, heure_fin
             FROM horaires_semaine WHERE medecin_id = :mid'
        );
        $stmt->execute([':mid' => $medecinId]);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['jour_semaine']] = $row;
        }
        return $map;
    }

    private static function chargerIndispos(
        \PDO $pdo,
        int $medecinId,
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin
    ): array {
        $stmt = $pdo->prepare(
            'SELECT date_debut, date_fin FROM indisponibilite
             WHERE medecin_id = :mid
               AND date_fin   >= :debut
               AND date_debut <= :fin'
        );
        $stmt->execute([
            ':mid'   => $medecinId,
            ':debut' => $debut->format('Y-m-d H:i:s'),
            ':fin'   => $fin->format('Y-m-d H:i:s'),
        ]);
        return array_map(fn($r) => [
            'debut' => new \DateTime($r['date_debut']),
            'fin'   => new \DateTime($r['date_fin']),
        ], $stmt->fetchAll());
    }

    private static function chargerRdvPoses(
        \PDO $pdo,
        int $medecinId,
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin
    ): array {
        $stmt = $pdo->prepare(
            "SELECT date_heure FROM rendez_vous
             WHERE medecin_id = :mid
               AND date_heure BETWEEN :debut AND :fin
               AND statut NOT IN ('annule', 'refuse')"
        );
        $stmt->execute([
            ':mid'   => $medecinId,
            ':debut' => $debut->format('Y-m-d H:i:s'),
            ':fin'   => $fin->format('Y-m-d H:i:s'),
        ]);
        return array_map(
            fn($r) => ['date_heure' => new \DateTime($r['date_heure'])],
            $stmt->fetchAll()
        );
    }

    private static function getDureeRdv(\PDO $pdo, int $medecinId): int
    {
        $stmt = $pdo->prepare('SELECT duree_rdv FROM medecins WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $medecinId]);
        return (int) ($stmt->fetchColumn() ?: 30);
    }
}
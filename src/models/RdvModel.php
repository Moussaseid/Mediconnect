<?php
namespace models;

class RdvModel
{
    public function __construct(private \PDO $pdo) {}

    // ── Lecture ───────────────────────────────────────────────────────────────

    /**
     * Liste les RDV d'un patient, avec info médecin (nom/prénom/spécialisation).
     * Tri : futurs d'abord (ASC), puis passés (DESC).
     */
    public function listerParPatient(int $patientId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.id, r.patient_id, r.medecin_id, r.date_heure, r.statut,
                    r.annule_par, r.motif_annulation, r.created_at,
                    u.nom           AS medecin_nom,
                    u.prenom        AS medecin_prenom,
                    m.specialisation AS medecin_specialisation,
                    m.adresse_cabinet AS medecin_adresse
             FROM rendez_vous r
             JOIN medecins    m ON m.id            = r.medecin_id
             JOIN utilisateurs u ON u.id           = m.utilisateur_id
             WHERE r.patient_id = :patient_id
             ORDER BY
                 CASE WHEN r.date_heure >= NOW() THEN 0 ELSE 1 END,
                 r.date_heure ASC"
        );
        $stmt->execute([':patient_id' => $patientId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.*, u.nom AS medecin_nom, u.prenom AS medecin_prenom,
                    m.specialisation AS medecin_specialisation
             FROM rendez_vous r
             JOIN medecins    m ON m.id  = r.medecin_id
             JOIN utilisateurs u ON u.id = m.utilisateur_id
             WHERE r.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Liste tous les médecins actifs (pour le formulaire de prise de RDV).
     */
    public function listerMedecins(): array
    {
        return $this->pdo->query(
            "SELECT m.id, m.specialisation, m.adresse_cabinet, m.duree_rdv,
                    u.nom, u.prenom, u.email, u.telephone
             FROM medecins     m
             JOIN utilisateurs u ON u.id = m.utilisateur_id
             WHERE u.statut = 'actif'
             ORDER BY u.nom, u.prenom"
        )->fetchAll();
    }

    /**
     * Génère les créneaux disponibles pour un médecin à une date donnée.
     * Basé sur horaires_semaine et les RDV déjà pris (non annulés).
     */
    public function creneauxDisponibles(int $medecinId, string $date): array
    {
        // Récupérer la durée du RDV du médecin
        $stmtM = $this->pdo->prepare("SELECT duree_rdv FROM medecins WHERE id = :id LIMIT 1");
        $stmtM->execute([':id' => $medecinId]);
        $dureeMin = (int) ($stmtM->fetchColumn() ?: 30);

        // Jour de la semaine : 1=Lundi … 7=Dimanche
        $jourSemaine = (int) date('N', strtotime($date));

        $stmtH = $this->pdo->prepare(
            "SELECT heure_debut, heure_fin
             FROM horaires_semaine
             WHERE medecin_id = :id AND jour_semaine = :jour
             ORDER BY heure_debut"
        );
        $stmtH->execute([':id' => $medecinId, ':jour' => $jourSemaine]);
        $horaires = $stmtH->fetchAll();

        if (empty($horaires)) return [];

        // Créneaux déjà pris pour cette journée
        $stmtP = $this->pdo->prepare(
            "SELECT TIME_FORMAT(date_heure, '%H:%i') AS heure
             FROM rendez_vous
             WHERE medecin_id = :id AND DATE(date_heure) = :date AND statut != 'annule'"
        );
        $stmtP->execute([':id' => $medecinId, ':date' => $date]);
        $pris = array_flip(array_column($stmtP->fetchAll(), 'heure'));

        // Générer les créneaux
        $creneaux = [];
        foreach ($horaires as $h) {
            $debut = strtotime($date . ' ' . $h['heure_debut']);
            $fin   = strtotime($date . ' ' . $h['heure_fin']);

            while ($debut + $dureeMin * 60 <= $fin) {
                $heure  = date('H:i', $debut);
                $creneaux[] = [
                    'heureDebut' => $heure,
                    'heureFin'   => date('H:i', $debut + $dureeMin * 60),
                    'disponible' => !isset($pris[$heure]),
                ];
                $debut += $dureeMin * 60;
            }
        }

        return $creneaux;
    }

    // ── Écriture ──────────────────────────────────────────────────────────────

    /**
     * Crée un RDV. Statut = 'confirme' par défaut (pas de workflow d'approbation ici).
     * Retourne l'ID créé, ou null si le créneau est déjà pris.
     */
    public function creer(int $patientId, int $medecinId, string $dateHeure): ?int
    {
        // Vérifier la disponibilité (UNIQUE KEY rdv_unique empêche le doublon)
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO rendez_vous (patient_id, medecin_id, date_heure, statut)
                 VALUES (:patient_id, :medecin_id, :date_heure, 'confirme')"
            );
            $stmt->execute([
                ':patient_id' => $patientId,
                ':medecin_id' => $medecinId,
                ':date_heure' => $dateHeure,
            ]);
            return (int) $this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            // Contrainte UNIQUE violée → créneau déjà pris
            if ($e->getCode() === '23000') return null;
            throw $e;
        }
    }

    /**
     * Annule un RDV appartenant au patient donné.
     * Retourne false si le RDV n'existe pas ou n'appartient pas au patient.
     */
    public function annuler(int $id, int $patientId, string $motif): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE rendez_vous
             SET statut = 'annule', annule_par = 'patient', motif_annulation = :motif
             WHERE id = :id AND patient_id = :patient_id AND statut != 'annule'"
        );
        $stmt->execute([':id' => $id, ':patient_id' => $patientId, ':motif' => $motif]);
        return $stmt->rowCount() > 0;
    }

    // ── Côté médecin ──────────────────────────────────────────────────────────

    public function listerParMedecin(int $medecinId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.id, r.patient_id, r.medecin_id, r.date_heure, r.statut,
                    r.annule_par, r.motif_annulation, r.created_at,
                    u.nom    AS patient_nom,
                    u.prenom AS patient_prenom,
                    u.email  AS patient_email
             FROM rendez_vous r
             JOIN utilisateurs u ON u.id = r.patient_id
             WHERE r.medecin_id = :medecin_id
             ORDER BY r.date_heure ASC"
        );
        $stmt->execute([':medecin_id' => $medecinId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function listerHoraires(int $medecinId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, medecin_id, jour_semaine, heure_debut, heure_fin
             FROM horaires_semaine
             WHERE medecin_id = :medecin_id
             ORDER BY jour_semaine, heure_debut"
        );
        $stmt->execute([':medecin_id' => $medecinId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function ajouterHoraire(int $medecinId, int $jourSemaine, string $heureDebut, string $heureFin): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO horaires_semaine (medecin_id, jour_semaine, heure_debut, heure_fin)
             VALUES (:medecin_id, :jour, :debut, :fin)"
        );
        $stmt->execute([
            ':medecin_id' => $medecinId,
            ':jour'       => $jourSemaine,
            ':debut'      => $heureDebut,
            ':fin'        => $heureFin,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function supprimerHoraire(int $id, int $medecinId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM horaires_semaine WHERE id = :id AND medecin_id = :medecin_id"
        );
        $stmt->execute([':id' => $id, ':medecin_id' => $medecinId]);
        return $stmt->rowCount() > 0;
    }
}

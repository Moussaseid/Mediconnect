<?php
namespace models;

class PrescriptionModel
{
    public function __construct(private \PDO $pdo) {}

    /**
     * Liste toutes les ordonnances d'un patient, avec médecin et lignes.
     */
    public function listerParPatient(int $patientId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.id, p.patient_id, p.medecin_id, p.rdv_id,
                    p.date_prescription, p.validite_jours, p.created_at,
                    u.nom    AS medecin_nom,
                    u.prenom AS medecin_prenom,
                    m.specialisation AS medecin_specialisation
             FROM prescriptions  p
             JOIN medecins        m ON m.id  = p.medecin_id
             JOIN utilisateurs    u ON u.id  = m.utilisateur_id
             WHERE p.patient_id = :patient_id
             ORDER BY p.date_prescription DESC"
        );
        $stmt->execute([':patient_id' => $patientId]);
        $prescriptions = $stmt->fetchAll();

        if (empty($prescriptions)) return [];

        // Charger toutes les lignes en une seule requête
        $ids  = implode(',', array_column($prescriptions, 'id'));
        $stmtL = $this->pdo->query(
            "SELECT ol.id, ol.prescription_id, ol.medicament_id, ol.posologie,
                    ol.duree_jours, ol.quantite,
                    med.nom    AS medicament_nom,
                    med.forme  AS medicament_forme,
                    med.dosage AS medicament_dosage
             FROM ordonnance_lignes ol
             JOIN medicaments       med ON med.id_medicament = ol.medicament_id
             WHERE ol.prescription_id IN ($ids)
             ORDER BY ol.id"
        );
        $toutesLignes = $stmtL->fetchAll();

        $lignesParPrescription = [];
        foreach ($toutesLignes as $l) {
            $lignesParPrescription[(int) $l['prescription_id']][] = $l;
        }

        foreach ($prescriptions as &$p) {
            $p['lignes'] = $lignesParPrescription[(int) $p['id']] ?? [];
        }

        return $prescriptions;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, u.nom AS medecin_nom, u.prenom AS medecin_prenom,
                    m.specialisation AS medecin_specialisation
             FROM prescriptions  p
             JOIN medecins        m ON m.id  = p.medecin_id
             JOIN utilisateurs    u ON u.id  = m.utilisateur_id
             WHERE p.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $prescription = $stmt->fetch();
        if (!$prescription) return null;

        $stmtL = $this->pdo->prepare(
            "SELECT ol.*, med.nom AS medicament_nom, med.forme AS medicament_forme,
                    med.dosage AS medicament_dosage
             FROM ordonnance_lignes ol
             JOIN medicaments med ON med.id_medicament = ol.medicament_id
             WHERE ol.prescription_id = :id ORDER BY ol.id"
        );
        $stmtL->execute([':id' => $id]);
        $prescription['lignes'] = $stmtL->fetchAll();

        return $prescription;
    }
}

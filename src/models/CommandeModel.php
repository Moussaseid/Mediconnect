<?php
namespace models;

class CommandeModel
{
    // Transitions de statut autorisées pour le pharmacien
    private const TRANSITIONS = [
        'en_attente' => ['preparee', 'annulee'],
        'preparee'   => ['prete',    'annulee'],
        'prete'      => ['livree'],
    ];

    public function __construct(private \PDO $pdo) {}

    // ── Lecture ───────────────────────────────────────────────────────────────

    /**
     * Liste les commandes d'une pharmacie.
     * Inclut les lignes (médicaments) dans la réponse.
     * Tri : statuts actifs en premier (en_attente→preparee→prete), puis par date desc.
     */
    public function listerParPharmacie(int $pharmacieId, ?string $statut = null): array
    {
        $sql = "SELECT c.id, c.patient_id, c.pharmacie_id, c.mode_retrait,
                       c.adresse_livraison, c.notes, c.statut,
                       c.created_at, c.updated_at
                FROM commandes c
                WHERE c.pharmacie_id = :pharmacie_id";

        $params = [':pharmacie_id' => $pharmacieId];

        if ($statut !== null) {
            $sql .= ' AND c.statut = :statut';
            $params[':statut'] = $statut;
        }

        $sql .= " ORDER BY
                    CASE c.statut
                        WHEN 'en_attente' THEN 0
                        WHEN 'preparee'   THEN 1
                        WHEN 'prete'      THEN 2
                        ELSE 3
                    END,
                    c.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $commandes = $stmt->fetchAll();

        if (empty($commandes)) return [];

        // Charger les lignes en une seule requête
        $ids       = implode(',', array_column($commandes, 'id'));
        $stmtLignes = $this->pdo->query(
            "SELECT lc.id, lc.commande_id, lc.medicament_id, lc.quantite, lc.prix_achat,
                    m.nom   AS medicament_nom,
                    m.forme AS medicament_forme,
                    m.dosage AS medicament_dosage
             FROM lignes_commande lc
             JOIN medicaments m ON m.id_medicament = lc.medicament_id
             WHERE lc.commande_id IN ($ids)
             ORDER BY lc.id"
        );
        $toutesLignes = $stmtLignes->fetchAll();

        // Indexer les lignes par commande_id
        $lignesParCommande = [];
        foreach ($toutesLignes as $l) {
            $lignesParCommande[(int) $l['commande_id']][] = $l;
        }

        foreach ($commandes as &$c) {
            $c['lignes'] = $lignesParCommande[(int) $c['id']] ?? [];
        }

        return $commandes;
    }

    /**
     * Retourne une commande avec ses lignes, ou null si introuvable.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.id, c.patient_id, c.pharmacie_id, c.mode_retrait,
                    c.adresse_livraison, c.notes, c.statut, c.created_at, c.updated_at
             FROM commandes c WHERE c.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $commande = $stmt->fetch();
        if (!$commande) return null;

        $stmtL = $this->pdo->prepare(
            "SELECT lc.id, lc.commande_id, lc.medicament_id, lc.quantite, lc.prix_achat,
                    m.nom    AS medicament_nom,
                    m.forme  AS medicament_forme,
                    m.dosage AS medicament_dosage
             FROM lignes_commande lc
             JOIN medicaments m ON m.id_medicament = lc.medicament_id
             WHERE lc.commande_id = :id ORDER BY lc.id"
        );
        $stmtL->execute([':id' => $id]);
        $commande['lignes'] = $stmtL->fetchAll();

        return $commande;
    }

    // ── Écriture ──────────────────────────────────────────────────────────────

    /**
     * Crée une commande avec ses lignes en transaction.
     * Le prix_achat de chaque ligne est lu depuis l'inventaire de la pharmacie.
     * Retourne l'ID de la commande créée.
     */
    public function creer(array $commande, array $lignes): int
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO commandes
                     (patient_id, pharmacie_id, mode_retrait, adresse_livraison, notes, statut)
                 VALUES
                     (:patient_id, :pharmacie_id, :mode_retrait, :adresse_livraison, :notes, 'en_attente')"
            );
            $stmt->execute([
                ':patient_id'        => $commande['patientId'],
                ':pharmacie_id'      => $commande['pharmacieId'],
                ':mode_retrait'      => $commande['modeRetrait'],
                ':adresse_livraison' => $commande['adresseLivraison'] ?? null,
                ':notes'             => $commande['notes']            ?? null,
            ]);
            $commandeId = (int) $this->pdo->lastInsertId();

            $stmtPrix = $this->pdo->prepare(
                "SELECT COALESCE(prix_unitaire, 0)
                 FROM inventaire
                 WHERE id_pharmacie = :pid AND id_medicament = :mid
                 LIMIT 1"
            );
            $stmtLigne = $this->pdo->prepare(
                "INSERT INTO lignes_commande (commande_id, medicament_id, quantite, prix_achat)
                 VALUES (:commande_id, :medicament_id, :quantite, :prix_achat)"
            );

            foreach ($lignes as $ligne) {
                $stmtPrix->execute([
                    ':pid' => $commande['pharmacieId'],
                    ':mid' => $ligne['medicamentId'],
                ]);
                $prixAchat = (float) ($stmtPrix->fetchColumn() ?: 0);

                $stmtLigne->execute([
                    ':commande_id'   => $commandeId,
                    ':medicament_id' => $ligne['medicamentId'],
                    ':quantite'      => max(1, (int) $ligne['quantite']),
                    ':prix_achat'    => $prixAchat,
                ]);
            }

            $this->pdo->commit();
            return $commandeId;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Met à jour le statut d'une commande.
     * Valide la transition avant d'écrire.
     * Retourne un message d'erreur, ou null si OK.
     */
    public function mettreAJourStatut(int $id, string $nouveauStatut): ?string
    {
        $stmt = $this->pdo->prepare(
            "SELECT statut FROM commandes WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $statutActuel = $stmt->fetchColumn();

        if ($statutActuel === false) return 'Commande introuvable';

        $transitions = self::TRANSITIONS[$statutActuel] ?? [];
        if (!in_array($nouveauStatut, $transitions, true)) {
            return "Transition « $statutActuel → $nouveauStatut » non autorisée";
        }

        $this->pdo->prepare(
            "UPDATE commandes SET statut = :statut WHERE id = :id"
        )->execute([':statut' => $nouveauStatut, ':id' => $id]);

        return null;
    }
}
<?php
namespace models;

class InventaireModel
{
    public function __construct(private \PDO $pdo) {}

    public function listerParPharmacie(int $pharmacieId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT i.id_inventaire     AS id,
                    i.id_pharmacie      AS pharmacie_id,
                    i.id_medicament     AS medicament_id,
                    i.quantite, i.prix_unitaire, i.date_peremption, i.updated_at,
                    m.nom          AS medicament_nom,
                    m.description  AS medicament_description,
                    m.sur_ordonnance, m.forme, m.dosage, m.laboratoire
             FROM inventaire i
             JOIN medicaments m ON m.id_medicament = i.id_medicament
             WHERE i.id_pharmacie = :pharmacie_id
             ORDER BY m.nom"
        );
        $stmt->execute([':pharmacie_id' => $pharmacieId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT i.id_inventaire AS id,
                    i.id_pharmacie  AS pharmacie_id,
                    i.id_medicament AS medicament_id,
                    i.quantite, i.prix_unitaire, i.date_peremption, i.updated_at,
                    m.nom AS medicament_nom
             FROM inventaire i
             JOIN medicaments m ON m.id_medicament = i.id_medicament
             WHERE i.id_inventaire = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Crée une ligne d'inventaire. Si le couple (pharmacie, médicament) existe déjà,
     * incrémente la quantité et met à jour prix/péremption si fournis.
     */
    public function creer(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_inventaire FROM inventaire
             WHERE id_pharmacie = :pid AND id_medicament = :mid LIMIT 1"
        );
        $stmt->execute([':pid' => $data['pharmacieId'], ':mid' => $data['medicamentId']]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $this->modifier((int) $existingId, $data);
            return (int) $existingId;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO inventaire (id_pharmacie, id_medicament, quantite, prix_unitaire, date_peremption)
             VALUES (:id_pharmacie, :id_medicament, :quantite, :prix_unitaire, :date_peremption)"
        );
        $stmt->execute([
            ':id_pharmacie'    => $data['pharmacieId'],
            ':id_medicament'   => $data['medicamentId'],
            ':quantite'        => (int) ($data['quantite'] ?? 0),
            ':prix_unitaire'   => $data['prixUnitaire']   ?? null,
            ':date_peremption' => $data['datePeremption'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Mise à jour partielle : seuls les champs présents dans $data sont modifiés.
     */
    public function modifier(int $id, array $data): void
    {
        $champs  = [];
        $valeurs = [':id' => $id];

        if (array_key_exists('quantite', $data)) {
            $champs[]             = 'quantite = :quantite';
            $valeurs[':quantite'] = max(0, (int) $data['quantite']);
        }
        if (array_key_exists('prixUnitaire', $data)) {
            $champs[]                  = 'prix_unitaire = :prix_unitaire';
            $valeurs[':prix_unitaire'] = $data['prixUnitaire'] !== null
                                          ? (float) $data['prixUnitaire']
                                          : null;
        }
        if (array_key_exists('datePeremption', $data)) {
            $champs[]                    = 'date_peremption = :date_peremption';
            $valeurs[':date_peremption'] = $data['datePeremption'] ?: null;
        }

        if (empty($champs)) return;

        $this->pdo->prepare(
            'UPDATE inventaire SET ' . implode(', ', $champs) . ' WHERE id_inventaire = :id'
        )->execute($valeurs);
    }
}

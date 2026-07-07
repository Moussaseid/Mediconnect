// docs/mongodb_init.js
// Script d'initialisation MongoDB — MediConnect Analytics
// Usage : mongosh mediconnect_analytics docs/mongodb_init.js

use mediconnect_analytics;

// ── Collection auth_logs ──────────────────────────────────────────────────────
db.createCollection("auth_logs", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["action", "timestamp", "statut"],
            properties: {
                userId:    { bsonType: ["int", "null"] },
                email:     { bsonType: ["string", "null"] },
                action:    {
                    bsonType: "string",
                    enum: [
                        "connexion_reussie",
                        "connexion_echouee",
                        "deconnexion",
                        "inscription",
                        "reset_mdp_demande",
                        "reset_mdp_effectue",
                        "changement_role",
                        "compte_suspendu_acces"
                    ]
                },
                role:      { bsonType: ["string", "null"] },
                ip:        { bsonType: ["string", "null"] },
                userAgent: { bsonType: ["string", "null"] },
                timestamp: { bsonType: "date" },
                statut:    { bsonType: "string", enum: ["succes", "echec"] }
            }
        }
    }
});

// Index auth_logs
db.auth_logs.createIndex({ userId: 1, timestamp: -1 });
db.auth_logs.createIndex({ action: 1, timestamp: -1 });
db.auth_logs.createIndex({ email: 1, timestamp: -1 });
// TTL 90 jours — purge automatique des anciens logs
db.auth_logs.createIndex(
    { timestamp: 1 },
    { expireAfterSeconds: 7776000, name: "ttl_90j" }
);

// ── Collection activity_logs ──────────────────────────────────────────────────
db.createCollection("activity_logs");
db.activity_logs.createIndex({ userId: 1, timestamp: -1 });
db.activity_logs.createIndex({ entite: 1, entiteId: 1 });
db.activity_logs.createIndex(
    { timestamp: 1 },
    { expireAfterSeconds: 15552000, name: "ttl_180j" }
);

// ── Collection fichiers_metadata ──────────────────────────────────────────────
db.createCollection("fichiers_metadata");
db.fichiers_metadata.createIndex({ userId: 1 });
db.fichiers_metadata.createIndex({ contexte: 1 });
db.fichiers_metadata.createIndex({ dateAjout: -1 });

// ── Collection stats_analytiques ─────────────────────────────────────────────
db.createCollection("stats_analytiques");
db.stats_analytiques.createIndex({ date: -1 }, { unique: true });

// ── Données de test ───────────────────────────────────────────────────────────
db.auth_logs.insertOne({
    userId:    null,
    email:     "test@exemple.fr",
    action:    "connexion_echouee",
    role:      null,
    ip:        "127.0.0.1",
    userAgent: "seed",
    timestamp: new Date(),
    statut:    "echec"
});

print("=== MediConnect Analytics — Collections et index créés ===");
print("auth_logs      : " + db.auth_logs.countDocuments() + " document(s)");
print("activity_logs  : " + db.activity_logs.countDocuments() + " document(s)");
print("fichiers_metadata : " + db.fichiers_metadata.countDocuments() + " document(s)");
print("stats_analytiques : " + db.stats_analytiques.countDocuments() + " document(s)");

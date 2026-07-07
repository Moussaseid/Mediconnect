# MediConnect - Schema MongoDB Partie 2

## Contexte
MariaDB = donnees operationnelles (RDV, users, stocks)
MongoDB  = logs activite, fichiers, statistiques

---

## Collection 1 - auth_logs
Responsable : P1
Alimentee a chaque connexion/deconnexion/tentative

### Document type
\
### Valeurs action
connexion_reussie | connexion_echouee | deconnexion | inscription
reset_mdp_demande | reset_mdp_effectue | changement_role | compte_suspendu_acces

### Source PHP
AuthController::connexion(), AuthController::deconnexion()
AuthController::motDePasseOubli(), BaseController::requireRole()

### Index
\
---

## Collection 2 - activity_logs
Responsable : P3
Alimentee a chaque action metier

### Document type
\
### Valeurs action
rdv_cree | rdv_annule | profil_medecin_modifie | horaire_ajoute | horaire_supprime
indispo_ajoutee | medecin_valide | medecin_rejete | stock_modifie | commande_creee

### Index
\
---

## Collection 3 - fichiers_metadata
Responsable : P4
Alimentee a chaque upload valide par finfo MIME

### Document type
\: is a shell builtin
### Valeurs contexte
photo_profil_medecin | photo_profil_patient | justificatif_demande | document_centre

### Index
\
---

## Collection 4 - stats_analytiques (ETL nightly)
Alimentee par cron PHP. Exploitee par Power BI.

### Document type
\
### Index
\
---

## Script init_mongodb.js
\
// interfaces.ts - MediConnect Partie 2
// Genere depuis schema.sql + toutes les migrations

export type UserRole = 'patient' | 'medecin' | 'admin' | 'pharmacie' | 'centre_sante' | 'centre_analyse';
export type UserStatut = 'actif' | 'inactif' | 'suspendu';
export type RdvStatut = 'confirme' | 'annule';
export type AnnulePar = 'patient' | 'medecin';
export type ModeRetrait = 'sur_place' | 'livraison';
export type CommandeStatut = 'en_attente' | 'preparee' | 'prete' | 'livree' | 'annulee';
export type TypeProfessionnel = 'medecin' | 'pharmacien' | 'centre_sante' | 'centre_analyse';
export type DemandeStatut = 'en_attente' | 'approuve' | 'rejete';

// utilisateurs: id, nom, prenom, email, telephone, adresse, ville, role, statut, created_at, photo_path
export interface IUser { id:number; nom:string; prenom:string; email:string; telephone?:string; adresse?:string; ville?:string; role:UserRole; statut:UserStatut; createdAt?:string; photoPath?:string; }
export interface ILoginRequest { email:string; password:string; }
export interface IRegisterRequest { nom:string; prenom:string; email:string; password:string; telephone?:string; adresse?:string; ville?:string; }
export interface IAuthResponse { token:string; expiresIn:number; user:IUser; }

// specialite: _id_specialite_, libelle
export interface ISpecialite { id:number; libelle:string; }

// medecins: id, utilisateur_id, specialisation(FK), numero_rpps, adresse_cabinet, latitude, longitude, valide_par, valide_le, duree_rdv, photo_path, actif, telephone
export interface IMedecin { id:number; utilisateurId:number; nom:string; prenom:string; email:string; specialisation:number; specialisationLibelle?:string; numeroRpps:string; adresseCabinet?:string; latitude?:number; longitude?:number; validePar?:number; valideLe?:string; dureeRdv:number; photoPath?:string; actif?:boolean; telephone?:string; horaires?:IHoraireSemaine[]; distance?:number; }
export interface IMedecinUpdateRequest { specialisation?:number; adresseCabinet?:string; photoPath?:string; dureeRdv?:number; telephone?:string; latitude?:number; longitude?:number; }

// horaires_semaine: id, medecin_id, jour_semaine, heure_debut, heure_fin, created_at
export interface IHoraireSemaine { id:number; medecinId:number; jourSemaine:1|2|3|4|5|6|7; heureDebut:string; heureFin:string; createdAt?:string; }
export interface ICreneau { heureDebut:string; heureFin:string; disponible:boolean; }
export interface ISemaineDispo { [dateKey:string]: { label:string; creneaux:ICreneau[] }; }

// indisponibilite: id, medecin_id, date_debut, date_fin, motif, created_at
export interface IIndisponibilite { id:number; medecinId:number; dateDebut:string; dateFin:string; motif?:string; createdAt?:string; }

// rendez_vous: id, patient_id, medecin_id, date_heure, statut, annule_par, motif_annulation, created_at
export interface IRdv { id:number; patientId:number; medecinId:number; dateHeure:string; statut:RdvStatut; annulePar?:AnnulePar; motifAnnulation?:string; createdAt:string; medecin?:IMedecin; patient?:IUser; }
export interface IRdvCreateRequest { medecinId:number; dateHeure:string; }
export interface IRdvAnnulerRequest { motif:string; }

// pharmacies: id_pharmacie, nom, adresse, code_postal, ville, telephone, email, latitude, longitude, actif, created_at, updated_at
export interface IPharmacie { id:number; nom:string; adresse?:string; codePostal?:string; ville?:string; telephone?:string; email?:string; latitude?:number; longitude?:number; actif:boolean; createdAt?:string; updatedAt?:string; distance?:number; inventaire?:IInventaire[]; }

// medicaments: id_medicament, nom, description, sur_ordonnance, forme, dosage, laboratoire, created_at
export interface IMedicament { id:number; nom:string; description?:string; surOrdonnance:boolean; forme?:string; dosage?:string; laboratoire?:string; createdAt?:string; }

// inventaire: id_inventaire, id_pharmacie, id_medicament, quantite, prix_unitaire, date_peremption, updated_at
export interface IInventaire { id:number; pharmacieId:number; medicamentId:number; quantite:number; prixUnitaire:number; datePeremption?:string; updatedAt?:string; medicament?:IMedicament; pharmacie?:IPharmacie; }

// commandes: id, patient_id, pharmacie_id, mode_retrait, adresse_livraison, notes, statut, created_at, updated_at
export interface ICommande { id:number; patientId:number; pharmacieId:number; modeRetrait:ModeRetrait; adresseLivraison?:string; notes?:string; statut:CommandeStatut; createdAt:string; updatedAt:string; lignes?:ILigneCommande[]; pharmacie?:IPharmacie; }
export interface ICommandeCreateRequest { pharmacieId:number; modeRetrait:ModeRetrait; adresseLivraison?:string; notes?:string; lignes:{medicamentId:number; quantite:number}[]; }

// lignes_commande: id, commande_id, medicament_id, quantite, prix_achat
export interface ILigneCommande { id:number; commandeId:number; medicamentId:number; quantite:number; prixAchat:number; medicament?:IMedicament; }

// centres_analyse: id, nom, adresse, latitude, longitude, telephone, email, actif, created_at
export interface ICentreAnalyse { id:number; nom:string; adresse?:string; latitude?:number; longitude?:number; telephone?:string; email?:string; actif:boolean; createdAt?:string; distance?:number; analyses?:ICentreAnalyseItem[]; }

// analyses: id, nom, description, categorie, duree_jours, created_at
export interface IAnalyse { id:number; nom:string; description?:string; categorie?:string; dureeJours:number; createdAt?:string; }

// centre_analyse_analyses: id, centre_id, analyse_id, prix, disponible
export interface ICentreAnalyseItem { id:number; centreId:number; analyseId:number; prix?:number; disponible:boolean; analyse?:IAnalyse; }

// centre_analyses: id, centre_id, nom, description, prix, duree_minutes, disponible, created_at
export interface IAnalysePropre { id:number; centreId:number; nom:string; description?:string; prix:number; dureeMinutes:number; disponible:boolean; createdAt?:string; }
export interface IAnalysePropreRequest { nom:string; description?:string; prix:number; dureeMinutes:number; disponible:boolean; }

// centres_sante: id, nom, adresse, latitude, longitude, telephone, email, description, specialites, services, photo_path, actif, created_at
export interface ICentreSante { id:number; nom:string; adresse?:string; latitude?:number; longitude?:number; telephone?:string; email?:string; description?:string; specialites?:string; services?:string; photoPath?:string; actif:boolean; createdAt?:string; distance?:number; }
export interface ICentreSanteUpdateRequest { nom:string; adresse?:string; telephone?:string; email?:string; description?:string; specialites?:string; services?:string; }

// demandes_professionnels: id, type_professionnel, nom, prenom, email, telephone, numero_pro, specialisation, entite_id, entite_nom, adresse_cabinet, statut, commentaire, traite_par, traite_le, created_at
export interface IDemandeProfessionnel { id:number; typeProfessionnel:TypeProfessionnel; nom:string; prenom:string; email:string; telephone?:string; numeroPro?:string; specialisation?:string; entiteId?:number; entiteNom?:string; adresseCabinet?:string; statut:DemandeStatut; commentaire?:string; traitePar?:number; traiteLe?:string; createdAt?:string; }

// prescriptions: id, patient_id, medecin_id, rdv_id, date_prescription, validite_jours, created_at
export interface IPrescription { id:number; patientId:number; medecinId:number; rdvId?:number; datePrescription:string; validiteJours:number; createdAt:string; medecin?:IMedecin; lignes?:IOrdonanceLigne[]; }

// ordonnance_lignes: id, prescription_id, medicament_id, posologie, duree_jours, quantite
export interface IOrdonanceLigne { id:number; prescriptionId:number; medicamentId:number; posologie:string; dureeJours?:number; quantite:number; medicament?:IMedicament; }

// API generiques
export interface IApiResponse<T> { data:T; message?:string; total?:number; page?:number; }
export interface IApiError { error:string; code:number; details?:string; }
export interface IProfilUpdateRequest { nom?:string; prenom?:string; telephone?:string; adresse?:string; ville?:string; }
export interface IUserUpdateRequest   { nom?:string; prenom?:string; telephone?:string; adresse?:string; ville?:string; motDePasse?:string; }

// Filtres
export interface IRechercheMedecinParams { specialiteId?:number; rayon?:5|10|20|50; lat?:number; lng?:number; }
export interface IRecherchePharmaciParams { medicamentNom?:string; rayon?:5|10|20|50; lat?:number; lng?:number; }

// Stats admin
export interface IAlertStock { pharmacieNom:string; medicamentNom:string; quantite:number; prixUnitaire:number; }
export interface IAdminStats { patients:number; medecins:number; pharmacies:number; centresAnalyse:number; rdvCeMois:number; rdvConfirmes:number; rdvAnnules:number; commandesTotales:number; commandesAttente:number; demandesPro:number; alertesStock:IAlertStock[]; repartitionRoles:{role:string;nb:number}[]; }

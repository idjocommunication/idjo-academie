-- Idjô Académie : structure de la base SQLite (fichier privé hors du dossier public).
-- Créée automatiquement au premier lancement par app/bootstrap.php.

CREATE TABLE IF NOT EXISTS utilisateurs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL UNIQUE,
  nom TEXT NOT NULL DEFAULT '',
  mot_de_passe TEXT NULL,
  email_verifie_le TEXT NULL,
  google_sub TEXT NULL UNIQUE,
  bloque INTEGER NOT NULL DEFAULT 0,
  cree_le TEXT NOT NULL,
  derniere_connexion TEXT NULL
);

CREATE TABLE IF NOT EXISTS jetons (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL,
  type TEXT NOT NULL,
  empreinte TEXT NOT NULL UNIQUE,
  expire_le TEXT NOT NULL,
  utilise_le TEXT NULL,
  cree_le TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS jetons_email ON jetons (email, type);

CREATE TABLE IF NOT EXISTS tentatives (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  cle TEXT NOT NULL,
  cree_le TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS tentatives_cle ON tentatives (cle, cree_le);

CREATE TABLE IF NOT EXISTS formations (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  slug TEXT NOT NULL UNIQUE,
  titre TEXT NOT NULL,
  sous_titre TEXT NOT NULL DEFAULT '',
  description TEXT NULL,
  prix_fcfa INTEGER NOT NULL DEFAULT 0,
  formules TEXT NULL,
  image TEXT NOT NULL DEFAULT '',
  publiee INTEGER NOT NULL DEFAULT 0,
  ordre INTEGER NOT NULL DEFAULT 0,
  cree_le TEXT NOT NULL,
  modifie_le TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS modules (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  formation_id INTEGER NOT NULL REFERENCES formations(id) ON DELETE CASCADE,
  titre TEXT NOT NULL,
  description TEXT NULL,
  ordre INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS lecons (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  module_id INTEGER NOT NULL REFERENCES modules(id) ON DELETE CASCADE,
  titre TEXT NOT NULL,
  resume TEXT NULL,
  duree_sec INTEGER NOT NULL DEFAULT 0,
  video_fichier TEXT NULL,
  video_taille INTEGER NULL,
  video_type TEXT NULL,
  apercu_gratuit INTEGER NOT NULL DEFAULT 0,
  ordre INTEGER NOT NULL DEFAULT 0,
  cree_le TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS fichiers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  lecon_id INTEGER NOT NULL REFERENCES lecons(id) ON DELETE CASCADE,
  nom TEXT NOT NULL,
  fichier TEXT NOT NULL,
  taille INTEGER NOT NULL DEFAULT 0,
  type TEXT NOT NULL DEFAULT 'application/pdf',
  cree_le TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS acces (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  utilisateur_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
  formation_id INTEGER NOT NULL REFERENCES formations(id) ON DELETE CASCADE,
  formule TEXT NOT NULL DEFAULT '',
  actif INTEGER NOT NULL DEFAULT 1,
  note TEXT NOT NULL DEFAULT '',
  accorde_le TEXT NOT NULL,
  UNIQUE (utilisateur_id, formation_id)
);

CREATE TABLE IF NOT EXISTS demandes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  utilisateur_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
  formation_id INTEGER NOT NULL REFERENCES formations(id) ON DELETE CASCADE,
  formule TEXT NOT NULL DEFAULT '',
  statut TEXT NOT NULL DEFAULT 'en_attente',
  cree_le TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS demandes_statut ON demandes (statut);

CREATE TABLE IF NOT EXISTS progression (
  utilisateur_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
  lecon_id INTEGER NOT NULL REFERENCES lecons(id) ON DELETE CASCADE,
  position_sec INTEGER NOT NULL DEFAULT 0,
  terminee_le TEXT NULL,
  modifie_le TEXT NOT NULL,
  PRIMARY KEY (utilisateur_id, lecon_id)
);

CREATE TABLE IF NOT EXISTS reglages (
  cle TEXT PRIMARY KEY,
  valeur TEXT NULL
);

-- Idjô Académie : structure de la base (MySQL / MariaDB, utf8mb4).
-- Créée automatiquement au premier lancement par app/bootstrap.php.

CREATE TABLE IF NOT EXISTS utilisateurs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  nom VARCHAR(120) NOT NULL DEFAULT '',
  mot_de_passe VARCHAR(255) NULL,
  email_verifie_le DATETIME NULL,
  google_sub VARCHAR(64) NULL UNIQUE,
  bloque TINYINT(1) NOT NULL DEFAULT 0,
  cree_le DATETIME NOT NULL,
  derniere_connexion DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jetons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  type VARCHAR(20) NOT NULL,            -- verification, magique, reinitialisation
  empreinte CHAR(64) NOT NULL UNIQUE,   -- sha256 du jeton envoyé par e-mail
  expire_le DATETIME NOT NULL,
  utilise_le DATETIME NULL,
  cree_le DATETIME NOT NULL,
  INDEX (email, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tentatives (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cle VARCHAR(200) NOT NULL,            -- action + ip + e-mail
  cree_le DATETIME NOT NULL,
  INDEX (cle, cree_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS formations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(120) NOT NULL UNIQUE,
  titre VARCHAR(200) NOT NULL,
  sous_titre VARCHAR(300) NOT NULL DEFAULT '',
  description TEXT NULL,
  prix_fcfa INT UNSIGNED NOT NULL DEFAULT 0,
  formules TEXT NULL,                   -- JSON : [{"id":"autonome","nom":"Autonome","prix":35000}, ...]
  image VARCHAR(255) NOT NULL DEFAULT '',
  publiee TINYINT(1) NOT NULL DEFAULT 0,
  ordre INT NOT NULL DEFAULT 0,
  cree_le DATETIME NOT NULL,
  modifie_le DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS modules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  formation_id INT UNSIGNED NOT NULL,
  titre VARCHAR(200) NOT NULL,
  description TEXT NULL,
  ordre INT NOT NULL DEFAULT 0,
  FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lecons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  module_id INT UNSIGNED NOT NULL,
  titre VARCHAR(200) NOT NULL,
  resume TEXT NULL,
  duree_sec INT UNSIGNED NOT NULL DEFAULT 0,
  video_fichier VARCHAR(255) NULL,
  video_taille BIGINT UNSIGNED NULL,
  video_type VARCHAR(60) NULL,
  apercu_gratuit TINYINT(1) NOT NULL DEFAULT 0,
  ordre INT NOT NULL DEFAULT 0,
  cree_le DATETIME NOT NULL,
  FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fichiers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lecon_id INT UNSIGNED NOT NULL,
  nom VARCHAR(200) NOT NULL,
  fichier VARCHAR(255) NOT NULL,
  taille BIGINT UNSIGNED NOT NULL DEFAULT 0,
  type VARCHAR(100) NOT NULL DEFAULT 'application/pdf',
  cree_le DATETIME NOT NULL,
  FOREIGN KEY (lecon_id) REFERENCES lecons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS acces (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id INT UNSIGNED NOT NULL,
  formation_id INT UNSIGNED NOT NULL,
  formule VARCHAR(40) NOT NULL DEFAULT '',
  actif TINYINT(1) NOT NULL DEFAULT 1,
  note VARCHAR(255) NOT NULL DEFAULT '',
  accorde_le DATETIME NOT NULL,
  UNIQUE KEY (utilisateur_id, formation_id),
  FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
  FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS demandes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id INT UNSIGNED NOT NULL,
  formation_id INT UNSIGNED NOT NULL,
  formule VARCHAR(40) NOT NULL DEFAULT '',
  statut VARCHAR(20) NOT NULL DEFAULT 'en_attente',   -- en_attente, acceptee, ignoree
  cree_le DATETIME NOT NULL,
  INDEX (statut),
  FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
  FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS progression (
  utilisateur_id INT UNSIGNED NOT NULL,
  lecon_id INT UNSIGNED NOT NULL,
  position_sec INT UNSIGNED NOT NULL DEFAULT 0,
  terminee_le DATETIME NULL,
  modifie_le DATETIME NOT NULL,
  PRIMARY KEY (utilisateur_id, lecon_id),
  FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
  FOREIGN KEY (lecon_id) REFERENCES lecons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contacts laissés sur la page d'accueil (nom + WhatsApp)
CREATE TABLE IF NOT EXISTS prospects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(80) NOT NULL,
  whatsapp VARCHAR(20) NOT NULL,
  source VARCHAR(20) NOT NULL DEFAULT '',
  statut VARCHAR(20) NOT NULL DEFAULT 'nouveau',   -- nouveau, contacte, inscrit, pas_interesse
  cree_le DATETIME NOT NULL,
  modifie_le DATETIME NOT NULL,
  INDEX (statut),
  INDEX (whatsapp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paiements en ligne (CinetPay)
CREATE TABLE IF NOT EXISTS paiements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transaction_id VARCHAR(40) NOT NULL UNIQUE,
  utilisateur_id INT UNSIGNED NOT NULL,
  formation_id INT UNSIGNED NOT NULL,
  formule VARCHAR(40) NOT NULL DEFAULT '',
  montant INT UNSIGNED NOT NULL,
  nom VARCHAR(80) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL,
  whatsapp VARCHAR(20) NOT NULL DEFAULT '',
  statut VARCHAR(20) NOT NULL DEFAULT 'en_attente',   -- en_attente, accepte, refuse
  moyen VARCHAR(30) NOT NULL DEFAULT '',
  compte_cree TINYINT(1) NOT NULL DEFAULT 0,
  cree_le DATETIME NOT NULL,
  modifie_le DATETIME NOT NULL,
  INDEX (statut),
  FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
  FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reglages (
  cle VARCHAR(60) PRIMARY KEY,
  valeur TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

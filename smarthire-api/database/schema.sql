-- ============================================================
-- SmartHire AI — Schéma de base de données MySQL
-- Réponse au retour de l'encadrant : entités, relations, index
-- fonctionnels sur colonnes JSON, pagination prévue côté API.
-- ============================================================

CREATE DATABASE IF NOT EXISTS smarthire_ai
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE smarthire_ai;

-- ------------------------------------------------------------
-- 1. USERS — table racine de l'authentification (JWT)
--    role détermine le sous-profil (candidate / recruiter / admin)
-- ------------------------------------------------------------
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('candidate', 'recruiter', 'admin') NOT NULL DEFAULT 'candidate',
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role (role)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2. COMPANIES — entreprises rattachées à des recruteurs
-- ------------------------------------------------------------
CREATE TABLE companies (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(190) NOT NULL,
  sector      VARCHAR(120),
  logo_url    VARCHAR(255),
  description TEXT,
  website     VARCHAR(255),
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3. RECRUITERS — profil 1-1 avec users (role=recruiter)
-- ------------------------------------------------------------
CREATE TABLE recruiters (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL UNIQUE,
  company_id  INT UNSIGNED NOT NULL,
  first_name  VARCHAR(100) NOT NULL,
  last_name   VARCHAR(100) NOT NULL,
  phone       VARCHAR(30),
  position    VARCHAR(120),
  CONSTRAINT fk_recruiters_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_recruiters_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 4. CANDIDATES — profil 1-1 avec users (role=candidate)
--    `parsed_cv_data` : JSON produit par le microservice NLP
--    (compétences extraites, expériences, etc.)
-- ------------------------------------------------------------
CREATE TABLE candidates (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL UNIQUE,
  first_name      VARCHAR(100) NOT NULL,
  last_name       VARCHAR(100) NOT NULL,
  phone           VARCHAR(30),
  headline        VARCHAR(190),
  location        VARCHAR(120),
  cv_file_path    VARCHAR(255),
  parsed_cv_data  JSON,
  years_experience TINYINT UNSIGNED DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_candidates_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  -- colonne virtuelle générée + index, pour interroger rapidement
  -- les compétences extraites par le microservice NLP (JSON)
  skills_summary VARCHAR(500)
    GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(parsed_cv_data, '$.skills_flat'))) STORED,
  INDEX idx_candidates_skills_summary (skills_summary),
  FULLTEXT INDEX ft_candidates_skills (skills_summary)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 5. SKILLS — référentiel des 100+ compétences détectées par le NLP
-- ------------------------------------------------------------
CREATE TABLE skills (
  id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name     VARCHAR(120) NOT NULL UNIQUE,
  category VARCHAR(80)
) ENGINE=InnoDB;

CREATE TABLE candidate_skills (
  candidate_id INT UNSIGNED NOT NULL,
  skill_id     INT UNSIGNED NOT NULL,
  score        DECIMAL(4,3) DEFAULT NULL, -- pertinence TF-IDF si utile
  PRIMARY KEY (candidate_id, skill_id),
  CONSTRAINT fk_cs_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
  CONSTRAINT fk_cs_skill FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6. JOB_OFFERS — offres d'emploi publiées par un recruteur
-- ------------------------------------------------------------
CREATE TABLE job_offers (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recruiter_id  INT UNSIGNED NOT NULL,
  company_id    INT UNSIGNED NOT NULL,
  title         VARCHAR(190) NOT NULL,
  description   TEXT NOT NULL,
  location      VARCHAR(120),
  contract_type ENUM('CDI','CDD','Stage','Freelance','Alternance') NOT NULL DEFAULT 'CDI',
  status        ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
  required_skills JSON, -- ex: ["PHP","MySQL","JWT"]
  published_at  DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_jo_recruiter FOREIGN KEY (recruiter_id) REFERENCES recruiters(id) ON DELETE CASCADE,
  CONSTRAINT fk_jo_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  INDEX idx_jo_status (status),
  INDEX idx_jo_location (location)
) ENGINE=InnoDB;

CREATE TABLE job_offer_skills (
  job_offer_id INT UNSIGNED NOT NULL,
  skill_id     INT UNSIGNED NOT NULL,
  is_required  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (job_offer_id, skill_id),
  CONSTRAINT fk_jos_offer FOREIGN KEY (job_offer_id) REFERENCES job_offers(id) ON DELETE CASCADE,
  CONSTRAINT fk_jos_skill FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 7. APPLICATIONS — candidature d'un candidat à une offre
--    match_score : résultat du microservice NLP (cosine similarity)
-- ------------------------------------------------------------
CREATE TABLE applications (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  candidate_id INT UNSIGNED NOT NULL,
  job_offer_id INT UNSIGNED NOT NULL,
  status       ENUM('submitted','shortlisted','interview','rejected','hired') NOT NULL DEFAULT 'submitted',
  match_score  DECIMAL(5,2) DEFAULT NULL, -- ex: 87.35 (%)
  cover_letter TEXT,
  applied_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_application (candidate_id, job_offer_id),
  CONSTRAINT fk_app_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
  CONSTRAINT fk_app_offer FOREIGN KEY (job_offer_id) REFERENCES job_offers(id) ON DELETE CASCADE,
  INDEX idx_app_status (status),
  INDEX idx_app_score (match_score)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 8. NOTIFICATIONS
-- ------------------------------------------------------------
CREATE TABLE notifications (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  type       VARCHAR(60) NOT NULL,
  message    VARCHAR(255) NOT NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_notif_user_unread (user_id, is_read)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 9. PASSWORD_RESETS — jetons "mot de passe oublié"
--    Seul le hash du jeton est stocké (jamais le jeton en clair),
--    même logique de sécurité que pour les mots de passe.
-- ------------------------------------------------------------
CREATE TABLE password_resets (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at    DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_pr_user (user_id),
  INDEX idx_pr_expires (expires_at)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 10. REFRESH_TOKENS — jetons de rafraîchissement (JWT access token
--     de courte durée, cf. config/jwt.php: expires_in = 1h).
--     Stockés côté serveur (hash uniquement) pour pouvoir être révoqués
--     individuellement (déconnexion, changement de mot de passe, etc.),
--     contrairement à un JWT stateless qui reste valide jusqu'à expiration.
-- ------------------------------------------------------------
CREATE TABLE refresh_tokens (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  token_hash VARCHAR(255) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_rt_user (user_id),
  INDEX idx_rt_expires (expires_at)
) ENGINE=InnoDB;

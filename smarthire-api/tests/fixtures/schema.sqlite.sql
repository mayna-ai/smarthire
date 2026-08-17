-- Schéma SQLite simplifié pour les tests d'intégration des repositories.
-- Reproduit uniquement les colonnes lues/écrites par le code testé
-- (voir backend/database/schema.sql pour le schéma MySQL complet et réel).

CREATE TABLE companies (
  id   INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL
);

CREATE TABLE recruiters (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER NOT NULL,
  company_id INTEGER NOT NULL,
  first_name TEXT,
  last_name  TEXT
);

CREATE TABLE candidates (
  id               INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id          INTEGER NOT NULL,
  first_name       TEXT NOT NULL,
  last_name        TEXT NOT NULL,
  phone            TEXT,
  headline         TEXT,
  location         TEXT,
  cv_file_path     TEXT,
  parsed_cv_data   TEXT,
  years_experience INTEGER DEFAULT 0,
  skills_summary   TEXT,
  created_at       TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at       TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE job_offers (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  recruiter_id    INTEGER NOT NULL,
  company_id      INTEGER NOT NULL,
  title           TEXT NOT NULL,
  description     TEXT NOT NULL,
  location        TEXT,
  contract_type   TEXT NOT NULL DEFAULT 'CDI',
  status          TEXT NOT NULL DEFAULT 'draft',
  required_skills TEXT,
  published_at    TEXT,
  created_at      TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE applications (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  candidate_id INTEGER NOT NULL,
  job_offer_id INTEGER NOT NULL,
  status       TEXT NOT NULL DEFAULT 'submitted',
  match_score  REAL DEFAULT NULL,
  cover_letter TEXT,
  applied_at   TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at   TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE password_resets (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER NOT NULL,
  token_hash TEXT NOT NULL,
  expires_at TEXT NOT NULL,
  used_at    TEXT DEFAULT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE refresh_tokens (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER NOT NULL,
  token_hash TEXT NOT NULL,
  expires_at TEXT NOT NULL,
  revoked_at TEXT DEFAULT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

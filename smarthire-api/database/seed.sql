-- ============================================================
-- SmartHire AI — Données de démonstration (seed)
-- À exécuter APRÈS backend/database/schema.sql :
--
--   mysql -u root -p smarthire_ai < backend/database/seed.sql
--
-- Mot de passe de TOUS les comptes de démo ci-dessous : password123
-- (hash bcrypt valide, vérifiable par password_verify() en PHP)
-- ⚠️ Données fictives, à usage de démo/développement uniquement.
-- ============================================================

USE smarthire_ai;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE notifications;
TRUNCATE TABLE applications;
TRUNCATE TABLE job_offer_skills;
TRUNCATE TABLE job_offers;
TRUNCATE TABLE candidate_skills;
TRUNCATE TABLE skills;
TRUNCATE TABLE candidates;
TRUNCATE TABLE recruiters;
TRUNCATE TABLE companies;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- 1. USERS (mot de passe pour tous : password123)
--    id 1     -> admin
--    id 2-4   -> recruteurs
--    id 5-10  -> candidats
-- ------------------------------------------------------------
INSERT INTO users (id, email, password_hash, role, is_active) VALUES
(1,  'admin@smarthire.ai',      '$2b$10$je31Q5MXuKuK7mGQS6/hgOGeZD9Mm3SPVzosd2Skx9FVNULbuLCyC', 'admin',     1),
(2,  'sarra.mansour@acme.tn',   '$2b$10$je31Q5MXuKuK7mGQS6/hgOGeZD9Mm3SPVzosd2Skx9FVNULbuLCyC', 'recruiter', 1),
(3,  'karim.bouzid@nexatech.tn','$2b$10$je31Q5MXuKuK7mGQS6/hgOGeZD9Mm3SPVzosd2Skx9FVNULbuLCyC', 'recruiter', 1),
(4,  'ines.gharbi@databridge.tn','$2b$10$je31Q5MXuKuK7mGQS6/hgOGeZD9Mm3SPVzosd2Skx9FVNULbuLCyC', 'recruiter', 1),
(5,  'mayssa.trabelsi@mail.tn', '$2b$10$je31Q5MXuKuK7mGQS6/hgOGeZD9Mm3SPVzosd2Skx9FVNULbuLCyC', 'candidate', 1),
(6,  'yassine.khemiri@mail.tn', '$2b$10$je31Q5MXuKuK7mGQS6/hgOGeZD9Mm3SPVzosd2Skx9FVNULbuLCyC', 'candidate', 1),
(7,  'nour.ayadi@mail.tn',      '$2b$10$je31Q5MXuKuK7mGQS6/hgOGeZD9Mm3SPVzosd2Skx9FVNULbuLCyC', 'candidate', 1),
(8,  'walid.jendoubi@mail.tn',  '$2b$10$je31Q5MXuKuK7mGQS6/hgOGeZD9Mm3SPVzosd2Skx9FVNULbuLCyC', 'candidate', 1),
(9,  'salma.ferchichi@mail.tn', '$2b$10$je31Q5MXuKuK7mGQS6/hgOGeZD9Mm3SPVzosd2Skx9FVNULbuLCyC', 'candidate', 1),
(10, 'amine.saidi@mail.tn',     '$2b$10$je31Q5MXuKuK7mGQS6/hgOGeZD9Mm3SPVzosd2Skx9FVNULbuLCyC', 'candidate', 0); -- compte désactivé, utile pour tester l'admin

-- ------------------------------------------------------------
-- 2. COMPANIES
-- ------------------------------------------------------------
INSERT INTO companies (id, name, sector, logo_url, description, website) VALUES
(1, 'Acme Corp',      'Technologie',        NULL, 'Éditeur de logiciels B2B basé à Tunis.',              'https://acme.example'),
(2, 'NexaTech',       'Conseil IT',         NULL, 'Cabinet de conseil en transformation digitale.',      'https://nexatech.example'),
(3, 'DataBridge',     'Data & IA',          NULL, 'Startup spécialisée en ingénierie de données et IA.',  'https://databridge.example');

-- ------------------------------------------------------------
-- 3. RECRUITERS (1-1 avec users id 2,3,4)
-- ------------------------------------------------------------
INSERT INTO recruiters (id, user_id, company_id, first_name, last_name, phone, position) VALUES
(1, 2, 1, 'Sarra',  'Mansour', '+216 20 111 222', 'Talent Acquisition Manager'),
(2, 3, 2, 'Karim',  'Bouzid',  '+216 22 333 444', 'HR Business Partner'),
(3, 4, 3, 'Ines',   'Gharbi',  '+216 24 555 666', 'CTO');

-- ------------------------------------------------------------
-- 4. CANDIDATES (1-1 avec users id 5..10)
--    parsed_cv_data simule la sortie du parsing CV (CvParser),
--    skills_flat alimente la colonne virtuelle générée skills_summary.
-- ------------------------------------------------------------
INSERT INTO candidates (id, user_id, first_name, last_name, phone, headline, location, cv_file_path, parsed_cv_data, years_experience) VALUES
(1, 5, 'Mayssa', 'Trabelsi', '+216 25 100 200', 'Développeuse Full-Stack PHP/JS',
 'Tunis', NULL,
 JSON_OBJECT('skills_flat', 'PHP, MySQL, JavaScript, Tailwind CSS, Git', 'skills', JSON_ARRAY('PHP','MySQL','JavaScript','Tailwind CSS','Git'), 'text_excerpt', 'Développeuse full-stack avec 3 ans d\'expérience...', 'parsed_at', NOW()),
 3),
(2, 6, 'Yassine', 'Khemiri', '+216 26 200 300', 'Data Scientist Junior',
 'Sousse', NULL,
 JSON_OBJECT('skills_flat', 'Python, Pandas, Machine Learning, SQL', 'skills', JSON_ARRAY('Python','Pandas','Machine Learning','SQL'), 'text_excerpt', 'Passionné de data science, stage de fin d\'études en NLP...', 'parsed_at', NOW()),
 1),
(3, 7, 'Nour', 'Ayadi', '+216 27 300 400', 'Ingénieure Logiciel Java/Spring',
 'Monastir', NULL,
 JSON_OBJECT('skills_flat', 'Java, Spring Boot, MySQL, Git, Scrum', 'skills', JSON_ARRAY('Java','Spring Boot','MySQL','Git','Scrum'), 'text_excerpt', 'Ingénieure logicielle spécialisée backend Java...', 'parsed_at', NOW()),
 4),
(4, 8, 'Walid', 'Jendoubi', '+216 28 400 500', 'Développeur Frontend React',
 'Tunis', NULL,
 JSON_OBJECT('skills_flat', 'React, JavaScript, Tailwind CSS, TypeScript', 'skills', JSON_ARRAY('React','JavaScript','Tailwind CSS','TypeScript'), 'text_excerpt', 'Développeur frontend orienté UI/UX...', 'parsed_at', NOW()),
 2),
(5, 9, 'Salma', 'Ferchichi', '+216 29 500 600', 'Administratrice Bases de Données',
 'Sfax', NULL,
 JSON_OBJECT('skills_flat', 'Oracle, MySQL, SQL, ETL, BI', 'skills', JSON_ARRAY('Oracle','MySQL','SQL','ETL','BI'), 'text_excerpt', 'DBA junior, spécialisée en data warehousing...', 'parsed_at', NOW()),
 2),
(6, 10, 'Amine', 'Saidi', '+216 20 600 700', 'Étudiant en Génie Logiciel — stage',
 'Monastir', NULL,
 JSON_OBJECT('skills_flat', 'PHP, Python, C, Git', 'skills', JSON_ARRAY('PHP','Python','C','Git'), 'text_excerpt', 'Étudiant en 3ème année, recherche un stage PFE...', 'parsed_at', NOW()),
 0);

-- ------------------------------------------------------------
-- 5. SKILLS (référentiel)
-- ------------------------------------------------------------
INSERT INTO skills (id, name, category) VALUES
(1,  'PHP',              'Backend'),
(2,  'MySQL',            'Base de données'),
(3,  'JavaScript',       'Frontend'),
(4,  'Tailwind CSS',     'Frontend'),
(5,  'Git',              'Outils'),
(6,  'Python',           'Backend'),
(7,  'Machine Learning', 'IA / Data'),
(8,  'SQL',              'Base de données'),
(9,  'Java',             'Backend'),
(10, 'Spring Boot',      'Backend'),
(11, 'React',            'Frontend'),
(12, 'TypeScript',       'Frontend'),
(13, 'Oracle',           'Base de données'),
(14, 'ETL',              'Data'),
(15, 'BI',               'Data');

-- ------------------------------------------------------------
-- 6. CANDIDATE_SKILLS
-- ------------------------------------------------------------
INSERT INTO candidate_skills (candidate_id, skill_id, score) VALUES
(1, 1, 0.900), (1, 2, 0.850), (1, 3, 0.800), (1, 4, 0.700), (1, 5, 0.750),
(2, 6, 0.900), (2, 7, 0.800), (2, 8, 0.700),
(3, 9, 0.900), (3, 10, 0.850), (3, 2, 0.600), (3, 5, 0.700),
(4, 11, 0.900), (4, 3, 0.850), (4, 4, 0.700), (4, 12, 0.750),
(5, 13, 0.850), (5, 2, 0.800), (5, 8, 0.900), (5, 14, 0.700), (5, 15, 0.700),
(6, 1, 0.600), (6, 6, 0.600), (6, 5, 0.650);

-- ------------------------------------------------------------
-- 7. JOB_OFFERS
-- ------------------------------------------------------------
INSERT INTO job_offers (id, recruiter_id, company_id, title, description, location, contract_type, status, required_skills, published_at) VALUES
(1, 1, 1, 'Développeur PHP Full-Stack',
   'Nous recherchons un(e) développeur(se) PHP full-stack pour renforcer notre équipe produit. Vous travaillerez sur notre API REST et l\'interface associée.',
   'Tunis', 'CDI', 'published', JSON_ARRAY('PHP','MySQL','JavaScript','Git'), NOW()),
(2, 1, 1, 'Stagiaire Développement Web',
   'Stage de fin d\'études (PFE) au sein de l\'équipe engineering, autour de la plateforme SmartHire (PHP/MySQL/JS).',
   'Tunis', 'Stage', 'published', JSON_ARRAY('PHP','Git'), NOW()),
(3, 2, 2, 'Data Scientist Junior',
   'Rejoignez notre practice Data pour des missions de conseil en machine learning et NLP chez nos clients.',
   'Sousse', 'CDI', 'published', JSON_ARRAY('Python','Machine Learning','SQL'), NOW()),
(4, 2, 2, 'Ingénieur Java/Spring',
   'Développement d\'applications backend Java/Spring Boot pour des clients grands comptes.',
   'Monastir', 'CDI', 'published', JSON_ARRAY('Java','Spring Boot','MySQL'), NOW()),
(5, 3, 3, 'Développeur Frontend React',
   'Construction de l\'interface de notre plateforme data en React/TypeScript.',
   'Tunis', 'CDD', 'published', JSON_ARRAY('React','JavaScript','TypeScript'), NOW()),
(6, 3, 3, 'Administrateur Base de Données',
   'Gestion et optimisation de nos entrepôts de données (Oracle/MySQL), mise en place de pipelines ETL.',
   'Sfax', 'CDI', 'draft', JSON_ARRAY('Oracle','SQL','ETL','BI'), NULL);

-- ------------------------------------------------------------
-- 8. JOB_OFFER_SKILLS
-- ------------------------------------------------------------
INSERT INTO job_offer_skills (job_offer_id, skill_id, is_required) VALUES
(1, 1, 1), (1, 2, 1), (1, 3, 1), (1, 5, 0),
(2, 1, 1), (2, 5, 0),
(3, 6, 1), (3, 7, 1), (3, 8, 0),
(4, 9, 1), (4, 10, 1), (4, 2, 0),
(5, 11, 1), (5, 3, 1), (5, 12, 0),
(6, 13, 1), (6, 8, 1), (6, 14, 1), (6, 15, 0);

-- ------------------------------------------------------------
-- 9. APPLICATIONS
-- ------------------------------------------------------------
INSERT INTO applications (id, candidate_id, job_offer_id, status, match_score, cover_letter, applied_at) VALUES
(1, 1, 1, 'shortlisted', 87.50, 'Je suis très intéressée par ce poste, mon profil PHP/JS correspond bien à vos besoins.', NOW() - INTERVAL 6 DAY),
(2, 1, 2, 'interview',   72.00, NULL, NOW() - INTERVAL 4 DAY),
(3, 6, 2, 'submitted',   65.30, 'Étudiant en génie logiciel, très motivé pour ce stage.', NOW() - INTERVAL 2 DAY),
(4, 2, 3, 'hired',       91.20, 'Mon stage de fin d\'études portait justement sur le NLP.', NOW() - INTERVAL 10 DAY),
(5, 3, 4, 'rejected',    58.40, NULL, NOW() - INTERVAL 8 DAY),
(6, 4, 5, 'shortlisted', 83.10, 'Portfolio React disponible sur demande.', NOW() - INTERVAL 3 DAY);

-- ------------------------------------------------------------
-- 10. NOTIFICATIONS
-- ------------------------------------------------------------
INSERT INTO notifications (user_id, type, message, is_read, created_at) VALUES
(2, 'new_application',      'Nouvelle candidature reçue pour « Développeur PHP Full-Stack »', 0, NOW() - INTERVAL 6 DAY),
(2, 'new_application',      'Nouvelle candidature reçue pour « Stagiaire Développement Web »', 1, NOW() - INTERVAL 4 DAY),
(3, 'new_application',      'Nouvelle candidature reçue pour « Data Scientist Junior »', 1, NOW() - INTERVAL 10 DAY),
(5, 'application_status',   'Votre candidature pour « Développeur PHP Full-Stack » est désormais : présélectionnée', 0, NOW() - INTERVAL 5 DAY),
(5, 'application_status',   'Votre candidature pour « Stagiaire Développement Web » est désormais : retenue pour un entretien', 0, NOW() - INTERVAL 3 DAY),
(6, 'application_status',   'Votre candidature pour « Data Scientist Junior » est désormais : acceptée — vous êtes recruté(e) !', 0, NOW() - INTERVAL 9 DAY);

-- ============================================================
-- Comptes de démo (mot de passe pour tous : password123)
-- ------------------------------------------------------------
-- Admin      : admin@smarthire.ai
-- Recruteurs : sarra.mansour@acme.tn / karim.bouzid@nexatech.tn / ines.gharbi@databridge.tn
-- Candidats  : mayssa.trabelsi@mail.tn / yassine.khemiri@mail.tn / nour.ayadi@mail.tn
--              walid.jendoubi@mail.tn / salma.ferchichi@mail.tn
-- Compte désactivé (pour tester la modération admin) : amine.saidi@mail.tn
-- ============================================================

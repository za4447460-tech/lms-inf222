CREATE DATABASE IF NOT EXISTS lms_inf222
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE lms_inf222;

-- Utilisateurs
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  prenom VARCHAR(100) NOT NULL,
  email VARCHAR(150) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('etudiant','enseignant','promoteur') NOT NULL,
  avatar VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Modules (créés par le promoteur)
CREATE TABLE modules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titre VARCHAR(200) NOT NULL,
  description TEXT,
  promoteur_id INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (promoteur_id) REFERENCES users(id)
);

-- Cours (rattachés à un module, créés par l'enseignant)
CREATE TABLE courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  module_id INT NOT NULL,
  enseignant_id INT NOT NULL,
  titre VARCHAR(200) NOT NULL,
  description TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (module_id) REFERENCES modules(id),
  FOREIGN KEY (enseignant_id) REFERENCES users(id)
);

-- Leçons (PDF ou vidéo)
CREATE TABLE lessons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  titre VARCHAR(200) NOT NULL,
  type ENUM('pdf','video') NOT NULL,
  fichier_path VARCHAR(255),
  video_url VARCHAR(500),
  ordre INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id) REFERENCES courses(id)
);

-- Évaluations (liées à une leçon)
CREATE TABLE evaluations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lesson_id INT NOT NULL,
  titre VARCHAR(200) NOT NULL,
  description TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (lesson_id) REFERENCES lessons(id)
);

-- Questions d'évaluation
CREATE TABLE questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  evaluation_id INT NOT NULL,
  enonce TEXT NOT NULL,
  type ENUM('qcm','vrai_faux','texte') NOT NULL,
  FOREIGN KEY (evaluation_id) REFERENCES evaluations(id)
);

-- Choix de réponses (pour QCM)
CREATE TABLE choices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question_id INT NOT NULL,
  texte VARCHAR(300) NOT NULL,
  is_correct TINYINT(1) DEFAULT 0,
  FOREIGN KEY (question_id) REFERENCES questions(id)
);

-- Tentatives d'évaluation par étudiant
CREATE TABLE attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  evaluation_id INT NOT NULL,
  etudiant_id INT NOT NULL,
  score DECIMAL(5,2) DEFAULT 0,
  completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (evaluation_id) REFERENCES evaluations(id),
  FOREIGN KEY (etudiant_id) REFERENCES users(id)
);

-- Progression des étudiants par leçon
CREATE TABLE progress (
  id INT AUTO_INCREMENT PRIMARY KEY,
  etudiant_id INT NOT NULL,
  lesson_id INT NOT NULL,
  completed TINYINT(1) DEFAULT 0,
  progression_pct DECIMAL(5,2) DEFAULT 0,
  completed_at DATETIME,
  FOREIGN KEY (etudiant_id) REFERENCES users(id),
  FOREIGN KEY (lesson_id) REFERENCES lessons(id)
);

-- Inscriptions aux modules
CREATE TABLE enrollments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  etudiant_id INT NOT NULL,
  module_id INT NOT NULL,
  enrolled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (etudiant_id) REFERENCES users(id),
  FOREIGN KEY (module_id) REFERENCES modules(id)
);

-- Certificats
CREATE TABLE certificates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  etudiant_id INT NOT NULL,
  module_id INT NOT NULL,
  issued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  code_unique VARCHAR(100) UNIQUE,
  FOREIGN KEY (etudiant_id) REFERENCES users(id),
  FOREIGN KEY (module_id) REFERENCES modules(id)
);

-- Utilisateur de test (promoteur, mdp: Admin1234)
INSERT INTO users (nom, prenom, email, password_hash, role)
VALUES ('Messi', 'Prof', 'admin@inf222.com',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'promoteur');

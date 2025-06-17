-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : mer. 07 mai 2025 à 21:45
-- Version du serveur : 8.3.0
-- Version de PHP : 8.2.18

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `gestion_presences`
--

-- --------------------------------------------------------

--
-- Structure de la table `admins`
--

DROP TABLE IF EXISTS `admins`;
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `nom` varchar(50) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `email`, `nom`, `prenom`, `created_at`, `updated_at`) VALUES
(2, 'admin', '$2y$10$wxS4N83rVZhTt.xMAK/Z2O4bO4/87Oixj3YikbA4ufvLCUXR61n/S', 'admin@gestion-presences.com', 'Administrateur', 'Super', '2025-05-05 01:18:39', '2025-05-05 01:18:39');

-- --------------------------------------------------------

--
-- Structure de la table `attributions_enseignants`
--

DROP TABLE IF EXISTS `attributions_enseignants`;
CREATE TABLE IF NOT EXISTS `attributions_enseignants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `enseignant_id` int NOT NULL,
  `matiere_id` int NOT NULL,
  `classe_id` int NOT NULL,
  `annee_scolaire` varchar(9) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attribution` (`enseignant_id`,`matiere_id`,`classe_id`,`annee_scolaire`),
  KEY `matiere_id` (`matiere_id`),
  KEY `classe_id` (`classe_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `attributions_enseignants`
--

INSERT INTO `attributions_enseignants` (`id`, `enseignant_id`, `matiere_id`, `classe_id`, `annee_scolaire`, `created_at`) VALUES
(1, 4, 1, 1, '2025-2026', '2025-05-05 22:25:47');

-- --------------------------------------------------------

--
-- Structure de la table `classes`
--

DROP TABLE IF EXISTS `classes`;
CREATE TABLE IF NOT EXISTS `classes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) NOT NULL,
  `niveau` varchar(20) NOT NULL,
  `annee_scolaire` varchar(9) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `capacite` int NOT NULL DEFAULT '30',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `classes`
--

INSERT INTO `classes` (`id`, `nom`, `niveau`, `annee_scolaire`, `created_at`, `capacite`) VALUES
(1, 'miage', 'licence3', '', '2025-05-05 14:35:12', 30),
(2, 'genie informatique', 'licence3', '', '2025-05-05 14:35:38', 25),
(3, 'RIT', 'licence1', '', '2025-05-05 14:36:07', 50);

-- --------------------------------------------------------

--
-- Structure de la table `cours`
--

DROP TABLE IF EXISTS `cours`;
CREATE TABLE IF NOT EXISTS `cours` (
  `id` int NOT NULL AUTO_INCREMENT,
  `matiere_id` int NOT NULL,
  `classe_id` int NOT NULL,
  `enseignant_id` int NOT NULL,
  `date_cours` date NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `salle` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `statut` enum('non_commence','en_cours','termine') NOT NULL DEFAULT 'non_commence',
  PRIMARY KEY (`id`),
  KEY `matiere_id` (`matiere_id`),
  KEY `classe_id` (`classe_id`),
  KEY `enseignant_id` (`enseignant_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `cours`
--

INSERT INTO `cours` (`id`, `matiere_id`, `classe_id`, `enseignant_id`, `date_cours`, `heure_debut`, `heure_fin`, `salle`, `created_at`, `statut`) VALUES
(1, 1, 1, 4, '2025-05-06', '20:00:28', '23:00:28', NULL, '2025-05-06 20:00:28', 'termine');

-- --------------------------------------------------------

--
-- Structure de la table `emploi_temps`
--

DROP TABLE IF EXISTS `emploi_temps`;
CREATE TABLE IF NOT EXISTS `emploi_temps` (
  `id` int NOT NULL AUTO_INCREMENT,
  `classe_id` int NOT NULL,
  `matiere_id` int NOT NULL,
  `enseignant_id` int NOT NULL,
  `jour_semaine` enum('lundi','mardi','mercredi','jeudi','vendredi','samedi') NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `salle` varchar(50) DEFAULT NULL,
  `annee_scolaire` varchar(9) NOT NULL,
  `semestre` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cours` (`classe_id`,`matiere_id`,`enseignant_id`,`jour_semaine`,`heure_debut`,`annee_scolaire`,`semestre`),
  KEY `matiere_id` (`matiere_id`),
  KEY `enseignant_id` (`enseignant_id`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `emploi_temps`
--

INSERT INTO `emploi_temps` (`id`, `classe_id`, `matiere_id`, `enseignant_id`, `jour_semaine`, `heure_debut`, `heure_fin`, `salle`, `annee_scolaire`, `semestre`, `created_at`) VALUES
(6, 2, 2, 9, 'mardi', '10:00:00', '12:00:00', 'Salle 201', '2025-2026', 1, '2025-05-06 13:58:25'),
(5, 1, 1, 4, 'mercredi', '14:00:00', '16:00:00', 'Salle 101', '2025-2026', 1, '2025-05-06 13:58:25'),
(4, 1, 1, 4, 'lundi', '08:00:00', '10:00:00', 'Salle 101', '2025-2026', 1, '2025-05-06 13:58:25'),
(7, 3, 2, 9, 'jeudi', '08:00:00', '10:00:00', 'Salle 202', '2025-2026', 1, '2025-05-06 13:58:25'),
(8, 1, 3, 10, 'vendredi', '14:00:00', '16:00:00', 'Salle 301', '2025-2026', 1, '2025-05-06 13:58:25'),
(9, 2, 3, 10, 'lundi', '10:00:00', '12:00:00', 'Salle 302', '2025-2026', 1, '2025-05-06 13:58:25'),
(10, 3, 4, 4, 'jeudi', '14:00:00', '18:00:00', 'salle 502D', '2025-2026', 1, '2025-05-06 14:43:37'),
(11, 2, 1, 4, 'jeudi', '08:00:00', '12:00:00', 'salle 2Bc', '2025-2026', 1, '2025-05-06 14:45:01');

-- --------------------------------------------------------

--
-- Structure de la table `inscriptions`
--

DROP TABLE IF EXISTS `inscriptions`;
CREATE TABLE IF NOT EXISTS `inscriptions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `etudiant_id` int NOT NULL,
  `classe_id` int NOT NULL,
  `matricule` varchar(20) NOT NULL,
  `date_inscription` date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `matricule` (`matricule`),
  UNIQUE KEY `unique_inscription` (`etudiant_id`,`classe_id`),
  KEY `classe_id` (`classe_id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `inscriptions`
--

INSERT INTO `inscriptions` (`id`, `etudiant_id`, `classe_id`, `matricule`, `date_inscription`) VALUES
(3, 5, 1, '20251100', '2025-05-05');

-- --------------------------------------------------------

--
-- Structure de la table `justificatifs`
--

DROP TABLE IF EXISTS `justificatifs`;
CREATE TABLE IF NOT EXISTS `justificatifs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `etudiant_id` int NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `motif` text NOT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `statut` enum('en_attente','accepte','refuse') DEFAULT 'en_attente',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `etudiant_id` (`etudiant_id`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `justificatifs`
--

INSERT INTO `justificatifs` (`id`, `etudiant_id`, `date_debut`, `date_fin`, `motif`, `document_path`, `statut`, `created_at`, `updated_at`) VALUES
(9, 5, '2025-05-07', '2025-05-07', 'paludisme severe', 'justificatif_681b9a7832524.png', 'en_attente', '2025-05-07 17:38:00', '2025-05-07 21:30:26');

-- --------------------------------------------------------

--
-- Structure de la table `matieres`
--

DROP TABLE IF EXISTS `matieres`;
CREATE TABLE IF NOT EXISTS `matieres` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `matieres`
--

INSERT INTO `matieres` (`id`, `nom`, `description`, `created_at`) VALUES
(1, 'python', 'coder en python', '2025-05-05 14:57:49'),
(2, 'Mathématiques', 'Cours de mathématiques', '2025-05-05 22:32:18'),
(3, 'Physique', 'Cours de physique', '2025-05-05 22:32:18'),
(4, 'Informatique', 'Cours d\'informatique', '2025-05-05 22:32:18'),
(5, 'Français', 'Cours de français', '2025-05-05 22:32:18'),
(6, 'Anglais', 'Cours d\'anglais', '2025-05-05 22:32:18');

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `titre` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `type` enum('absence','retard','information','justificatif') NOT NULL,
  `lu` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `presences`
--

DROP TABLE IF EXISTS `presences`;
CREATE TABLE IF NOT EXISTS `presences` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cours_id` int NOT NULL,
  `etudiant_id` int NOT NULL,
  `date` date NOT NULL,
  `statut` enum('present','absent','retard') NOT NULL,
  `heure_pointage` timestamp NULL DEFAULT NULL,
  `commentaire` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_presence` (`cours_id`,`etudiant_id`,`date`),
  KEY `cours_id` (`cours_id`),
  KEY `etudiant_id` (`etudiant_id`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `presences`
--

INSERT INTO `presences` (`id`, `cours_id`, `etudiant_id`, `date`, `statut`, `heure_pointage`, `commentaire`, `created_at`) VALUES
(7, 4, 5, '2025-05-07', 'absent', NULL, '', '2025-05-07 17:18:23'),
(8, 5, 5, '2025-05-07', 'present', NULL, '', '2025-05-07 17:18:31'),
(9, 8, 5, '2025-05-07', 'retard', NULL, 'premier avertissement', '2025-05-07 20:21:34');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','enseignant','etudiant') NOT NULL,
  `nom` varchar(50) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `specialite` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `nom`, `prenom`, `created_at`, `updated_at`, `specialite`) VALUES
(5, 'ssamb', '$2y$10$fSOQ7VA1gvnv0U5y2WEPOOu2v4Oc1Mcx9mNc72aOOXIhUnCtMbzuq', 'sambsidy287@gmail.com', 'etudiant', 'samb', 'sidy', '2025-05-05 15:07:48', '2025-05-07 20:14:15', NULL),
(4, 'opetey', '$2y$10$2QOgFfIpo5nfy1W8SdwBqeXw5XYizrSN4BHmgJ3tjLEXYD/wa/7r2', 'peteyolivier@yahoo.fr', 'enseignant', 'petey', 'olivier', '2025-05-05 14:52:07', '2025-05-07 20:29:57', 'informatique'),
(10, 'gdjebi', '$2y$10$FJWeHQARB8J1Shu5z/JUBOYhcpOyyRvRHDuEjANQ70hlN/LbKHKiK', 'djebigabin@gmail.com', 'enseignant', 'djebi', 'gabin', '2025-05-05 22:34:51', '2025-05-05 22:34:51', 'physique'),
(9, 'kkanga', '$2y$10$HNqTNWO4dcZOrL3mgr6QXu4VkAUGO//NFla0QuYOuepa.p2y9ZbjK', 'kangakoffi@gmail.com', 'enseignant', 'kanga', 'koffi', '2025-05-05 22:33:34', '2025-05-05 22:33:34', 'mathematique');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

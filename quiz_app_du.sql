-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mar. 21 avr. 2026 à 10:34
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `quiz_app_du`
--

-- --------------------------------------------------------

--
-- Structure de la table `administrateurs`
--

CREATE TABLE `administrateurs` (
  `idadmin` int(11) NOT NULL,
  `nom` varchar(80) NOT NULL,
  `prenom` varchar(80) NOT NULL,
  `code_acces` varchar(20) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `role` enum('superadmin','admin') DEFAULT 'admin',
  `actif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email` varchar(120) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `administrateurs`
--

INSERT INTO `administrateurs` (`idadmin`, `nom`, `prenom`, `code_acces`, `mot_de_passe`, `role`, `actif`, `created_at`, `email`, `updated_at`) VALUES
(1, 'IBRAHIME MPOUH', 'Aicha', 'ADM001', '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'superadmin', 1, '2026-03-19 10:59:45', NULL, '2026-04-16 08:53:01'),
(2, 'NZE NGUEMA', 'Jean Benoît', 'ADM002', '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'superadmin', 1, '2026-03-19 10:59:45', '', '2026-04-16 08:53:01'),
(3, 'MBADINGA', 'Rufin', '01111', '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'admin', 1, '2026-03-26 14:47:59', 'rufin.mbadinga@anac-gabon.com', '2026-04-09 09:57:31');

-- --------------------------------------------------------

--
-- Structure de la table `admin_permissions`
--

CREATE TABLE `admin_permissions` (
  `id` int(11) NOT NULL,
  `idadmin` int(11) NOT NULL,
  `module` varchar(50) NOT NULL COMMENT 'dashboard|candidats|sessions|questions|resultats|evaluations|reinitialiser|administrateurs',
  `peut_voir` tinyint(1) DEFAULT 1,
  `peut_edit` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Habilitations des admins par module';

--
-- Déchargement des données de la table `admin_permissions`
--

INSERT INTO `admin_permissions` (`id`, `idadmin`, `module`, `peut_voir`, `peut_edit`) VALUES
(17, 3, 'dashboard', 1, 0),
(18, 3, 'candidats', 0, 0),
(19, 3, 'sessions', 0, 0),
(20, 3, 'questions', 1, 0),
(21, 3, 'resultats', 1, 0),
(22, 3, 'evaluations', 0, 0),
(23, 3, 'reinitialiser', 1, 0),
(24, 3, 'administrateurs', 0, 0),
(33, 2, 'dashboard', 1, 0),
(34, 2, 'candidats', 1, 1),
(35, 2, 'sessions', 1, 1),
(36, 2, 'questions', 1, 1),
(37, 2, 'resultats', 1, 0),
(38, 2, 'evaluations', 1, 0),
(39, 2, 'reinitialiser', 1, 1),
(40, 2, 'administrateurs', 1, 1);

-- --------------------------------------------------------

--
-- Structure de la table `candidat`
--

CREATE TABLE `candidat` (
  `idcandidat` int(11) NOT NULL,
  `idstagiaire` int(11) NOT NULL COMMENT 'Référence si_anac.stagiaire',
  `code_acces` int(4) NOT NULL COMMENT 'Code connexion unique  stagiaire.codeserv sur SI_ANAC',
  `mot_de_passe` varchar(255) NOT NULL,
  `role` enum('candidat','admin') DEFAULT 'candidat',
  `is_logged_in` tinyint(1) DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `tentatives` int(1) DEFAULT 0,
  `bloque` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `candidat`
--

INSERT INTO `candidat` (`idcandidat`, `idstagiaire`, `code_acces`, `mot_de_passe`, `role`, `is_logged_in`, `last_login`, `tentatives`, `bloque`) VALUES
(1, 1459, 2409, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(2, 31, 1000, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(3, 283, 1241, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, '2026-04-16 18:37:01', 0, 0),
(4, 1258, 2208, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(5, 1615, 2565, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(6, 1287, 2237, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(7, 1438, 2388, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(8, 1396, 2346, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, '2026-04-17 10:06:34', 0, 0),
(9, 1492, 2442, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(10, 468, 1426, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(11, 644, 1597, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, '2026-04-15 21:37:44', 0, 0),
(12, 185, 1143, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(13, 563, 1520, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(14, 959, 1909, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(15, 1025, 1975, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, '2026-04-16 17:50:46', 0, 0),
(16, 1122, 2072, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(17, 738, 1691, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, '2026-04-16 17:50:47', 0, 0),
(18, 1501, 2451, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(19, 1692, 2641, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(20, 931, 1881, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, '2026-04-16 18:34:36', 0, 0),
(21, 768, 1718, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(22, 1175, 2125, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(23, 172, 1130, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(24, 1197, 2147, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(25, 312, 1270, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(26, 1083, 2033, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(27, 200, 1158, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(28, 118, 1076, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(29, 955, 1905, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(30, 271, 1229, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(31, 1695, 2644, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0),
(32, 1026, 1976, '$2y$10$EnQ1EpDmvIedx98BDHS.eOknd3xTYLG0PJd.gP7YYo4P5cq.ZgwXW', 'candidat', 0, NULL, 0, 0);

-- --------------------------------------------------------

--
-- Structure de la table `candidat_session`
--

CREATE TABLE `candidat_session` (
  `id` int(11) NOT NULL,
  `idcandidat` int(11) NOT NULL,
  `id_session` int(11) NOT NULL,
  `habilite` tinyint(1) DEFAULT 1 COMMENT '1=autorisé, 0=bloqué',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `candidat_session`
--

INSERT INTO `candidat_session` (`id`, `idcandidat`, `id_session`, `habilite`, `created_at`) VALUES
(1, 10, 1, 1, '2026-04-15 20:00:48'),
(2, 11, 1, 1, '2026-04-15 20:00:49'),
(3, 6, 3, 1, '2026-04-15 20:03:05'),
(4, 6, 2, 1, '2026-04-15 20:03:05'),
(5, 8, 2, 1, '2026-04-15 20:03:05'),
(6, 14, 2, 1, '2026-04-15 20:03:05'),
(7, 12, 2, 1, '2026-04-15 20:03:06'),
(8, 13, 2, 1, '2026-04-15 20:03:06'),
(9, 15, 4, 1, '2026-04-15 20:03:27'),
(10, 1, 5, 1, '2026-04-15 20:04:30'),
(11, 16, 5, 1, '2026-04-15 20:04:30'),
(12, 17, 5, 1, '2026-04-15 20:04:30'),
(13, 15, 5, 1, '2026-04-15 20:04:30'),
(14, 3, 6, 1, '2026-04-15 20:04:49'),
(15, 3, 7, 1, '2026-04-15 20:04:49'),
(16, 18, 7, 1, '2026-04-15 20:04:49'),
(17, 6, 6, 1, '2026-04-15 20:04:49'),
(18, 9, 7, 1, '2026-04-15 20:04:49'),
(19, 7, 7, 1, '2026-04-15 20:04:49'),
(20, 20, 6, 1, '2026-04-15 20:04:50'),
(21, 3, 8, 1, '2026-04-15 20:09:54'),
(22, 6, 8, 1, '2026-04-15 20:09:54'),
(23, 20, 8, 1, '2026-04-15 20:09:54'),
(24, 3, 9, 1, '2026-04-15 20:10:17'),
(25, 6, 9, 1, '2026-04-15 20:10:17'),
(26, 20, 9, 1, '2026-04-15 20:10:17');

-- --------------------------------------------------------

--
-- Structure de la table `evaluations`
--

CREATE TABLE `evaluations` (
  `id` int(11) NOT NULL,
  `idcandidat` int(11) NOT NULL,
  `rating` varchar(20) NOT NULL,
  `commentaire` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `evaluations`
--

INSERT INTO `evaluations` (`id`, `idcandidat`, `rating`, `commentaire`, `created_at`) VALUES
(1, 11, 'satisfait', NULL, '2026-04-15 20:52:33'),
(2, 15, 'satisfait', NULL, '2026-04-16 16:40:04'),
(3, 17, 'satisfait', NULL, '2026-04-16 16:51:40'),
(4, 3, 'satisfait', NULL, '2026-04-16 17:36:00'),
(5, 20, 'satisfait', NULL, '2026-04-16 17:43:46'),
(6, 8, 'satisfait', NULL, '2026-04-17 07:14:59');

-- --------------------------------------------------------

--
-- Structure de la table `evaluation_module`
--

CREATE TABLE `evaluation_module` (
  `id` int(11) NOT NULL,
  `idcandidat` int(11) NOT NULL,
  `idmodule` int(11) NOT NULL,
  `id_session` int(11) NOT NULL,
  `note_obtenue` decimal(5,2) NOT NULL,
  `note_sur` int(11) NOT NULL,
  `pourcentage` decimal(5,2) NOT NULL,
  `reussite` tinyint(1) NOT NULL,
  `date_eval` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `evaluation_module`
--

INSERT INTO `evaluation_module` (`id`, `idcandidat`, `idmodule`, `id_session`, `note_obtenue`, `note_sur`, `pourcentage`, `reussite`, `date_eval`) VALUES
(1, 3, 15, 8, 4.00, 16, 25.00, 0, '2026-04-16 17:35:35'),
(2, 3, 17, 9, 6.00, 16, 37.50, 0, '2026-04-16 17:37:20'),
(3, 20, 15, 8, 8.00, 16, 50.00, 0, '2026-04-16 17:42:56');

-- --------------------------------------------------------

--
-- Structure de la table `module_formation`
--

CREATE TABLE `module_formation` (
  `idmodule` int(11) NOT NULL,
  `idtypeformation` int(11) NOT NULL COMMENT 'Référence si_anac.typeformation',
  `numero_module` int(2) NOT NULL,
  `nom_module_fr` varchar(255) NOT NULL,
  `nom_module_en` varchar(255) NOT NULL,
  `actif` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `module_formation`
--

INSERT INTO `module_formation` (`idmodule`, `idtypeformation`, `numero_module`, `nom_module_fr`, `nom_module_en`, `actif`) VALUES
(1, 38, 2, 'Module 2 : Cadre juridique et réglementaire', 'Module 2: Legal and regulatory framework', 1),
(2, 38, 3, 'Module 3 : Mesures de sûreté', 'Module 3: Security measures', 1),
(3, 38, 4, 'Module 4 : Contrôle d\'accès', 'Module 4: Access control', 1),
(4, 38, 6, 'Module 6 : Fouille des bagages', 'Module 6: Baggage search', 1),
(5, 38, 8, 'Module 8 : Inspection filtrage', 'Module 8: Security screening', 1),
(6, 38, 9, 'Module 9 : Gestion des incidents', 'Module 9: Incident management', 1),
(7, 38, 11, 'Module 11 : Facteurs humains et comportementaux', 'Module 11: Human and behavioural factors', 1),
(8, 115, 1, 'MODULE', '', 1),
(9, 115, 2, 'MODULE', '', 1),
(10, 45, 4, 'MODULE', '', 1),
(11, 45, 11, 'gestion exasur', '', 1),
(12, 40, 4, 'GESTION DES HABILITATIONS', '', 1),
(13, 40, 9, 'PROFIL EXASUR', '', 1),
(14, 40, 6, 'fouille bagage', '', 1),
(15, 43, 2, 'Surete fret module 2', '', 1),
(16, 43, 3, 'Mod.2 Surete fret module 3', '', 1),
(17, 43, 4, 'Mod.4 Surete fret module 4', '', 1),
(18, 43, 8, 'maintenance', '', 1),
(19, 43, 11, '11module', '', 1),
(20, 41, 2, 'SCAN', '', 1),
(21, 41, 3, 'radioactif', '', 1),
(22, 41, 8, 'laser', '', 1);

-- --------------------------------------------------------

--
-- Structure de la table `progression_candidat`
--

CREATE TABLE `progression_candidat` (
  `id` int(11) NOT NULL,
  `idcandidat` int(11) NOT NULL,
  `id_session` int(11) NOT NULL,
  `partie_encours` enum('theorique','pratique') DEFAULT NULL,
  `current_index_theo` int(11) DEFAULT 0,
  `current_index_pra` int(11) DEFAULT 0,
  `infractions` int(11) DEFAULT 0,
  `ordre_questions_theo` longtext DEFAULT NULL,
  `ordre_questions_pra` longtext DEFAULT NULL,
  `reponses_json` longtext DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `progression_candidat`
--

INSERT INTO `progression_candidat` (`id`, `idcandidat`, `id_session`, `partie_encours`, `current_index_theo`, `current_index_pra`, `infractions`, `ordre_questions_theo`, `ordre_questions_pra`, `reponses_json`, `updated_at`) VALUES
(1, 11, 1, 'theorique', 49, 0, 3, '[{\"id\":136,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"En cas d\'absence de personnels sp\\u00e9cialis\\u00e9s en d\\u00e9minage, le personnel de Police peut proc\\u00e9der au d\\u00e9samor\\u00e7age d\'un EEI\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":183,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"L\'acc\\u00e8s en zone Tri bagage est r\\u00e9serv\\u00e9 aux personnes d\\u00e9tentrices des badges avec la zone : (Choisir la r\\u00e9ponse fausse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) TRA\",\"option1_en\":\"\",\"option2_fr\":\"b) B\",\"option2_en\":\"\",\"option3_fr\":\"c) A\",\"option3_en\":null,\"option4_fr\":\"d) F\",\"option4_en\":null,\"correct_option\":4,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":154,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Quel est le sort r\\u00e9serv\\u00e9 aux articles confisqu\\u00e9s au PIF. Choisir la bonne r\\u00e9ponse\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) d\\u00e9truits par le chef de poste\",\"option1_en\":\"\",\"option2_fr\":\"b) transmis \\u00e0 la compagnie a\\u00e9rienne\",\"option2_en\":\"\",\"option3_fr\":\"c) transmis au Chef d\'\\u00e9quipe\",\"option3_en\":null,\"option4_fr\":\"d) prendre pour l\'usage du personnel du PIF\",\"option4_en\":null,\"correct_option\":3,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":137,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Le seuil de r\\u00e9ussite (moyenne) \\u00e0 l\'examen de certification est de 80%\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":123,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Les agents charg\\u00e9s de la patrouille dans les halls de l\'a\\u00e9rogare passagers ne doivent pas s\'occuper de la surveillance des colis abandonn\\u00e9s parce que c\'est le r\\u00f4le de l\'\\u00e9quipe de la vid\\u00e9osurveillance\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":138,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Le personnel a\\u00e9roportuaire doit \\u00eatre inspect\\u00e9\\/filtr\\u00e9 \\u00e0 un PIF de la m\\u00eame fa\\u00e7on qu\'un passager\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":106,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"La GTA est en charge des mesures de s\\u00fbret\\u00e9 cot\\u00e9 piste et cote ville\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":193,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Quel est le principal document de travail d\'un agent de s\\u00fbret\\u00e9 \\u00e0 un PIF?\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) PEN\",\"option1_en\":\"\",\"option2_fr\":\"b) PNSAC\",\"option2_en\":\"\",\"option3_fr\":\"c) AVSEC-FAL\",\"option3_en\":null,\"option4_fr\":\"d) PNSQ\",\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":164,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"La PEN du PIF salle d\'embarquement fait obligation de faire la fouille de la zone st\\u00e9rile. Quel est l\'objectif de cette fouille ? Choisir la bonne r\\u00e9ponse\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) s\'assurer qu\'aucun passager n\'a oubli\\u00e9 ses bagages contenant des objets de valeurs dans la zone\",\"option1_en\":\"\",\"option2_fr\":\"b) s\'assurer de la propret\\u00e9 des lieux avant sa mise en utilisation\",\"option2_en\":\"\",\"option3_fr\":\"c) s\'assurer que toutes les portes menant au c\\u00f4t\\u00e9 piste sont verrouill\\u00e9es\",\"option3_en\":null,\"option4_fr\":\"d) s\'assurer que la zone ne contient aucun article pouvant servir \\u00e0 commettre un acte d\'intervention illicite\",\"option4_en\":null,\"correct_option\":4,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":192,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Un passager mal voyant se pr\\u00e9sente au PIF. Quelles sont les dispositions \\u00e0 prendre pour son inspection filtrage ? (Choisir la bonne r\\u00e9ponse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) demander l\'assistance d\'un t\\u00e9moin\",\"option1_en\":\"\",\"option2_fr\":\"b) demander une attestation m\\u00e9dicale prouvant qu\'il est mal voyant\",\"option2_en\":\"\",\"option3_fr\":\"c) lui appliquer une fouille de niveau 2\",\"option3_en\":null,\"option4_fr\":\"d) Toutes les r\\u00e9ponses ci-dessus\",\"option4_en\":null,\"correct_option\":4,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":172,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Avant de proc\\u00e9der \\u00e0 la fouille manuelle d\'un bagage d\'un passager qui est s\\u00e9lectionn\\u00e9 suivant la r\\u00e8gle de fouille al\\u00e9atoire, l\'agent de s\\u00fbret\\u00e9 doit (Choisir la bonne r\\u00e9ponse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) expliquer au passager ce pourquoi le bagage est choisi\",\"option1_en\":\"\",\"option2_fr\":\"b) demander au passager de se mettre \\u00e0 l\'\\u00e9cart de la table de fouille\",\"option2_en\":\"\",\"option3_fr\":\"c) demander au passager d\'ouvrir son bagage pour avoir son consentement\",\"option3_en\":null,\"option4_fr\":\"d) solliciter un agent de s\\u00fbret\\u00e9 de m\\u00eame sexe pour ouvrir le bagage\",\"option4_en\":null,\"correct_option\":3,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":124,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Le Directeur g\\u00e9n\\u00e9ral de GSEZ -Airport assure la pr\\u00e9sidence du COLSA de Libreville\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":169,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Les agents de maintenance des \\u00e9quipements de s\\u00fbret\\u00e9 ont la responsabilit\\u00e9. Choisir la r\\u00e9ponse fausse\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) d\'assurer la maintenance des \\u00e9quipements\",\"option1_en\":\"\",\"option2_fr\":\"b) de faire les v\\u00e9rifications de bon fonctionnement des \\u00e9quipements\",\"option2_en\":\"\",\"option3_fr\":\"d) de renseigner le passage des agents Op\\u00e9rateurs\",\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":3,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":101,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Suivant la PEN du PIF salle embarquement, le minimum d\'agent est de trois (03)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":112,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"La cl\\u00f4ture de l\'a\\u00e9roport a pour objet principal d\'emp\\u00eacher les gens de regarder les a\\u00e9ronefs\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":163,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Vous \\u00eates superviseur du PIF : le passager refuse de soumettre son bagage de cabine \\u00e0 la fouille manuelle. Choisir la bonne r\\u00e9ponse\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) le passager est imm\\u00e9diatement interpell\\u00e9\",\"option1_en\":\"\",\"option2_fr\":\"b) le bagage est confisqu\\u00e9\",\"option2_en\":\"\",\"option3_fr\":\"c) avoir l\'autorisation de la compagnie a\\u00e9rienne avant de le laissez-passer\",\"option3_en\":null,\"option4_fr\":\"d) aucune r\\u00e9ponse ci-dessus\",\"option4_en\":null,\"correct_option\":4,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":102,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Suivant la PEN du PIF salle embarquement, la prise de service ne prend pas en compte le statut des \\u00e9quipements\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":155,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Avant d\'\\u00eatre candidat \\u00e0 la certification, le personnel d\'inspection filtrage doit avoir suivi avec succ\\u00e8s les formations ci-apr\\u00e8s. Choisir la bonne r\\u00e9ponse.\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) Gestion de crises et 123 BASE\",\"option1_en\":\"\",\"option2_fr\":\"b) S\\u00fbret\\u00e9 du fret et imagerie radioscopique\",\"option2_en\":\"\",\"option3_fr\":\"c) 123 BASE et imagerie radioscopique\",\"option3_en\":null,\"option4_fr\":\"d) 123 BASE et S\\u00fbret\\u00e9 du fret\",\"option4_en\":null,\"correct_option\":3,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":165,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Parmi les articles ci-apr\\u00e8s lequel n\'est pas autoris\\u00e9 en soute. Choisir la bonne r\\u00e9ponse\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) Pistolets \\u00e0 fus\\u00e9es de signalisation\",\"option1_en\":\"\",\"option2_fr\":\"b) Pistolets d\'enfants de tous types\",\"option2_en\":\"\",\"option3_fr\":\"c) Pistolets de d\\u00e9part\",\"option3_en\":null,\"option4_fr\":\"d) Explosifs\",\"option4_en\":null,\"correct_option\":4,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":152,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Tout engin explosif a quatre composantes principales : laquelle des r\\u00e9ponses ci-dessous ne fait pas partie\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) Bloc d\'alimentation\",\"option1_en\":\"\",\"option2_fr\":\"b) Minuterie \\/ m\\u00e9canisme de retardement\",\"option2_en\":\"\",\"option3_fr\":\"c) D\\u00e9tonateur (ou initiateur)\",\"option3_en\":null,\"option4_fr\":\"d) Mati\\u00e8re incendiaire\",\"option4_en\":null,\"correct_option\":4,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":158,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Quelles mesures doivent \\u00eatre prises si un m\\u00e9lange ou un contact entre des passagers ayant \\u00e9t\\u00e9 soumis \\u00e0 l\'inspection filtrage et d\'autres personnes non soumises \\u00e0 ce contr\\u00f4le se r\\u00e9alise ? Choisir la bonne r\\u00e9ponse\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) aucune mesure\",\"option1_en\":\"\",\"option2_fr\":\"b) tous les passagers doivent \\u00eatre soumis de nouveau \\u00e0 l\'inspection filtrage apr\\u00e8s la fouille de la salle d\'attente\",\"option2_en\":\"\",\"option3_fr\":\"c) annuler tous les vols et demander aux passagers de revenir le lendemain\",\"option3_en\":null,\"option4_fr\":\"d) toutes les r\\u00e9ponses ci-dessus\",\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":189,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"En cas de d\\u00e9couverte d\'un article suspect, l\'agent de s\\u00fbret\\u00e9 doit suivre les consignes suivantes (Choisir la r\\u00e9ponse fausse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) ne pas toucher l\'engin ni le d\\u00e9placer\",\"option1_en\":\"\",\"option2_fr\":\"b) laisser si possible quelque chose de distinctif aupr\\u00e8s de l\'engin sans le toucher\",\"option2_en\":\"\",\"option3_fr\":\"c) s\'\\u00e9loigner de l\'engin\",\"option3_en\":null,\"option4_fr\":\"d) prendre une photographie de l\'engin pour compte rendu\",\"option4_en\":null,\"correct_option\":4,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":187,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Le personnel de la GTA peut acc\\u00e9der cot\\u00e9 piste par la porte d\'acc\\u00e8s Brigade GTA (Choisir la bonne r\\u00e9ponse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) en pr\\u00e9sentant seulement le badge s\\u00fbret\\u00e9\",\"option1_en\":\"\",\"option2_fr\":\"b) en se pr\\u00e9sentant avec l\'uniforme et le badge s\\u00fbret\\u00e9\",\"option2_en\":\"\",\"option3_fr\":\"c) en se faisant accompagner par un gendarme en tenue\",\"option3_en\":null,\"option4_fr\":\"d) aucune des r\\u00e9ponses ci-dessus\",\"option4_en\":null,\"correct_option\":4,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":144,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Les passagers en correspondance sont soumis \\u00e0 un contr\\u00f4le all\\u00e9g\\u00e9 parce qu\'ils ont d\\u00e9j\\u00e0 fait l\'objet de contr\\u00f4les sur les a\\u00e9roports de d\\u00e9part.\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":174,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Au PIF correspondance des passagers, un passager se pr\\u00e9sente avec des bouteilles de liqueur contenues dans un sac de s\\u00fbret\\u00e9 \\u00e0 indicateur d\'effraction scell\\u00e9 (STEB) fourni par le vendeur. Que faites-vous ? (Choisir la bonne r\\u00e9ponse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) refuser l\'acc\\u00e8s si les bouteilles ont une contenance sup\\u00e9rieure \\u00e0 100ml\",\"option1_en\":\"\",\"option2_fr\":\"b) appeler le repr\\u00e9sentant de la compagnie a\\u00e9rienne pour avoir son accord\",\"option2_en\":\"\",\"option3_fr\":\"c) le soumettre \\u00e0 l\'examen radioscopique\",\"option3_en\":null,\"option4_fr\":\"d) confisquer automatiquement les bouteilles et rendre compte au chef de poste\",\"option4_en\":null,\"correct_option\":3,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":176,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Un passager accompagn\\u00e9 d\'un nourrisson dans une poussette se pr\\u00e9sente au PIF. Que faites-vous ? (Choisir la r\\u00e9ponse fausse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) le nourrisson doit \\u00eatre retir\\u00e9 de la poussette avant d\'\\u00eatre inspect\\u00e9 filtr\\u00e9\",\"option1_en\":\"\",\"option2_fr\":\"b) la poussette doit \\u00eatre inspect\\u00e9e filtr\\u00e9e s\\u00e9par\\u00e9ment au RX\",\"option2_en\":\"\",\"option3_fr\":\"c) l\'agent de s\\u00fbret\\u00e9 retire le nourrisson et traverse le portique\",\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":3,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":129,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"En cas de d\\u00e9couverte d\'un article class\\u00e9 dans la cat\\u00e9gorie des mati\\u00e8res dangereuses, le repr\\u00e9sentant de la compagnie doit \\u00eatre inform\\u00e9 pour qu\'il prenne les mesures appropri\\u00e9es\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":132,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Le personnel d\'inspection filtrage doit \\u00eatre certifi\\u00e9 parce que c\'est une norme de l\'OACI et une exigence nationale avant d\'\\u00eatre \\u00eatre autoris\\u00e9 \\u00e0 inspecter filtrer les passagers.\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":160,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"A l\'a\\u00e9roport de Libreville, les zones ci-apr\\u00e8s sont des zones publiques. Choisir la r\\u00e9ponse fausse\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) le salons VIP SAMBA\",\"option1_en\":\"\",\"option2_fr\":\"b) le salons VIP EKENA\",\"option2_en\":\"\",\"option3_fr\":\"c) le parking auto\",\"option3_en\":null,\"option4_fr\":\"d) la salle d\'enregistrement\",\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":100,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Parmi les personnes exempt\\u00e9es de l\'inspection filtrage conform\\u00e9ment \\u00e0 la PEN se trouve les ministres des affaires \\u00e9trang\\u00e8res\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":159,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"En effectuant l\'inspection filtrage d\'un passager, il est essentiel de s\'assurer que toutes les alarmes sont trait\\u00e9es. Choisir la bonne r\\u00e9ponse.\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) en demandant aux passagers de nous dire quels sont les objets m\\u00e9talliques qu\'il porte sur lui.\",\"option1_en\":\"\",\"option2_fr\":\"b) en diminuant le volume de l\'alarme sonore\",\"option2_en\":\"\",\"option3_fr\":\"c) en demandant au passager de pr\\u00e9senter son passeport et sa carte d\'embarquement\",\"option3_en\":null,\"option4_fr\":\"d) aucune des r\\u00e9ponses ci-dessus\",\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":126,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"HPG et le COMPOL assurent les m\\u00eames missions de s\\u00fbret\\u00e9\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":130,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"la certification du personnel de s\\u00fbret\\u00e9 n\'est pas exig\\u00e9e aux services de l\'Etat qui assurent l\'IF\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":153,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Certaines mati\\u00e8res r\\u00e9glement\\u00e9es, bien qu\'elles ne soient pas autoris\\u00e9es \\u00e0 voyager en cabine, peuvent \\u00eatre transport\\u00e9es dans la soute d\'un a\\u00e9ronef dans certaines conditions. Choisir la bonne r\\u00e9ponse\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) autoris\\u00e9es par le transporteur a\\u00e9rien\",\"option1_en\":\"\",\"option2_fr\":\"b) autoris\\u00e9es par l\'ONSFAG\",\"option2_en\":\"\",\"option3_fr\":\"c) ne d\\u00e9passent pas 100ml\",\"option3_en\":null,\"option4_fr\":\"d) autoris\\u00e9es par l\'ANAC\",\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":104,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Un passager sous garde judiciaire accompagn\\u00e9 d\'une escorte est exempt\\u00e9 de l\'inspection filtrage au PIF\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":148,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Les LAG contenus dans des r\\u00e9cipients de plus de 100ml sont accept\\u00e9s en cabine s\'ils sont partiellement remplis\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":127,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Parmi les articles interdits en cabine, les munitions sont consid\\u00e9r\\u00e9es comme \\u00ab Substances Explosives \\u00bb\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":180,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"En cas de panne du RX au PIF des passagers que faites-vous ? (Choisir la r\\u00e9ponse fausse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) rendre compte imm\\u00e9diatement au superviseur\",\"option1_en\":\"\",\"option2_fr\":\"b) proc\\u00e9der syst\\u00e9matiquement \\u00e0 la fouille manuelle de 100% des bagages de cabine\",\"option2_en\":\"\",\"option3_fr\":\"c) attendre la r\\u00e9paration du RX\",\"option3_en\":null,\"option4_fr\":\"d) informer sans d\\u00e9lai les techniciens de maintenance\",\"option4_en\":null,\"correct_option\":3,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":121,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"L\'ANAC est l\'autorit\\u00e9 a\\u00e9ronautique du Gabon\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":118,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Le Passage au PIF du Directeur G\\u00e9n\\u00e9ral de l\'ONSFAG ne fait pas l\'objet d\'une mention dans le registre\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":171,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Dans le cas o\\u00f9 l\'agent de s\\u00fbret\\u00e9 au PIF se trouve en face d\'une situation avec un personnel a\\u00e9roportuaire et que cette situation n\'est pas pr\\u00e9vue par la PEN, il doit (Choisir la bonne r\\u00e9ponse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) faire preuve d\'imagination et trouver la solution\",\"option1_en\":\"\",\"option2_fr\":\"b) s\'adresser au chef de poste pour la suite \\u00e0 donner\",\"option2_en\":\"\",\"option3_fr\":\"c) demander au personnel de proposer la solution appropri\\u00e9e\",\"option3_en\":null,\"option4_fr\":\"d) aucune des r\\u00e9ponses ci-dessus\",\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":178,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"En cas de panne du portique, que faites-vous ? (Choisir la r\\u00e9ponse fausse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) rendre compte imm\\u00e9diatement au superviseur\",\"option1_en\":\"\",\"option2_fr\":\"b) proc\\u00e9der syst\\u00e9matiquement \\u00e0 la palpation des passagers\",\"option2_en\":\"\",\"option3_fr\":\"c) informer sans d\\u00e9lai les techniciens de maintenance\",\"option3_en\":null,\"option4_fr\":\"d) utiliser le d\\u00e9tecteur manuel de m\\u00e9taux\",\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":125,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Les agents charg\\u00e9s de la patrouille dans les halls de l\'a\\u00e9rogare passagers ne doivent pas dresser un rapport de patrouille parce que c\'est le r\\u00f4le de l\'\\u00e9quipe de la vid\\u00e9osurveillance\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":107,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"HPG a pour mission d\'assurer la protection des installations sensibles du cot\\u00e9 piste\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":105,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"La fouille de niveau 2 est faite n\\u00e9cessairement dans un isoloir sans un t\\u00e9moin\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":191,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"La liste des badges perdus permet \\u00e0 un agent au PIF de (Choisir la bonne r\\u00e9ponse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) s\'assurer qu\'aucun badge perdu ne sera utilis\\u00e9 pour acc\\u00e9der frauduleusement\",\"option1_en\":\"\",\"option2_fr\":\"b) connaitre le nombre de badge qui ne sont plus en la possession des titulaires\",\"option2_en\":\"\",\"option3_fr\":\"c) identifier les vrais titulaires des badges perdus\",\"option3_en\":null,\"option4_fr\":\"d) toutes les r\\u00e9ponses ci-dessus\",\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":128,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Les mati\\u00e8res toxiques sont consid\\u00e9r\\u00e9es comme des mati\\u00e8res dangereuses et sont interdites en cabine\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":115,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"Au PIF filtrage des bagages de soute, l\'agent charg\\u00e9 de l\'examen du bagage au moyen de l\'EDS, en cas de d\\u00e9tection d\'une menace, demande n\\u00e9cessairement \\u00e0 un coll\\u00e8gue de proc\\u00e9der \\u00e0 une fouille manuelle ou \\u00e0 un examen \\u00e0 l\'ETD\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":184,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"L\'acc\\u00e8s peut \\u00eatre accord\\u00e9 \\u00e0 un personnel a\\u00e9roportuaire non d\\u00e9tenteur de badge dans les cas suivants (Choisir la bonne r\\u00e9ponse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) il explique que son badge est perdu la veille\",\"option1_en\":\"\",\"option2_fr\":\"b) il vous informe qu\'il a re\\u00e7u une autorisation verbale du D\\u00e9l\\u00e9gu\\u00e9 de l\'ONSFAG\",\"option2_en\":\"\",\"option3_fr\":\"c) ce personnel est connu comme instructeur en s\\u00fbret\\u00e9\",\"option3_en\":null,\"option4_fr\":\"d) aucune des r\\u00e9ponses ci-dessus\",\"option4_en\":null,\"correct_option\":4,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":143,\"idtype_examen\":1,\"type_question\":\"theorique\",\"question_text_fr\":\"En cas d\'absence du passager, la fouille de son bagage de soute peut \\u00eatre faite en pr\\u00e9sence d\'un repr\\u00e9sentant de la compagnie a\\u00e9rienne\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null}]', NULL, '{\"191\":1,\"104\":1,\"121\":1,\"100\":1,\"158\":1,\"192\":1,\"187\":2,\"183\":1,\"129\":1,\"163\":1,\"106\":1,\"178\":2,\"176\":1,\"112\":1,\"126\":1,\"172\":1,\"105\":1,\"107\":1,\"127\":1,\"193\":1,\"153\":1,\"128\":1,\"152\":1,\"143\":1,\"164\":1,\"101\":1,\"115\":1,\"165\":1,\"159\":2,\"184\":2,\"155\":2,\"171\":2,\"136\":1,\"169\":2,\"180\":3,\"137\":1,\"174\":3,\"102\":1,\"160\":1,\"189\":1,\"138\":1,\"124\":1,\"154\":3,\"148\":1,\"123\":1,\"130\":1,\"144\":1,\"118\":1,\"125\":1,\"132\":1}', '2026-04-15 20:41:10'),
(2, 15, 4, 'theorique', 9, 0, 3, '[{\"id\":33,\"idtype_examen\":3,\"type_question\":\"theorique\",\"question_text_fr\":\"Comment \\u00e9valuer efficacement l\'acquisition des comp\\u00e9tences lors d\'une formation en s\\u00fbret\\u00e9 ?\",\"question_text_en\":\"How to effectively assess skill acquisition during security training?\",\"images\":null,\"images_data\":null,\"option1_fr\":\"Par des tests pratiques, mises en situation r\\u00e9elles et \\u00e9valuations formatives continues\",\"option1_en\":\"Through practical tests, real-world simulations and continuous formative assessments\",\"option2_fr\":\"Par le nombre d\'heures de pr\\u00e9sence et la signature des feuilles uniquement\",\"option2_en\":\"By attendance hours and signature sheets only\",\"option3_fr\":\"Par la satisfaction globale des apprenants en fin de session\",\"option3_en\":\"By overall learner satisfaction at the end of the session\",\"option4_fr\":\"Par l\'obtention d\'un dipl\\u00f4me universitaire\",\"option4_en\":\"By obtaining a university degree\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":39,\"idtype_examen\":3,\"type_question\":\"theorique\",\"question_text_fr\":\"Un instructeur certifi\\u00e9 d\\u00e9couvre qu\'un agent de son groupe poss\\u00e8de une certification de s\\u00fbret\\u00e9 falsifi\\u00e9e. Il doit :\",\"question_text_en\":\"A certified instructor discovers that an agent in their group has a forged security certification. They must:\",\"images\":null,\"images_data\":null,\"option1_fr\":\"Signaler imm\\u00e9diatement le fait \\u00e0 l\'ANAC (autorit\\u00e9 comp\\u00e9tente)\",\"option1_en\":\"Immediately report the fact to ANAC (competent authority)\",\"option2_fr\":\"Ignorer si l\'agent semble techniquement comp\\u00e9tent\",\"option2_en\":\"Ignore if the agent seems technically competent\",\"option3_fr\":\"En discuter en premier lieu avec ses coll\\u00e8gues instructeurs\",\"option3_en\":\"Discuss it first with fellow instructors\",\"option4_fr\":\"Attendre une inspection officielle de l\'ANAC pour en parler\",\"option4_en\":\"Wait for an official ANAC inspection to mention it\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":38,\"idtype_examen\":3,\"type_question\":\"theorique\",\"question_text_fr\":\"Dans une session de formation en s\\u00fbret\\u00e9, la proportion recommand\\u00e9e pratique\\/th\\u00e9orie est :\",\"question_text_en\":\"In a security training session, the recommended practical\\/theory ratio is:\",\"images\":null,\"images_data\":null,\"option1_fr\":\"70% pratique \\/ 30% th\\u00e9orie pour une meilleure acquisition des comp\\u00e9tences\",\"option1_en\":\"70% practical \\/ 30% theory for better skill acquisition\",\"option2_fr\":\"50% pratique \\/ 50% th\\u00e9orie \\u00e9quilibr\\u00e9\",\"option2_en\":\"50% practical \\/ 50% theory balanced\",\"option3_fr\":\"30% pratique \\/ 70% th\\u00e9orie avec accent sur les textes\",\"option3_en\":\"30% practical \\/ 70% theory with emphasis on texts\",\"option4_fr\":\"100% th\\u00e9orie pour garantir les connaissances r\\u00e9glementaires\",\"option4_en\":\"100% theory to guarantee regulatory knowledge\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":31,\"idtype_examen\":3,\"type_question\":\"theorique\",\"question_text_fr\":\"Quelles sont les qualit\\u00e9s fondamentales d\'un bon instructeur en s\\u00fbret\\u00e9 a\\u00e9ronautique ?\",\"question_text_en\":\"What are the fundamental qualities of a good aeronautical security instructor?\",\"images\":null,\"images_data\":null,\"option1_fr\":\"P\\u00e9dagogie, expertise technique et capacit\\u00e9 d\'adaptation aux apprenants\",\"option1_en\":\"Pedagogy, technical expertise and adaptability to learners\",\"option2_fr\":\"Autorit\\u00e9 stricte, discipline rigide et ton imposant\",\"option2_en\":\"Strict authority, rigid discipline and imposing tone\",\"option3_fr\":\"Rapidit\\u00e9 d\'ex\\u00e9cution et d\\u00e9bit de parole \\u00e9lev\\u00e9 uniquement\",\"option3_en\":\"Speed of execution and high speech rate only\",\"option4_fr\":\"Disponibilit\\u00e9 sans expertise ni formation valid\\u00e9e\",\"option4_en\":\"Availability without validated expertise or training\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":40,\"idtype_examen\":3,\"type_question\":\"theorique\",\"question_text_fr\":\"L\'audit de qualit\\u00e9 d\'une formation en s\\u00fbret\\u00e9 a\\u00e9ronautique est r\\u00e9alis\\u00e9 par :\",\"question_text_en\":\"Quality audit of aeronautical security training is carried out by:\",\"images\":null,\"images_data\":null,\"option1_fr\":\"L\'ANAC et\\/ou des auditeurs ind\\u00e9pendants accr\\u00e9dit\\u00e9s OACI\",\"option1_en\":\"ANAC and\\/or ICAO-accredited independent auditors\",\"option2_fr\":\"L\'instructeur lui-m\\u00eame en auto-\\u00e9valuation uniquement\",\"option2_en\":\"The instructor alone through self-assessment only\",\"option3_fr\":\"Les apprenants par notation anonyme\",\"option3_en\":\"Learners through anonymous rating\",\"option4_fr\":\"Le responsable RH de l\'organisme de formation\",\"option4_en\":\"The HR manager of the training organisation\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":34,\"idtype_examen\":3,\"type_question\":\"theorique\",\"question_text_fr\":\"La certification d\'instructeur AVSEC doit \\u00eatre renouvel\\u00e9e tous les :\",\"question_text_en\":\"AVSEC instructor certification must be renewed every:\",\"images\":null,\"images_data\":null,\"option1_fr\":\"3 ans avec formation continue obligatoire\",\"option1_en\":\"3 years with mandatory continuous training\",\"option2_fr\":\"1 an\",\"option2_en\":\"1 year\",\"option3_fr\":\"2 ans\",\"option3_en\":\"2 years\",\"option4_fr\":\"5 ans sans formation compl\\u00e9mentaire\",\"option4_en\":\"5 years without additional training\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":37,\"idtype_examen\":3,\"type_question\":\"theorique\",\"question_text_fr\":\"La m\\u00e9thode p\\u00e9dagogique SAVI dans le contexte de la formation AVSEC correspond \\u00e0 :\",\"question_text_en\":\"The SAVI teaching method in the AVSEC training context corresponds to:\",\"images\":null,\"images_data\":null,\"option1_fr\":\"Savoir \\u2014 Attitudes \\u2014 Valeurs \\u2014 Int\\u00e9r\\u00eats (comp\\u00e9tences globales)\",\"option1_en\":\"Knowledge \\u2014 Attitudes \\u2014 Values \\u2014 Interests (global competencies)\",\"option2_fr\":\"Situation \\u2014 Action \\u2014 V\\u00e9rification \\u2014 Impact\",\"option2_en\":\"Situation \\u2014 Action \\u2014 Verification \\u2014 Impact\",\"option3_fr\":\"Strat\\u00e9gie \\u2014 Application \\u2014 Valorisation \\u2014 Int\\u00e9gration\",\"option3_en\":\"Strategy \\u2014 Application \\u2014 Valorisation \\u2014 Integration\",\"option4_fr\":\"Synth\\u00e8se \\u2014 Analyse \\u2014 Vision \\u2014 Impl\\u00e9mentation\",\"option4_en\":\"Synthesis \\u2014 Analysis \\u2014 Vision \\u2014 Implementation\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":32,\"idtype_examen\":3,\"type_question\":\"theorique\",\"question_text_fr\":\"Quel document d\\u00e9finit le Programme National de Formation des agents de s\\u00fbret\\u00e9 au Gabon ?\",\"question_text_en\":\"Which document defines the National Security Agent Training Programme in Gabon?\",\"images\":null,\"images_data\":null,\"option1_fr\":\"PNFSAC \\u2014 Programme National de Formation en S\\u00fbret\\u00e9 de l\'Aviation Civile\",\"option1_en\":\"PNFSAC \\u2014 National Civil Aviation Security Training Programme\",\"option2_fr\":\"PNSAC \\u2014 Programme National de S\\u00fbret\\u00e9\",\"option2_en\":\"PNSAC \\u2014 National Security Programme\",\"option3_fr\":\"RAG 3 \\u2014 R\\u00e9glementation A\\u00e9ronautique Gabonaise\",\"option3_en\":\"RAG 3 \\u2014 Gabonese Aeronautical Regulation\",\"option4_fr\":\"L\'Annexe 17 de l\'OACI uniquement\",\"option4_en\":\"ICAO Annex 17 only\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":36,\"idtype_examen\":3,\"type_question\":\"theorique\",\"question_text_fr\":\"Le plan de cours d\'un instructeur certifi\\u00e9 doit obligatoirement inclure :\",\"question_text_en\":\"A certified instructor\'s lesson plan must include:\",\"images\":null,\"images_data\":null,\"option1_fr\":\"Objectifs d\'apprentissage, contenu structur\\u00e9, m\\u00e9thodes p\\u00e9dagogiques et modalit\\u00e9s d\'\\u00e9valuation\",\"option1_en\":\"Learning objectives, structured content, teaching methods and assessment modalities\",\"option2_fr\":\"Uniquement la date, la salle et le nom de l\'instructeur\",\"option2_en\":\"Only the date, room and instructor name\",\"option3_fr\":\"La liste des apprenants et le budget allou\\u00e9\",\"option3_en\":\"The learner list and allocated budget\",\"option4_fr\":\"Le nombre d\'heures pr\\u00e9vu et le mat\\u00e9riel disponible\",\"option4_en\":\"The planned number of hours and available materials\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":35,\"idtype_examen\":3,\"type_question\":\"theorique\",\"question_text_fr\":\"Quel organisme d\\u00e9livre et valide la certification d\'instructeur en s\\u00fbret\\u00e9 au Gabon ?\",\"question_text_en\":\"Which body issues and validates security instructor certification in Gabon?\",\"images\":null,\"images_data\":null,\"option1_fr\":\"L\'ANAC Gabon (Agence Nationale de l\'Aviation Civile)\",\"option1_en\":\"ANAC Gabon (National Civil Aviation Agency)\",\"option2_fr\":\"L\'IATA (Association Internationale du Transport A\\u00e9rien)\",\"option2_en\":\"IATA (International Air Transport Association)\",\"option3_fr\":\"L\'OACI directement par ses bureaux r\\u00e9gionaux\",\"option3_en\":\"ICAO directly through its regional offices\",\"option4_fr\":\"Le Minist\\u00e8re gabonais des Transports exclusivement\",\"option4_en\":\"The Gabonese Ministry of Transport exclusively\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null}]', NULL, '{\"31\":1,\"34\":1,\"33\":1,\"40\":2,\"32\":1,\"38\":1,\"36\":1,\"37\":1,\"35\":1,\"39\":1}', '2026-04-16 16:39:54');
INSERT INTO `progression_candidat` (`id`, `idcandidat`, `id_session`, `partie_encours`, `current_index_theo`, `current_index_pra`, `infractions`, `ordre_questions_theo`, `ordre_questions_pra`, `reponses_json`, `updated_at`) VALUES
(3, 15, 5, 'theorique', 4, 0, 0, '[{\"id\":45,\"idtype_examen\":4,\"type_question\":\"theorique\",\"question_text_fr\":\"Que faire si vous observez une personne non autoris\\u00e9e tentant d\'acc\\u00e9der \\u00e0 une zone r\\u00e9glement\\u00e9e ?\",\"question_text_en\":\"What to do if you observe an unauthorised person attempting to access a restricted area?\",\"images\":null,\"images_data\":null,\"option1_fr\":\"Alerter imm\\u00e9diatement le service de s\\u00fbret\\u00e9 sans intervenir physiquement vous-m\\u00eame\",\"option1_en\":\"Immediately alert the security service without physically intervening yourself\",\"option2_fr\":\"L\'interpeller et l\'immobiliser physiquement vous-m\\u00eame\",\"option2_en\":\"Physically confront and restrain them yourself\",\"option3_fr\":\"Ignorer si la personne semble inoffensive et tranquille\",\"option3_en\":\"Ignore if the person seems harmless and calm\",\"option4_fr\":\"Attendre qu\'un coll\\u00e8gue arrive avant d\'agir\",\"option4_en\":\"Wait for a colleague to arrive before acting\",\"correct_option\":1,\"bareme\":\"5.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":44,\"idtype_examen\":4,\"type_question\":\"theorique\",\"question_text_fr\":\"Un badge d\'acc\\u00e8s a\\u00e9roportuaire est :\",\"question_text_en\":\"An airport access badge is:\",\"images\":null,\"images_data\":null,\"option1_fr\":\"Strictement personnel, non transf\\u00e9rable et doit \\u00eatre visible en permanence sur la tenue\",\"option1_en\":\"Strictly personal, non-transferable and must be visibly worn at all times\",\"option2_fr\":\"Partageable entre coll\\u00e8gues du m\\u00eame service en cas d\'urgence\",\"option2_en\":\"Shareable between colleagues of the same department in an emergency\",\"option3_fr\":\"Valable sur tous les a\\u00e9roports du monde sans restriction\",\"option3_en\":\"Valid at all airports worldwide without restriction\",\"option4_fr\":\"Optionnel si vous \\u00eates connu du personnel de s\\u00e9curit\\u00e9\",\"option4_en\":\"Optional if you are known to the security staff\",\"correct_option\":1,\"bareme\":\"5.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":43,\"idtype_examen\":4,\"type_question\":\"theorique\",\"question_text_fr\":\"Face \\u00e0 un bagage abandonn\\u00e9 dans la zone publique de l\'a\\u00e9roport, la premi\\u00e8re action est :\",\"question_text_en\":\"When facing an abandoned bag in the public area of the airport, the first action is:\",\"images\":null,\"images_data\":null,\"option1_fr\":\"Ne pas toucher le bagage et alerter imm\\u00e9diatement les agents de s\\u00fbret\\u00e9\",\"option1_en\":\"Do not touch the bag and immediately alert security agents\",\"option2_fr\":\"Ouvrir le bagage pour v\\u00e9rifier son contenu soi-m\\u00eame\",\"option2_en\":\"Open the bag to check its contents yourself\",\"option3_fr\":\"Le d\\u00e9placer dans un endroit plus discret pour \\u00e9viter la panique\",\"option3_en\":\"Move it to a more discreet location to avoid panic\",\"option4_fr\":\"Attendre 30 minutes avant d\'agir pour ne pas surr\\u00e9agir\",\"option4_en\":\"Wait 30 minutes before acting to avoid overreacting\",\"correct_option\":1,\"bareme\":\"5.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":41,\"idtype_examen\":4,\"type_question\":\"theorique\",\"question_text_fr\":\"Qu\'est-ce que la sensibilisation \\u00e0 la s\\u00fbret\\u00e9 a\\u00e9roportuaire ?\",\"question_text_en\":\"What is airport security awareness?\",\"images\":null,\"images_data\":null,\"option1_fr\":\"Informer et responsabiliser tout le personnel sur les menaces et risques li\\u00e9s \\u00e0 la s\\u00fbret\\u00e9\",\"option1_en\":\"Inform and empower all staff about security threats and risks\",\"option2_fr\":\"Apprendre \\u00e0 combattre physiquement des terroristes\",\"option2_en\":\"Learning to physically fight terrorists\",\"option3_fr\":\"Surveiller uniquement le comportement des passagers\",\"option3_en\":\"Monitoring passenger behaviour only\",\"option4_fr\":\"Former des agents de s\\u00e9curit\\u00e9 arm\\u00e9s pour les couloirs\",\"option4_en\":\"Training armed security agents for corridors\",\"correct_option\":1,\"bareme\":\"5.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":42,\"idtype_examen\":4,\"type_question\":\"theorique\",\"question_text_fr\":\"\\u00c0 qui s\'adresse principalement la sensibilisation \\u00e0 la s\\u00fbret\\u00e9 a\\u00e9roportuaire ?\",\"question_text_en\":\"Who is airport security awareness primarily aimed at?\",\"images\":null,\"images_data\":null,\"option1_fr\":\"\\u00c0 tout le personnel ayant acc\\u00e8s \\u00e0 l\'a\\u00e9roport (agents, techniciens, commer\\u00e7ants, prestataires)\",\"option1_en\":\"To all personnel with airport access (agents, technicians, traders, contractors)\",\"option2_fr\":\"Uniquement aux agents de s\\u00fbret\\u00e9 certifi\\u00e9s\",\"option2_en\":\"Only to certified security agents\",\"option3_fr\":\"Uniquement aux pilotes et au personnel navigant de cabine\",\"option3_en\":\"Only to pilots and cabin crew\",\"option4_fr\":\"Uniquement aux passagers des vols internationaux\",\"option4_en\":\"Only to passengers on international flights\",\"correct_option\":1,\"bareme\":\"5.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null}]', NULL, '{\"45\":1,\"41\":1,\"44\":2,\"42\":1,\"43\":4}', '2026-04-16 16:51:17'),
(4, 17, 5, 'theorique', 4, 0, 0, '[{\"id\":42,\"idtype_examen\":4,\"type_question\":\"theorique\",\"question_text_fr\":\"\\u00c0 qui s\'adresse principalement la sensibilisation \\u00e0 la s\\u00fbret\\u00e9 a\\u00e9roportuaire ?\",\"question_text_en\":\"Who is airport security awareness primarily aimed at?\",\"images\":null,\"images_data\":null,\"option1_fr\":\"\\u00c0 tout le personnel ayant acc\\u00e8s \\u00e0 l\'a\\u00e9roport (agents, techniciens, commer\\u00e7ants, prestataires)\",\"option1_en\":\"To all personnel with airport access (agents, technicians, traders, contractors)\",\"option2_fr\":\"Uniquement aux agents de s\\u00fbret\\u00e9 certifi\\u00e9s\",\"option2_en\":\"Only to certified security agents\",\"option3_fr\":\"Uniquement aux pilotes et au personnel navigant de cabine\",\"option3_en\":\"Only to pilots and cabin crew\",\"option4_fr\":\"Uniquement aux passagers des vols internationaux\",\"option4_en\":\"Only to passengers on international flights\",\"correct_option\":1,\"bareme\":\"5.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":43,\"idtype_examen\":4,\"type_question\":\"theorique\",\"question_text_fr\":\"Face \\u00e0 un bagage abandonn\\u00e9 dans la zone publique de l\'a\\u00e9roport, la premi\\u00e8re action est :\",\"question_text_en\":\"When facing an abandoned bag in the public area of the airport, the first action is:\",\"images\":null,\"images_data\":null,\"option1_fr\":\"Ne pas toucher le bagage et alerter imm\\u00e9diatement les agents de s\\u00fbret\\u00e9\",\"option1_en\":\"Do not touch the bag and immediately alert security agents\",\"option2_fr\":\"Ouvrir le bagage pour v\\u00e9rifier son contenu soi-m\\u00eame\",\"option2_en\":\"Open the bag to check its contents yourself\",\"option3_fr\":\"Le d\\u00e9placer dans un endroit plus discret pour \\u00e9viter la panique\",\"option3_en\":\"Move it to a more discreet location to avoid panic\",\"option4_fr\":\"Attendre 30 minutes avant d\'agir pour ne pas surr\\u00e9agir\",\"option4_en\":\"Wait 30 minutes before acting to avoid overreacting\",\"correct_option\":1,\"bareme\":\"5.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":41,\"idtype_examen\":4,\"type_question\":\"theorique\",\"question_text_fr\":\"Qu\'est-ce que la sensibilisation \\u00e0 la s\\u00fbret\\u00e9 a\\u00e9roportuaire ?\",\"question_text_en\":\"What is airport security awareness?\",\"images\":null,\"images_data\":null,\"option1_fr\":\"Informer et responsabiliser tout le personnel sur les menaces et risques li\\u00e9s \\u00e0 la s\\u00fbret\\u00e9\",\"option1_en\":\"Inform and empower all staff about security threats and risks\",\"option2_fr\":\"Apprendre \\u00e0 combattre physiquement des terroristes\",\"option2_en\":\"Learning to physically fight terrorists\",\"option3_fr\":\"Surveiller uniquement le comportement des passagers\",\"option3_en\":\"Monitoring passenger behaviour only\",\"option4_fr\":\"Former des agents de s\\u00e9curit\\u00e9 arm\\u00e9s pour les couloirs\",\"option4_en\":\"Training armed security agents for corridors\",\"correct_option\":1,\"bareme\":\"5.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":45,\"idtype_examen\":4,\"type_question\":\"theorique\",\"question_text_fr\":\"Que faire si vous observez une personne non autoris\\u00e9e tentant d\'acc\\u00e9der \\u00e0 une zone r\\u00e9glement\\u00e9e ?\",\"question_text_en\":\"What to do if you observe an unauthorised person attempting to access a restricted area?\",\"images\":null,\"images_data\":null,\"option1_fr\":\"Alerter imm\\u00e9diatement le service de s\\u00fbret\\u00e9 sans intervenir physiquement vous-m\\u00eame\",\"option1_en\":\"Immediately alert the security service without physically intervening yourself\",\"option2_fr\":\"L\'interpeller et l\'immobiliser physiquement vous-m\\u00eame\",\"option2_en\":\"Physically confront and restrain them yourself\",\"option3_fr\":\"Ignorer si la personne semble inoffensive et tranquille\",\"option3_en\":\"Ignore if the person seems harmless and calm\",\"option4_fr\":\"Attendre qu\'un coll\\u00e8gue arrive avant d\'agir\",\"option4_en\":\"Wait for a colleague to arrive before acting\",\"correct_option\":1,\"bareme\":\"5.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":44,\"idtype_examen\":4,\"type_question\":\"theorique\",\"question_text_fr\":\"Un badge d\'acc\\u00e8s a\\u00e9roportuaire est :\",\"question_text_en\":\"An airport access badge is:\",\"images\":null,\"images_data\":null,\"option1_fr\":\"Strictement personnel, non transf\\u00e9rable et doit \\u00eatre visible en permanence sur la tenue\",\"option1_en\":\"Strictly personal, non-transferable and must be visibly worn at all times\",\"option2_fr\":\"Partageable entre coll\\u00e8gues du m\\u00eame service en cas d\'urgence\",\"option2_en\":\"Shareable between colleagues of the same department in an emergency\",\"option3_fr\":\"Valable sur tous les a\\u00e9roports du monde sans restriction\",\"option3_en\":\"Valid at all airports worldwide without restriction\",\"option4_fr\":\"Optionnel si vous \\u00eates connu du personnel de s\\u00e9curit\\u00e9\",\"option4_en\":\"Optional if you are known to the security staff\",\"correct_option\":1,\"bareme\":\"5.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null}]', NULL, '{\"42\":1,\"43\":1,\"41\":3,\"45\":3,\"44\":4}', '2026-04-16 16:51:24'),
(5, 20, 8, 'theorique', 7, 0, 0, '[{\"id\":52,\"idtype_examen\":5,\"type_question\":\"theorique\",\"question_text_fr\":\"[Module 3] Une zone c\\u00f4t\\u00e9 piste (airside) est d\\u00e9finie comme :\",\"question_text_en\":\"[Module 3] An airside zone is defined as:\",\"images\":null,\"images_data\":null,\"option1_fr\":\"Toute zone de mouvement des a\\u00e9ronefs, de chargement et zones adjacentes dont l\'acc\\u00e8s est strictement contr\\u00f4l\\u00e9\",\"option1_en\":\"Any aircraft movement area, loading area and adjacent zones with strictly controlled access\",\"option2_fr\":\"La piste d\'atterrissage et les voies de circulation uniquement\",\"option2_en\":\"The landing runway and taxiways only\",\"option3_fr\":\"La zone commerciale et les boutiques de l\'a\\u00e9roport\",\"option3_en\":\"The commercial zone and airport shops\",\"option4_fr\":\"Les bureaux administratifs de la direction de l\'a\\u00e9roport\",\"option4_en\":\"The administrative offices of the airport management\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":48,\"idtype_examen\":5,\"type_question\":\"theorique\",\"question_text_fr\":\"[Module 2] L\'ANAC Gabon est principalement responsable de :\",\"question_text_en\":\"[Module 2] ANAC Gabon is primarily responsible for:\",\"images\":null,\"images_data\":null,\"option1_fr\":\"La r\\u00e9glementation, la supervision et la certification de l\'aviation civile nationale\",\"option1_en\":\"Regulation, supervision and certification of national civil aviation\",\"option2_fr\":\"La gestion commerciale des compagnies a\\u00e9riennes au Gabon\",\"option2_en\":\"Commercial management of airlines in Gabon\",\"option3_fr\":\"La conception et la construction des a\\u00e9roports nationaux\",\"option3_en\":\"Design and construction of national airports\",\"option4_fr\":\"La d\\u00e9livrance des visas d\'entr\\u00e9e sur le territoire gabonais\",\"option4_en\":\"Issuing entry visas to Gabonese territory\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":49,\"idtype_examen\":5,\"type_question\":\"theorique\",\"question_text_fr\":\"[Module 2] La sanction en cas de violation grave des r\\u00e8gles de s\\u00fbret\\u00e9 a\\u00e9roportuaire peut aller jusqu\'\\u00e0 :\",\"question_text_en\":\"[Module 2] The penalty for serious violation of airport security rules can go up to:\",\"images\":null,\"images_data\":null,\"option1_fr\":\"Des amendes lourdes et\\/ou l\'emprisonnement selon la l\\u00e9gislation nationale gabonaise\",\"option1_en\":\"Heavy fines and\\/or imprisonment under Gabonese national legislation\",\"option2_fr\":\"Un simple avertissement oral de la hi\\u00e9rarchie\",\"option2_en\":\"A simple verbal warning from management\",\"option3_fr\":\"Une suspension temporaire de badge a\\u00e9roportuaire uniquement\",\"option3_en\":\"A temporary airport badge suspension only\",\"option4_fr\":\"Aucune sanction l\\u00e9gale sp\\u00e9cifique n\'est pr\\u00e9vue\",\"option4_en\":\"No specific legal sanction is provided\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":50,\"idtype_examen\":5,\"type_question\":\"theorique\",\"question_text_fr\":\"[Module 3] Quelles sont les principales mesures de s\\u00fbret\\u00e9 \\u00e0 appliquer dans un a\\u00e9roport ?\",\"question_text_en\":\"[Module 3] What are the main security measures to be applied in an airport?\",\"images\":null,\"images_data\":null,\"option1_fr\":\"Contr\\u00f4le d\'acc\\u00e8s, inspection filtrage, surveillance p\\u00e9rim\\u00e9trique et fouille des bagages\",\"option1_en\":\"Access control, security screening, perimeter surveillance and baggage search\",\"option2_fr\":\"Vente de billets, gestion des files et information voyageurs\",\"option2_en\":\"Ticket sales, queue management and passenger information\",\"option3_fr\":\"Nettoyage des pistes, maintenance des terminaux\",\"option3_en\":\"Runway cleaning, terminal maintenance\",\"option4_fr\":\"Gestion des parkings, restauration et boutiques hors-taxe\",\"option4_en\":\"Parking management, catering and duty-free shops\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":47,\"idtype_examen\":5,\"type_question\":\"theorique\",\"question_text_fr\":\"[Module 2] Qu\'est-ce que le PNSAC et quel est son r\\u00f4le dans l\'aviation civile gabonaise ?\",\"question_text_en\":\"[Module 2] What is PNSAC and what is its role in Gabonese civil aviation?\",\"images\":null,\"images_data\":null,\"option1_fr\":\"Programme National de S\\u00fbret\\u00e9 de l\'Aviation Civile du Gabon \\u2014 document-cadre de la s\\u00fbret\\u00e9 nationale\",\"option1_en\":\"National Civil Aviation Security Programme of Gabon \\u2014 national security framework document\",\"option2_fr\":\"Plan de Normalisation des Services A\\u00e9roportuaires Civils\",\"option2_en\":\"Civil Airport Services Standardisation Plan\",\"option3_fr\":\"Protocole National de Surveillance A\\u00e9ronautique Civile\",\"option3_en\":\"National Civil Aeronautical Surveillance Protocol\",\"option4_fr\":\"Programme de Navigation et S\\u00e9curit\\u00e9 A\\u00e9rienne Contr\\u00f4l\\u00e9e\",\"option4_en\":\"Controlled Air Navigation and Safety Programme\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":46,\"idtype_examen\":5,\"type_question\":\"theorique\",\"question_text_fr\":\"[Module 2] Quel est le fondement juridique international de la s\\u00fbret\\u00e9 de l\'aviation civile ?\",\"question_text_en\":\"[Module 2] What is the international legal basis for civil aviation security?\",\"images\":null,\"images_data\":null,\"option1_fr\":\"La Convention de Chicago (1944) et l\'Annexe 17 de l\'OACI\",\"option1_en\":\"The Chicago Convention (1944) and ICAO Annex 17\",\"option2_fr\":\"Le Trait\\u00e9 de Rome (1957)\",\"option2_en\":\"The Treaty of Rome (1957)\",\"option3_fr\":\"La Charte des Nations Unies (1945)\",\"option3_en\":\"The United Nations Charter (1945)\",\"option4_fr\":\"La Convention de Gen\\u00e8ve (1949)\",\"option4_en\":\"The Geneva Convention (1949)\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":55,\"idtype_examen\":5,\"type_question\":\"theorique\",\"question_text_fr\":\"question sur la forme vfvfvf\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"vv\",\"option1_en\":\"\",\"option2_fr\":\"ddc\",\"option2_en\":\"\",\"option3_fr\":\"ddcd\",\"option3_en\":null,\"option4_fr\":\"dd\",\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-26 12:04:46\",\"images_traitements\":null},{\"id\":51,\"idtype_examen\":5,\"type_question\":\"theorique\",\"question_text_fr\":\"[Module 3] Le contr\\u00f4le d\'acc\\u00e8s en zone r\\u00e9glement\\u00e9e a\\u00e9roportuaire repose sur :\",\"question_text_en\":\"[Module 3] Access control in an airport restricted area is based on:\",\"images\":null,\"images_data\":null,\"option1_fr\":\"L\'identification formelle, l\'habilitation v\\u00e9rifi\\u00e9e et le contr\\u00f4le physique syst\\u00e9matique\",\"option1_en\":\"Formal identification, verified clearance and systematic physical control\",\"option2_fr\":\"La confiance mutuelle entre les agents et coll\\u00e8gues connus\",\"option2_en\":\"Mutual trust between agents and known colleagues\",\"option3_fr\":\"La simple pr\\u00e9sentation d\'une carte professionnelle de l\'entreprise\",\"option3_en\":\"Simple presentation of a company professional card\",\"option4_fr\":\"La reconnaissance visuelle du personnel par les agents en poste\",\"option4_en\":\"Visual recognition of staff by agents on duty\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null}]', NULL, '{\"47\":2,\"52\":2,\"50\":1,\"49\":1,\"46\":1,\"55\":1,\"51\":2,\"48\":2}', '2026-04-16 17:42:54'),
(6, 3, 8, 'theorique', 7, 0, 3, '[{\"id\":49,\"idtype_examen\":5,\"type_question\":\"theorique\",\"question_text_fr\":\"[Module 2] La sanction en cas de violation grave des r\\u00e8gles de s\\u00fbret\\u00e9 a\\u00e9roportuaire peut aller jusqu\'\\u00e0 :\",\"question_text_en\":\"[Module 2] The penalty for serious violation of airport security rules can go up to:\",\"images\":null,\"images_data\":null,\"option1_fr\":\"Des amendes lourdes et\\/ou l\'emprisonnement selon la l\\u00e9gislation nationale gabonaise\",\"option1_en\":\"Heavy fines and\\/or imprisonment under Gabonese national legislation\",\"option2_fr\":\"Un simple avertissement oral de la hi\\u00e9rarchie\",\"option2_en\":\"A simple verbal warning from management\",\"option3_fr\":\"Une suspension temporaire de badge a\\u00e9roportuaire uniquement\",\"option3_en\":\"A temporary airport badge suspension only\",\"option4_fr\":\"Aucune sanction l\\u00e9gale sp\\u00e9cifique n\'est pr\\u00e9vue\",\"option4_en\":\"No specific legal sanction is provided\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":52,\"idtype_examen\":5,\"type_question\":\"theorique\",\"question_text_fr\":\"[Module 3] Une zone c\\u00f4t\\u00e9 piste (airside) est d\\u00e9finie comme :\",\"question_text_en\":\"[Module 3] An airside zone is defined as:\",\"images\":null,\"images_data\":null,\"option1_fr\":\"Toute zone de mouvement des a\\u00e9ronefs, de chargement et zones adjacentes dont l\'acc\\u00e8s est strictement contr\\u00f4l\\u00e9\",\"option1_en\":\"Any aircraft movement area, loading area and adjacent zones with strictly controlled access\",\"option2_fr\":\"La piste d\'atterrissage et les voies de circulation uniquement\",\"option2_en\":\"The landing runway and taxiways only\",\"option3_fr\":\"La zone commerciale et les boutiques de l\'a\\u00e9roport\",\"option3_en\":\"The commercial zone and airport shops\",\"option4_fr\":\"Les bureaux administratifs de la direction de l\'a\\u00e9roport\",\"option4_en\":\"The administrative offices of the airport management\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":50,\"idtype_examen\":5,\"type_question\":\"theorique\",\"question_text_fr\":\"[Module 3] Quelles sont les principales mesures de s\\u00fbret\\u00e9 \\u00e0 appliquer dans un a\\u00e9roport ?\",\"question_text_en\":\"[Module 3] What are the main security measures to be applied in an airport?\",\"images\":null,\"images_data\":null,\"option1_fr\":\"Contr\\u00f4le d\'acc\\u00e8s, inspection filtrage, surveillance p\\u00e9rim\\u00e9trique et fouille des bagages\",\"option1_en\":\"Access control, security screening, perimeter surveillance and baggage search\",\"option2_fr\":\"Vente de billets, gestion des files et information voyageurs\",\"option2_en\":\"Ticket sales, queue management and passenger information\",\"option3_fr\":\"Nettoyage des pistes, maintenance des terminaux\",\"option3_en\":\"Runway cleaning, terminal maintenance\",\"option4_fr\":\"Gestion des parkings, restauration et boutiques hors-taxe\",\"option4_en\":\"Parking management, catering and duty-free shops\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":51,\"idtype_examen\":5,\"type_question\":\"theorique\",\"question_text_fr\":\"[Module 3] Le contr\\u00f4le d\'acc\\u00e8s en zone r\\u00e9glement\\u00e9e a\\u00e9roportuaire repose sur :\",\"question_text_en\":\"[Module 3] Access control in an airport restricted area is based on:\",\"images\":null,\"images_data\":null,\"option1_fr\":\"L\'identification formelle, l\'habilitation v\\u00e9rifi\\u00e9e et le contr\\u00f4le physique syst\\u00e9matique\",\"option1_en\":\"Formal identification, verified clearance and systematic physical control\",\"option2_fr\":\"La confiance mutuelle entre les agents et coll\\u00e8gues connus\",\"option2_en\":\"Mutual trust between agents and known colleagues\",\"option3_fr\":\"La simple pr\\u00e9sentation d\'une carte professionnelle de l\'entreprise\",\"option3_en\":\"Simple presentation of a company professional card\",\"option4_fr\":\"La reconnaissance visuelle du personnel par les agents en poste\",\"option4_en\":\"Visual recognition of staff by agents on duty\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":47,\"idtype_examen\":5,\"type_question\":\"theorique\",\"question_text_fr\":\"[Module 2] Qu\'est-ce que le PNSAC et quel est son r\\u00f4le dans l\'aviation civile gabonaise ?\",\"question_text_en\":\"[Module 2] What is PNSAC and what is its role in Gabonese civil aviation?\",\"images\":null,\"images_data\":null,\"option1_fr\":\"Programme National de S\\u00fbret\\u00e9 de l\'Aviation Civile du Gabon \\u2014 document-cadre de la s\\u00fbret\\u00e9 nationale\",\"option1_en\":\"National Civil Aviation Security Programme of Gabon \\u2014 national security framework document\",\"option2_fr\":\"Plan de Normalisation des Services A\\u00e9roportuaires Civils\",\"option2_en\":\"Civil Airport Services Standardisation Plan\",\"option3_fr\":\"Protocole National de Surveillance A\\u00e9ronautique Civile\",\"option3_en\":\"National Civil Aeronautical Surveillance Protocol\",\"option4_fr\":\"Programme de Navigation et S\\u00e9curit\\u00e9 A\\u00e9rienne Contr\\u00f4l\\u00e9e\",\"option4_en\":\"Controlled Air Navigation and Safety Programme\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":48,\"idtype_examen\":5,\"type_question\":\"theorique\",\"question_text_fr\":\"[Module 2] L\'ANAC Gabon est principalement responsable de :\",\"question_text_en\":\"[Module 2] ANAC Gabon is primarily responsible for:\",\"images\":null,\"images_data\":null,\"option1_fr\":\"La r\\u00e9glementation, la supervision et la certification de l\'aviation civile nationale\",\"option1_en\":\"Regulation, supervision and certification of national civil aviation\",\"option2_fr\":\"La gestion commerciale des compagnies a\\u00e9riennes au Gabon\",\"option2_en\":\"Commercial management of airlines in Gabon\",\"option3_fr\":\"La conception et la construction des a\\u00e9roports nationaux\",\"option3_en\":\"Design and construction of national airports\",\"option4_fr\":\"La d\\u00e9livrance des visas d\'entr\\u00e9e sur le territoire gabonais\",\"option4_en\":\"Issuing entry visas to Gabonese territory\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":46,\"idtype_examen\":5,\"type_question\":\"theorique\",\"question_text_fr\":\"[Module 2] Quel est le fondement juridique international de la s\\u00fbret\\u00e9 de l\'aviation civile ?\",\"question_text_en\":\"[Module 2] What is the international legal basis for civil aviation security?\",\"images\":null,\"images_data\":null,\"option1_fr\":\"La Convention de Chicago (1944) et l\'Annexe 17 de l\'OACI\",\"option1_en\":\"The Chicago Convention (1944) and ICAO Annex 17\",\"option2_fr\":\"Le Trait\\u00e9 de Rome (1957)\",\"option2_en\":\"The Treaty of Rome (1957)\",\"option3_fr\":\"La Charte des Nations Unies (1945)\",\"option3_en\":\"The United Nations Charter (1945)\",\"option4_fr\":\"La Convention de Gen\\u00e8ve (1949)\",\"option4_en\":\"The Geneva Convention (1949)\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":55,\"idtype_examen\":5,\"type_question\":\"theorique\",\"question_text_fr\":\"question sur la forme vfvfvf\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"vv\",\"option1_en\":\"\",\"option2_fr\":\"ddc\",\"option2_en\":\"\",\"option3_fr\":\"ddcd\",\"option3_en\":null,\"option4_fr\":\"dd\",\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-26 12:04:46\",\"images_traitements\":null}]', NULL, '{\"52\":1,\"47\":1,\"49\":2,\"55\":2,\"48\":2,\"51\":3,\"50\":2,\"46\":2}', '2026-04-16 17:35:30'),
(7, 3, 9, 'theorique', 7, 0, 0, '[{\"id\":47,\"idtype_examen\":5,\"type_question\":\"theorique\",\"question_text_fr\":\"[Module 2] Qu\'est-ce que le PNSAC et quel est son r\\u00f4le dans l\'aviation civile gabonaise ?\",\"question_text_en\":\"[Module 2] What is PNSAC and what is its role in Gabonese civil aviation?\",\"images\":null,\"images_data\":null,\"option1_fr\":\"Programme National de S\\u00fbret\\u00e9 de l\'Aviation Civile du Gabon \\u2014 document-cadre de la s\\u00fbret\\u00e9 nationale\",\"option1_en\":\"National Civil Aviation Security Programme of Gabon \\u2014 national security framework document\",\"option2_fr\":\"Plan de Normalisation des Services A\\u00e9roportuaires Civils\",\"option2_en\":\"Civil Airport Services Standardisation Plan\",\"option3_fr\":\"Protocole National de Surveillance A\\u00e9ronautique Civile\",\"option3_en\":\"National Civil Aeronautical Surveillance Protocol\",\"option4_fr\":\"Programme de Navigation et S\\u00e9curit\\u00e9 A\\u00e9rienne Contr\\u00f4l\\u00e9e\",\"option4_en\":\"Controlled Air Navigation and Safety Programme\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":46,\"idtype_examen\":5,\"type_question\":\"theorique\",\"question_text_fr\":\"[Module 2] Quel est le fondement juridique international de la s\\u00fbret\\u00e9 de l\'aviation civile ?\",\"question_text_en\":\"[Module 2] What is the international legal basis for civil aviation security?\",\"images\":null,\"images_data\":null,\"option1_fr\":\"La Convention de Chicago (1944) et l\'Annexe 17 de l\'OACI\",\"option1_en\":\"The Chicago Convention (1944) and ICAO Annex 17\",\"option2_fr\":\"Le Trait\\u00e9 de Rome (1957)\",\"option2_en\":\"The Treaty of Rome (1957)\",\"option3_fr\":\"La Charte des Nations Unies (1945)\",\"option3_en\":\"The United Nations Charter (1945)\",\"option4_fr\":\"La Convention de Gen\\u00e8ve (1949)\",\"option4_en\":\"The Geneva Convention (1949)\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":51,\"idtype_examen\":5,\"type_question\":\"theorique\",\"question_text_fr\":\"[Module 3] Le contr\\u00f4le d\'acc\\u00e8s en zone r\\u00e9glement\\u00e9e a\\u00e9roportuaire repose sur :\",\"question_text_en\":\"[Module 3] Access control in an airport restricted area is based on:\",\"images\":null,\"images_data\":null,\"option1_fr\":\"L\'identification formelle, l\'habilitation v\\u00e9rifi\\u00e9e et le contr\\u00f4le physique syst\\u00e9matique\",\"option1_en\":\"Formal identification, verified clearance and systematic physical control\",\"option2_fr\":\"La confiance mutuelle entre les agents et coll\\u00e8gues connus\",\"option2_en\":\"Mutual trust between agents and known colleagues\",\"option3_fr\":\"La simple pr\\u00e9sentation d\'une carte professionnelle de l\'entreprise\",\"option3_en\":\"Simple presentation of a company professional card\",\"option4_fr\":\"La reconnaissance visuelle du personnel par les agents en poste\",\"option4_en\":\"Visual recognition of staff by agents on duty\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":55,\"idtype_examen\":5,\"type_question\":\"theorique\",\"question_text_fr\":\"question sur la forme vfvfvf\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"vv\",\"option1_en\":\"\",\"option2_fr\":\"ddc\",\"option2_en\":\"\",\"option3_fr\":\"ddcd\",\"option3_en\":null,\"option4_fr\":\"dd\",\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-26 12:04:46\",\"images_traitements\":null},{\"id\":50,\"idtype_examen\":5,\"type_question\":\"theorique\",\"question_text_fr\":\"[Module 3] Quelles sont les principales mesures de s\\u00fbret\\u00e9 \\u00e0 appliquer dans un a\\u00e9roport ?\",\"question_text_en\":\"[Module 3] What are the main security measures to be applied in an airport?\",\"images\":null,\"images_data\":null,\"option1_fr\":\"Contr\\u00f4le d\'acc\\u00e8s, inspection filtrage, surveillance p\\u00e9rim\\u00e9trique et fouille des bagages\",\"option1_en\":\"Access control, security screening, perimeter surveillance and baggage search\",\"option2_fr\":\"Vente de billets, gestion des files et information voyageurs\",\"option2_en\":\"Ticket sales, queue management and passenger information\",\"option3_fr\":\"Nettoyage des pistes, maintenance des terminaux\",\"option3_en\":\"Runway cleaning, terminal maintenance\",\"option4_fr\":\"Gestion des parkings, restauration et boutiques hors-taxe\",\"option4_en\":\"Parking management, catering and duty-free shops\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":52,\"idtype_examen\":5,\"type_question\":\"theorique\",\"question_text_fr\":\"[Module 3] Une zone c\\u00f4t\\u00e9 piste (airside) est d\\u00e9finie comme :\",\"question_text_en\":\"[Module 3] An airside zone is defined as:\",\"images\":null,\"images_data\":null,\"option1_fr\":\"Toute zone de mouvement des a\\u00e9ronefs, de chargement et zones adjacentes dont l\'acc\\u00e8s est strictement contr\\u00f4l\\u00e9\",\"option1_en\":\"Any aircraft movement area, loading area and adjacent zones with strictly controlled access\",\"option2_fr\":\"La piste d\'atterrissage et les voies de circulation uniquement\",\"option2_en\":\"The landing runway and taxiways only\",\"option3_fr\":\"La zone commerciale et les boutiques de l\'a\\u00e9roport\",\"option3_en\":\"The commercial zone and airport shops\",\"option4_fr\":\"Les bureaux administratifs de la direction de l\'a\\u00e9roport\",\"option4_en\":\"The administrative offices of the airport management\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":48,\"idtype_examen\":5,\"type_question\":\"theorique\",\"question_text_fr\":\"[Module 2] L\'ANAC Gabon est principalement responsable de :\",\"question_text_en\":\"[Module 2] ANAC Gabon is primarily responsible for:\",\"images\":null,\"images_data\":null,\"option1_fr\":\"La r\\u00e9glementation, la supervision et la certification de l\'aviation civile nationale\",\"option1_en\":\"Regulation, supervision and certification of national civil aviation\",\"option2_fr\":\"La gestion commerciale des compagnies a\\u00e9riennes au Gabon\",\"option2_en\":\"Commercial management of airlines in Gabon\",\"option3_fr\":\"La conception et la construction des a\\u00e9roports nationaux\",\"option3_en\":\"Design and construction of national airports\",\"option4_fr\":\"La d\\u00e9livrance des visas d\'entr\\u00e9e sur le territoire gabonais\",\"option4_en\":\"Issuing entry visas to Gabonese territory\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null},{\"id\":49,\"idtype_examen\":5,\"type_question\":\"theorique\",\"question_text_fr\":\"[Module 2] La sanction en cas de violation grave des r\\u00e8gles de s\\u00fbret\\u00e9 a\\u00e9roportuaire peut aller jusqu\'\\u00e0 :\",\"question_text_en\":\"[Module 2] The penalty for serious violation of airport security rules can go up to:\",\"images\":null,\"images_data\":null,\"option1_fr\":\"Des amendes lourdes et\\/ou l\'emprisonnement selon la l\\u00e9gislation nationale gabonaise\",\"option1_en\":\"Heavy fines and\\/or imprisonment under Gabonese national legislation\",\"option2_fr\":\"Un simple avertissement oral de la hi\\u00e9rarchie\",\"option2_en\":\"A simple verbal warning from management\",\"option3_fr\":\"Une suspension temporaire de badge a\\u00e9roportuaire uniquement\",\"option3_en\":\"A temporary airport badge suspension only\",\"option4_fr\":\"Aucune sanction l\\u00e9gale sp\\u00e9cifique n\'est pr\\u00e9vue\",\"option4_en\":\"No specific legal sanction is provided\",\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-03-24 21:51:58\",\"images_traitements\":null}]', NULL, '{\"51\":1,\"50\":2,\"49\":2,\"46\":3,\"47\":1,\"55\":2,\"48\":1,\"52\":2}', '2026-04-16 17:37:16');
INSERT INTO `progression_candidat` (`id`, `idcandidat`, `id_session`, `partie_encours`, `current_index_theo`, `current_index_pra`, `infractions`, `ordre_questions_theo`, `ordre_questions_pra`, `reponses_json`, `updated_at`) VALUES
(8, 8, 2, 'theorique', 49, 0, 1, '[{\"id\":233,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Le seuil de r\\u00e9ussite (moyenne) \\u00e0 l\'examen de certification est de 80%\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":252,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Un prisonnier sous escorte de gardes arm\\u00e9s ne doit pas \\u00eatre soumis aux contr\\u00f4les de suret\\u00e9 avant de voyager \\u00e0 bord des a\\u00e9ronefs parce que : (Choisir la bonne r\\u00e9ponse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) L\'autorit\\u00e9 judiciaire est responsable de son transport ;\",\"option1_en\":\"\",\"option2_fr\":\"b) Il a subi tous les contr\\u00f4les avant d\'arriver \\u00e0 l\'a\\u00e9roport ;\",\"option2_en\":\"\",\"option3_fr\":\"c) Le gouvernement et l\'exploitant d\'a\\u00e9ronef ont convenu des dispositions pr\\u00e9alables ;\",\"option3_en\":null,\"option4_fr\":\"d) Aucune des r\\u00e9ponses ci-dessus\",\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":204,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"La rotation du personnel pr\\u00e9pos\\u00e9 au RX est de 25mn\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":261,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Parmi les articles ci-apr\\u00e8s lequel n\'est pas autoris\\u00e9 en soute. Choisir la bonne r\\u00e9ponse\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) Pistolets \\u00e0 fus\\u00e9es de signalisation\",\"option1_en\":\"\",\"option2_fr\":\"b) Pistolets d\'enfants de tous types\",\"option2_en\":\"\",\"option3_fr\":\"c) Pistolets de d\\u00e9part\",\"option3_en\":null,\"option4_fr\":\"d) Explosifs\",\"option4_en\":null,\"correct_option\":4,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":236,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Les cartes d\'embarquement constituent des titres d\'acc\\u00e8s pour les passagers\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":205,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Parmi les \\u00e9quipements au PIF de filtrage des bagages de soute se trouve un ETD\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":250,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Quel est le sort r\\u00e9serv\\u00e9 aux articles confisqu\\u00e9s au PIF. Choisir la bonne r\\u00e9ponse\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) d\\u00e9truits par le chef de poste\",\"option1_en\":\"\",\"option2_fr\":\"b) transmis \\u00e0 la compagnie a\\u00e9rienne\",\"option2_en\":\"\",\"option3_fr\":\"c) transmis au Chef d\'\\u00e9quipe\",\"option3_en\":null,\"option4_fr\":\"d) prendre pour l\'usage du personnel du PIF\",\"option4_en\":null,\"correct_option\":3,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":215,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Le Directeur G\\u00e9n\\u00e9ral de l\'ANAC peut solliciter l\'acc\\u00e8s en ZSAR des membres de sa famille non d\\u00e9tenteurs de TCA\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":196,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Parmi les personnes exempt\\u00e9es de l\'inspection filtrage conform\\u00e9ment \\u00e0 la PEN se trouve les ministres des affaires \\u00e9trang\\u00e8res\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":208,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"La cl\\u00f4ture de l\'a\\u00e9roport a pour objet principal d\'emp\\u00eacher les gens de regarder les a\\u00e9ronefs\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":272,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Un passager accompagn\\u00e9 d\'un nourrisson dans une poussette se pr\\u00e9sente au PIF. Que faites-vous ? (Choisir la r\\u00e9ponse fausse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) le nourrisson doit \\u00eatre retir\\u00e9 de la poussette avant d\'\\u00eatre inspect\\u00e9 filtr\\u00e9\",\"option1_en\":\"\",\"option2_fr\":\"b) la poussette doit \\u00eatre inspect\\u00e9e filtr\\u00e9e s\\u00e9par\\u00e9ment au RX\",\"option2_en\":\"\",\"option3_fr\":\"c) l\'agent de s\\u00fbret\\u00e9 retire le nourrisson et traverse le portique\",\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":3,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":280,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"L\'acc\\u00e8s peut \\u00eatre accord\\u00e9 \\u00e0 un personnel a\\u00e9roportuaire non d\\u00e9tenteur de badge dans les cas suivants (Choisir la bonne r\\u00e9ponse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) il explique que son badge est perdu la veille\",\"option1_en\":\"\",\"option2_fr\":\"b) il vous informe qu\'il a re\\u00e7u une autorisation verbale du D\\u00e9l\\u00e9gu\\u00e9 de l\'ONSFAG\",\"option2_en\":\"\",\"option3_fr\":\"c) ce personnel est connu comme instructeur en s\\u00fbret\\u00e9\",\"option3_en\":null,\"option4_fr\":\"d) aucune des r\\u00e9ponses ci-dessus\",\"option4_en\":null,\"correct_option\":4,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":210,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Suivant la PEN au PIF filtrage des bagages de soute, l\'agent charg\\u00e9 de l\'examen radioscopique au moyen d\'un EDS ou un RX conventionnel envoie un bagage suspect \\u00e0 la fouille manuelle\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":244,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Les LAG contenus dans des r\\u00e9cipients de plus de 100ml sont accept\\u00e9s en cabine s\'ils sont partiellement remplis\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":267,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Dans le cas o\\u00f9 l\'agent de s\\u00fbret\\u00e9 au PIF se trouve en face d\'une situation avec un personnel a\\u00e9roportuaire et que cette situation n\'est pas pr\\u00e9vue par la PEN, il doit (Choisir la bonne r\\u00e9ponse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) faire preuve d\'imagination et trouver la solution\",\"option1_en\":\"\",\"option2_fr\":\"b) s\'adresser au chef de poste pour la suite \\u00e0 donner\",\"option2_en\":\"\",\"option3_fr\":\"c) demander au personnel de proposer la solution appropri\\u00e9e\",\"option3_en\":null,\"option4_fr\":\"d) aucune des r\\u00e9ponses ci-dessus\",\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":237,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Le principe de fonctionnement du portique est la d\\u00e9tection de tout objet (m\\u00e9tallique et non m\\u00e9tallique) d\'une certaine masse\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":284,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Lors du contr\\u00f4le du badge, l\'agent de s\\u00fbret\\u00e9 v\\u00e9rifie particuli\\u00e8rement les mentions suivantes (Choisir la r\\u00e9ponse fausse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) le nom, la photographie, la signature de l\'autorit\\u00e9 et la date de validit\\u00e9\",\"option1_en\":\"\",\"option2_fr\":\"b) le nom, la porte d\'acc\\u00e8s autoris\\u00e9e, la signature du Directeur G\\u00e9n\\u00e9ral de l\'ONSFAG et la date de validit\\u00e9\",\"option2_en\":\"\",\"option3_fr\":\"c) le nom, la signature du Directeur G\\u00e9n\\u00e9ral de l\'ONSFAG, la date de validit\\u00e9 et les zones autoris\\u00e9es\",\"option3_en\":null,\"option4_fr\":\"d) le nom, la photographie, la signature de l\'autorit\\u00e9 et le nom de l\'employeur\",\"option4_en\":null,\"correct_option\":4,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":195,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Un passager \\u00e0 mobilit\\u00e9 r\\u00e9duite (PMR) se pr\\u00e9sente dans un fauteuil roulant au PIF. Son fauteuil est soumis \\u00e0 l\'inspection filtrage \\u00e0 l\'aide du portique.\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":221,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Les agents charg\\u00e9s de la patrouille dans les halls de l\'a\\u00e9rogare passagers ne doivent pas dresser un rapport de patrouille parce que c\'est le r\\u00f4le de l\'\\u00e9quipe de la vid\\u00e9osurveillance\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":256,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"A l\'a\\u00e9roport de Libreville, les zones ci-apr\\u00e8s sont des zones publiques. Choisir la r\\u00e9ponse fausse\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) le salons VIP SAMBA\",\"option1_en\":\"\",\"option2_fr\":\"b) le salons VIP EKENA\",\"option2_en\":\"\",\"option3_fr\":\"c) le parking auto\",\"option3_en\":null,\"option4_fr\":\"d) la salle d\'enregistrement\",\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":286,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Le maintien de la st\\u00e9rilit\\u00e9 de la salle d\'attente des passagers se fait comme suit (Choisir la r\\u00e9ponse fausse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) refuser les acc\\u00e8s non autoris\\u00e9s\",\"option1_en\":\"\",\"option2_fr\":\"b) refuser l\'acc\\u00e8s \\u00e0 tout personnel qui n\'est pas passager\",\"option2_en\":\"\",\"option3_fr\":\"c) inspecter et filtrer toutes les marchandises, fournitures et personnes conform\\u00e9ment aux PEN\",\"option3_en\":null,\"option4_fr\":\"d) verrouiller les portes et autres points d\'entr\\u00e9e de la salle d\'attente\",\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":240,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Les passagers en correspondance sont soumis \\u00e0 un contr\\u00f4le all\\u00e9g\\u00e9 parce qu\'ils ont d\\u00e9j\\u00e0 fait l\'objet de contr\\u00f4les sur les a\\u00e9roports de d\\u00e9part.\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":266,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"A partir de quel instant une zone peut \\u00eatre consid\\u00e9r\\u00e9e comme zone st\\u00e9rile. Choisir la bonne r\\u00e9ponse\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) apr\\u00e8s le nettoyage quotidien par la soci\\u00e9t\\u00e9 de nettoyage\",\"option1_en\":\"\",\"option2_fr\":\"b) une fois que les portes sont ferm\\u00e9es\",\"option2_en\":\"\",\"option3_fr\":\"c) juste apr\\u00e8s la fouille de la zone par une \\u00e9quipe d\'agents de s\\u00fbret\\u00e9\",\"option3_en\":null,\"option4_fr\":\"d) \\u00e0 l\'ouverture du vol\",\"option4_en\":null,\"correct_option\":3,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":257,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Une maman et son b\\u00e9b\\u00e9 se pr\\u00e9sentent au PIF. Le b\\u00e9b\\u00e9 est porteur d\'une arme jouet. Choisir la bonne r\\u00e9ponse\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) l\'agent de s\\u00fbret\\u00e9 s\'assure que le jouet appartient effectivement au b\\u00e9b\\u00e9 avant de laisser passer\",\"option1_en\":\"\",\"option2_fr\":\"b) l\'agent de s\\u00fbret\\u00e9 v\\u00e9rifie le re\\u00e7u d\'achat du jouet avant de laisser passer\",\"option2_en\":\"\",\"option3_fr\":\"c) l\'agent de s\\u00fbret\\u00e9 confisque le jouet\",\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":3,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":232,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"En cas d\'absence de personnels sp\\u00e9cialis\\u00e9s en d\\u00e9minage, le personnel de Police peut proc\\u00e9der au d\\u00e9samor\\u00e7age d\'un EEI\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":243,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Le titulaire du titre d\'acc\\u00e8s visiteur n\'a pas besoin d\'\\u00eatre escort\\u00e9 s\'il connait le circuit a\\u00e9roportuaire\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":219,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Les agents charg\\u00e9s de la patrouille dans les halls de l\'a\\u00e9rogare passagers ne doivent pas s\'occuper de la surveillance des colis abandonn\\u00e9s parce que c\'est le r\\u00f4le de l\'\\u00e9quipe de la vid\\u00e9osurveillance\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":278,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Au PIF du terminal Afrijet, le personnel AVSEC certifi\\u00e9 de l\'ONSFAG : (Choisir la bonne r\\u00e9ponse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) n\'a pas besoin de se soumettre \\u00e0 l\'IF\",\"option1_en\":\"\",\"option2_fr\":\"b) peut se soumettre \\u00e0 l\'IF mais sans TCA\",\"option2_en\":\"\",\"option3_fr\":\"c) se soumet aux m\\u00eames contr\\u00f4les que les passagers et le personnel a\\u00e9roportuaire\",\"option3_en\":null,\"option4_fr\":\"d) aucune r\\u00e9ponse ci-dessus\",\"option4_en\":null,\"correct_option\":3,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":201,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"La fouille de niveau 2 est faite n\\u00e9cessairement dans un isoloir sans un t\\u00e9moin\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":238,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Lorsqu\'un exploitant d\'a\\u00e9ronef prend l\'engagement qu\'un passager ne subisse pas les contr\\u00f4les de s\\u00fbret\\u00e9, le passager est exempt\\u00e9\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":269,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"L\'un des principes cardinaux de la fouille physique des personnes est \\u00ab les femmes fouillent les femmes et les hommes fouillent les hommes \\u00bb. Que faire si l\'\\u00e9quipe de s\\u00fbret\\u00e9 au PIF ne comporte pas de femme ? Choisir la r\\u00e9ponse fausse\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) demander au passager d\'attendre jusqu\'\\u00e0 ce que l\'\\u00e9quipe de rel\\u00e8ve qui comprend une femme soit en service\",\"option1_en\":\"\",\"option2_fr\":\"b) solliciter un personnel a\\u00e9roportuaire femme et lui demander son assistance\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":276,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"En cas de panne du RX au PIF des passagers que faites-vous ? (Choisir la r\\u00e9ponse fausse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) rendre compte imm\\u00e9diatement au superviseur\",\"option1_en\":\"\",\"option2_fr\":\"b) proc\\u00e9der syst\\u00e9matiquement \\u00e0 la fouille manuelle de 100% des bagages de cabine\",\"option2_en\":\"\",\"option3_fr\":\"c) attendre la r\\u00e9paration du RX\",\"option3_en\":null,\"option4_fr\":\"d) informer sans d\\u00e9lai les techniciens de maintenance\",\"option4_en\":null,\"correct_option\":3,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":283,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Le personnel de la GTA peut acc\\u00e9der cot\\u00e9 piste par la porte d\'acc\\u00e8s Brigade GTA (Choisir la bonne r\\u00e9ponse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) en pr\\u00e9sentant seulement le badge s\\u00fbret\\u00e9\",\"option1_en\":\"\",\"option2_fr\":\"b) en se pr\\u00e9sentant avec l\'uniforme et le badge s\\u00fbret\\u00e9\",\"option2_en\":\"\",\"option3_fr\":\"c) en se faisant accompagner par un gendarme en tenue\",\"option3_en\":null,\"option4_fr\":\"d) aucune des r\\u00e9ponses ci-dessus\",\"option4_en\":null,\"correct_option\":4,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":288,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Un passager mal voyant se pr\\u00e9sente au PIF. Quelles sont les dispositions \\u00e0 prendre pour son inspection filtrage ? (Choisir la bonne r\\u00e9ponse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) demander l\'assistance d\'un t\\u00e9moin\",\"option1_en\":\"\",\"option2_fr\":\"b) demander une attestation m\\u00e9dicale prouvant qu\'il est mal voyant\",\"option2_en\":\"\",\"option3_fr\":\"c) lui appliquer une fouille de niveau 2\",\"option3_en\":null,\"option4_fr\":\"d) Toutes les r\\u00e9ponses ci-dessus\",\"option4_en\":null,\"correct_option\":4,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":273,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"A l\'entr\\u00e9e d\'un PIF l\'agent de s\\u00fbret\\u00e9 doit v\\u00e9rifier, pour les membres d\'\\u00e9quipage : (Choisir la r\\u00e9ponse fausse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) avoir leur nom sur la d\\u00e9claration g\\u00e9n\\u00e9rale du vol en cours\",\"option1_en\":\"\",\"option2_fr\":\"b) le badge d\'acc\\u00e8s de l\'ann\\u00e9e en cours\",\"option2_en\":\"\",\"option3_fr\":\"c) la concordance entre le nom sur la carte de membre d\'\\u00e9quipage et la d\\u00e9claration g\\u00e9n\\u00e9rale\",\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":285,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"En cas de d\\u00e9couverte d\'un article suspect, l\'agent de s\\u00fbret\\u00e9 doit suivre les consignes suivantes (Choisir la r\\u00e9ponse fausse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) ne pas toucher l\'engin ni le d\\u00e9placer\",\"option1_en\":\"\",\"option2_fr\":\"b) laisser si possible quelque chose de distinctif aupr\\u00e8s de l\'engin sans le toucher\",\"option2_en\":\"\",\"option3_fr\":\"c) s\'\\u00e9loigner de l\'engin\",\"option3_en\":null,\"option4_fr\":\"d) prendre une photographie de l\'engin pour compte rendu\",\"option4_en\":null,\"correct_option\":4,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":253,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Toute personne travaillant \\u00e0 l\'a\\u00e9roport de Libreville doit porter un titre de circulation (badge d\'identification) chaque fois qu\'elle entre dans les lieux ci-apr\\u00e8s. Choisir la bonne r\\u00e9ponse\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) zone cot\\u00e9 ville\",\"option1_en\":\"\",\"option2_fr\":\"b) zone \\u00e0 acc\\u00e8s r\\u00e9glement\\u00e9\",\"option2_en\":\"\",\"option3_fr\":\"c) parking \\u00e0 v\\u00e9hicules\",\"option3_en\":null,\"option4_fr\":\"d) aucune r\\u00e9ponse ci-dessus\",\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":265,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Les agents de maintenance des \\u00e9quipements de s\\u00fbret\\u00e9 ont la responsabilit\\u00e9. Choisir la r\\u00e9ponse fausse\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) d\'assurer la maintenance des \\u00e9quipements\",\"option1_en\":\"\",\"option2_fr\":\"b) de faire les v\\u00e9rifications de bon fonctionnement des \\u00e9quipements\",\"option2_en\":\"\",\"option3_fr\":\"d) de renseigner le passage des agents Op\\u00e9rateurs\",\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":3,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":259,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Vous \\u00eates superviseur du PIF : le passager refuse de soumettre son bagage de cabine \\u00e0 la fouille manuelle. Choisir la bonne r\\u00e9ponse\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) le passager est imm\\u00e9diatement interpell\\u00e9\",\"option1_en\":\"\",\"option2_fr\":\"b) le bagage est confisqu\\u00e9\",\"option2_en\":\"\",\"option3_fr\":\"c) avoir l\'autorisation de la compagnie a\\u00e9rienne avant de le laissez-passer\",\"option3_en\":null,\"option4_fr\":\"d) aucune r\\u00e9ponse ci-dessus\",\"option4_en\":null,\"correct_option\":4,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":251,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Avant d\'\\u00eatre candidat \\u00e0 la certification, le personnel d\'inspection filtrage doit avoir suivi avec succ\\u00e8s les formations ci-apr\\u00e8s. Choisir la bonne r\\u00e9ponse.\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) Gestion de crises et 123 BASE\",\"option1_en\":\"\",\"option2_fr\":\"b) S\\u00fbret\\u00e9 du fret et imagerie radioscopique\",\"option2_en\":\"\",\"option3_fr\":\"c) 123 BASE et imagerie radioscopique\",\"option3_en\":null,\"option4_fr\":\"d) 123 BASE et S\\u00fbret\\u00e9 du fret\",\"option4_en\":null,\"correct_option\":3,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":225,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"En cas de d\\u00e9couverte d\'un article class\\u00e9 dans la cat\\u00e9gorie des mati\\u00e8res dangereuses, le repr\\u00e9sentant de la compagnie doit \\u00eatre inform\\u00e9 pour qu\'il prenne les mesures appropri\\u00e9es\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":202,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"La GTA est en charge des mesures de s\\u00fbret\\u00e9 cot\\u00e9 piste et cote ville\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":270,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Au PIF correspondance des passagers, un passager se pr\\u00e9sente avec des bouteilles de liqueur contenues dans un sac de s\\u00fbret\\u00e9 \\u00e0 indicateur d\'effraction scell\\u00e9 (STEB) fourni par le vendeur. Que faites-vous ? (Choisir la bonne r\\u00e9ponse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) refuser l\'acc\\u00e8s si les bouteilles ont une contenance sup\\u00e9rieure \\u00e0 100ml\",\"option1_en\":\"\",\"option2_fr\":\"b) appeler le repr\\u00e9sentant de la compagnie a\\u00e9rienne pour avoir son accord\",\"option2_en\":\"\",\"option3_fr\":\"c) le soumettre \\u00e0 l\'examen radioscopique\",\"option3_en\":null,\"option4_fr\":\"d) confisquer automatiquement les bouteilles et rendre compte au chef de poste\",\"option4_en\":null,\"correct_option\":3,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":203,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"HPG a pour mission d\'assurer la protection des installations sensibles du cot\\u00e9 piste\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":228,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Le personnel d\'inspection filtrage doit \\u00eatre certifi\\u00e9 parce que c\'est une norme de l\'OACI et une exigence nationale avant d\'\\u00eatre \\u00eatre autoris\\u00e9 \\u00e0 inspecter filtrer les passagers.\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":214,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Le Passage au PIF du Directeur G\\u00e9n\\u00e9ral de l\'ONSFAG ne fait pas l\'objet d\'une mention dans le registre\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":268,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Avant de proc\\u00e9der \\u00e0 la fouille manuelle d\'un bagage d\'un passager qui est s\\u00e9lectionn\\u00e9 suivant la r\\u00e8gle de fouille al\\u00e9atoire, l\'agent de s\\u00fbret\\u00e9 doit (Choisir la bonne r\\u00e9ponse)\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) expliquer au passager ce pourquoi le bagage est choisi\",\"option1_en\":\"\",\"option2_fr\":\"b) demander au passager de se mettre \\u00e0 l\'\\u00e9cart de la table de fouille\",\"option2_en\":\"\",\"option3_fr\":\"c) demander au passager d\'ouvrir son bagage pour avoir son consentement\",\"option3_en\":null,\"option4_fr\":\"d) solliciter un agent de s\\u00fbret\\u00e9 de m\\u00eame sexe pour ouvrir le bagage\",\"option4_en\":null,\"correct_option\":3,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":245,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Un passager se pr\\u00e9sente au PIF avec une bouteille d\'un litre de Coca-Cola. Que faites-vous ?\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"(a) Vous ne faites rien de sp\\u00e9cial.\",\"option1_en\":\"\",\"option2_fr\":\"(b) Vous permettez au passager de proc\\u00e9der \\u00e0 l\'embarquement apr\\u00e8s lui avoir demand\\u00e9 de gouter au liquide.\",\"option2_en\":\"\",\"option3_fr\":\"(c) Vous lui confisquez l\'article\",\"option3_en\":null,\"option4_fr\":\"d) Aucune r\\u00e9ponse ci-dessus\",\"option4_en\":null,\"correct_option\":3,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":258,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"Vous \\u00eates l\'op\\u00e9rateur de radioscopie du PIF des passagers, vous constatez \\u00e0 l\'\\u00e9cran la pr\\u00e9sence d\'une arme ou d\'une grenade dans le bagage. Choisir la bonne r\\u00e9ponse\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"a) Vous demandez la fouille manuelle du bagage\",\"option1_en\":\"\",\"option2_fr\":\"b) Vous bloquez le tunnel et avisez le superviseur\",\"option2_en\":\"\",\"option3_fr\":\"c) Vous demandez des explications au passager\",\"option3_en\":null,\"option4_fr\":\"d) aucune des r\\u00e9ponses ci-dessus\",\"option4_en\":null,\"correct_option\":2,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null},{\"id\":239,\"idtype_examen\":2,\"type_question\":\"theorique\",\"question_text_fr\":\"En cas d\'absence du passager, la fouille de son bagage de soute peut \\u00eatre faite en pr\\u00e9sence d\'un repr\\u00e9sentant de la compagnie a\\u00e9rienne\",\"question_text_en\":\"\",\"images\":null,\"images_data\":null,\"option1_fr\":\"VRAI\",\"option1_en\":\"\",\"option2_fr\":\"FAUX\",\"option2_en\":\"\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":1,\"bareme\":\"2.00\",\"created_at\":\"2026-04-09 12:55:58\",\"images_traitements\":null}]', NULL, '{\"288\":4,\"284\":1,\"270\":3,\"240\":2,\"202\":2,\"201\":2,\"278\":3,\"204\":2,\"258\":2,\"195\":2,\"228\":1,\"208\":2,\"238\":2,\"256\":2,\"285\":4,\"221\":2,\"250\":3,\"266\":3,\"225\":1,\"237\":2,\"257\":3,\"233\":1,\"273\":2,\"214\":2,\"244\":2,\"269\":2,\"283\":4,\"265\":3,\"267\":2,\"205\":1,\"243\":2,\"261\":4,\"210\":1,\"252\":2,\"203\":2,\"236\":1,\"232\":2,\"276\":3,\"272\":1,\"259\":2,\"219\":2,\"239\":1,\"286\":2,\"215\":2,\"253\":2,\"280\":1,\"268\":1,\"196\":1,\"245\":1,\"251\":3}', '2026-04-17 07:02:46'),
(11, 8, 3, 'pratique', 0, 0, 3, NULL, '[{\"id\":292,\"idtype_examen\":2,\"type_question\":\"pratique\",\"question_text_fr\":\"PRISE DE D\\u00c9CISION\\r\\nBAGAGE DE CABINE N\\u00b03\",\"question_text_en\":\"DECISION MAKING\\r\\nCABIN LUGGAGE NO.3\",\"images\":\"[\\\"q_1776281443_0_315.jpg\\\",\\\"q_1776281443_1_372.jpg\\\",\\\"q_1776281443_2_375.jpg\\\"]\",\"images_data\":null,\"option1_fr\":\"Bagage CLAIR\",\"option1_en\":\"CLEAR Baggage\",\"option2_fr\":\"Bagage SUSPECT\",\"option2_en\":\"SUSPECT Baggage\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":1,\"bareme\":\"20.00\",\"created_at\":\"2026-04-15 20:30:43\",\"images_traitements\":\"{\\\"q_1776281443_0_315.jpg\\\":\\\"color\\\",\\\"q_1776281443_1_372.jpg\\\":\\\"inorganic\\\",\\\"q_1776281443_2_375.jpg\\\":\\\"normal\\\"}\"},{\"id\":295,\"idtype_examen\":2,\"type_question\":\"pratique\",\"question_text_fr\":\"PRISE DE D\\u00c9CISION BAGAGE DE CABINE N\\u00b06\",\"question_text_en\":\"DECISION MAKING CABIN BAGGAGE N\\u00b06\",\"images\":\"[\\\"q_1776413267_0_341.jpg\\\",\\\"q_1776413267_1_268.jpg\\\",\\\"q_1776413267_2_957.jpg\\\"]\",\"images_data\":null,\"option1_fr\":\"Bagage CLAIR\",\"option1_en\":\"CLEAR Baggage\",\"option2_fr\":\"Bagage SUSPECT\",\"option2_en\":\"SUSPECT Baggage\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":1,\"bareme\":\"20.00\",\"created_at\":\"2026-04-17 09:07:47\",\"images_traitements\":\"{\\\"q_1776413267_0_341.jpg\\\":\\\"hp\\\",\\\"q_1776413267_1_268.jpg\\\":\\\"grayscale\\\",\\\"q_1776413267_2_957.jpg\\\":\\\"inorganic\\\"}\"},{\"id\":294,\"idtype_examen\":2,\"type_question\":\"pratique\",\"question_text_fr\":\"PRISE DE D\\u00c9CISION\\r\\nBAGAGE DE CABINE N\\u00b05\",\"question_text_en\":\"DECISION MAKING\\r\\nCABIN LUGGAGE NO.5\",\"images\":\"[\\\"q_1776281915_0_519.jpg\\\",\\\"q_1776281915_1_883.jpg\\\",\\\"q_1776281915_2_700.jpg\\\"]\",\"images_data\":null,\"option1_fr\":\"Bagage CLAIR\",\"option1_en\":\"CLEAR Baggage\",\"option2_fr\":\"Bagage SUSPECT\",\"option2_en\":\"SUSPECT Baggage\",\"option3_fr\":\"Instruments contondants\",\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":4,\"bareme\":\"20.00\",\"created_at\":\"2026-04-15 20:38:35\",\"images_traitements\":\"{\\\"q_1776281915_0_519.jpg\\\":\\\"normal\\\",\\\"q_1776281915_1_883.jpg\\\":\\\"normal\\\",\\\"q_1776281915_2_700.jpg\\\":\\\"normal\\\"}\"},{\"id\":290,\"idtype_examen\":2,\"type_question\":\"pratique\",\"question_text_fr\":\"PRISE DE D\\u00c9CISION\\r\\nBAGAGE DE CABINE N\\u00b01\",\"question_text_en\":\"DECISION MAKING\\r\\nCABIN LUGGAGE N\\u00b01\",\"images\":\"[\\\"q_1776269247_0_804.jpg\\\",\\\"q_1776269247_1_452.jpg\\\",\\\"q_1776269247_2_170.jpg\\\"]\",\"images_data\":null,\"option1_fr\":\"Bagage CLAIR\",\"option1_en\":\"CLEAR Baggage\",\"option2_fr\":\"Bagage SUSPECT\",\"option2_en\":\"SUSPECT Baggage\",\"option3_fr\":null,\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":1,\"bareme\":\"20.00\",\"created_at\":\"2026-04-15 17:07:27\",\"images_traitements\":\"{\\\"q_1776269247_0_804.jpg\\\":\\\"hp\\\",\\\"q_1776269247_1_452.jpg\\\":\\\"color\\\",\\\"q_1776269247_2_170.jpg\\\":\\\"normal\\\"}\"},{\"id\":291,\"idtype_examen\":2,\"type_question\":\"pratique\",\"question_text_fr\":\"PRISE DE D\\u00c9CISION\\r\\nBAGAGE DE CABINE N\\u00b02\",\"question_text_en\":\"DECISION MAKING\\r\\nCABIN LUGGAGE N\\u00b02\",\"images\":\"[\\\"q_1776269678_0_804.jpg\\\",\\\"q_1776269678_1_743.jpg\\\",\\\"q_1776269678_2_815.jpg\\\"]\",\"images_data\":null,\"option1_fr\":\"Bagage CLAIR\",\"option1_en\":\"CLEAR Baggage\",\"option2_fr\":\"Bagage SUSPECT\",\"option2_en\":\"SUSPECT Baggage\",\"option3_fr\":\"Armes \\u00e0 feu, fusils et autres armes\",\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":2,\"bareme\":\"20.00\",\"created_at\":\"2026-04-15 17:14:38\",\"images_traitements\":\"{\\\"q_1776269678_0_804.jpg\\\":\\\"color\\\",\\\"q_1776269678_1_743.jpg\\\":\\\"hp\\\",\\\"q_1776269678_2_815.jpg\\\":\\\"grayscale\\\"}\"},{\"id\":293,\"idtype_examen\":2,\"type_question\":\"pratique\",\"question_text_fr\":\"PRISE DE D\\u00c9CISION\\r\\nBAGAGE DE CABINE N\\u00b04\",\"question_text_en\":\"DECISION MAKING\\r\\nCABIN LUGGAGE NO.4\",\"images\":\"[\\\"q_1776281669_0_662.jpg\\\",\\\"q_1776281669_1_488.jpg\\\",\\\"q_1776281669_2_379.jpg\\\",\\\"q_1776281669_3_513.jpg\\\"]\",\"images_data\":null,\"option1_fr\":\"Bagage CLAIR\",\"option1_en\":\"CLEAR Baggage\",\"option2_fr\":\"Bagage SUSPECT\",\"option2_en\":\"SUSPECT Baggage\",\"option3_fr\":\"Instruments contondants\",\"option3_en\":null,\"option4_fr\":null,\"option4_en\":null,\"correct_option\":4,\"bareme\":\"20.00\",\"created_at\":\"2026-04-15 20:34:29\",\"images_traitements\":\"{\\\"q_1776281669_0_662.jpg\\\":\\\"inorganic\\\",\\\"q_1776281669_1_488.jpg\\\":\\\"hp\\\",\\\"q_1776281669_2_379.jpg\\\":\\\"inorganic\\\",\\\"q_1776281669_3_513.jpg\\\":\\\"normal\\\"}\"}]', '{\"293\":1,\"292\":1,\"291\":1,\"290\":1,\"294\":1,\"295\":1,\"296\":1}', '2026-04-17 09:08:17');

-- --------------------------------------------------------

--
-- Structure de la table `question`
--

CREATE TABLE `question` (
  `id` int(11) NOT NULL,
  `idtype_examen` int(11) NOT NULL COMMENT 'FK type_examen.idtype_examen',
  `type_question` enum('theorique','pratique') DEFAULT 'theorique',
  `question_text_fr` text NOT NULL COMMENT 'Libellé français',
  `question_text_en` text NOT NULL COMMENT 'Libellé anglais',
  `images` longtext DEFAULT NULL COMMENT 'JSON array noms fichiers ex: ["if_scan1.jpg","if_bag1.jpg"]',
  `images_data` longtext DEFAULT NULL COMMENT 'JSON objet traitements par image ex: {"if_scan1.jpg":{"defaut":"hp","disponibles":["normal","hp","organic"]}}',
  `option1_fr` varchar(500) NOT NULL,
  `option1_en` varchar(500) NOT NULL,
  `option2_fr` varchar(500) NOT NULL,
  `option2_en` varchar(500) NOT NULL,
  `option3_fr` varchar(500) DEFAULT NULL,
  `option3_en` varchar(500) DEFAULT NULL,
  `option4_fr` varchar(500) DEFAULT NULL,
  `option4_en` varchar(500) DEFAULT NULL,
  `correct_option` int(1) NOT NULL COMMENT '1 à 4 — option correcte',
  `bareme` decimal(5,2) DEFAULT 2.00 COMMENT 'Points par bonne réponse',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `images_traitements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Association image → traitement : {"scan1.jpg":"contour", ...}' CHECK (json_valid(`images_traitements`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Banque de questions EXASUR — théoriques et pratiques IF';

--
-- Déchargement des données de la table `question`
--

INSERT INTO `question` (`id`, `idtype_examen`, `type_question`, `question_text_fr`, `question_text_en`, `images`, `images_data`, `option1_fr`, `option1_en`, `option2_fr`, `option2_en`, `option3_fr`, `option3_en`, `option4_fr`, `option4_en`, `correct_option`, `bareme`, `created_at`, `images_traitements`) VALUES
(31, 3, 'theorique', 'Quelles sont les qualités fondamentales d\'un bon instructeur en sûreté aéronautique ?', 'What are the fundamental qualities of a good aeronautical security instructor?', NULL, NULL, 'Pédagogie, expertise technique et capacité d\'adaptation aux apprenants', 'Pedagogy, technical expertise and adaptability to learners', 'Autorité stricte, discipline rigide et ton imposant', 'Strict authority, rigid discipline and imposing tone', 'Rapidité d\'exécution et débit de parole élevé uniquement', 'Speed of execution and high speech rate only', 'Disponibilité sans expertise ni formation validée', 'Availability without validated expertise or training', 1, 2.00, '2026-03-24 20:51:58', NULL),
(32, 3, 'theorique', 'Quel document définit le Programme National de Formation des agents de sûreté au Gabon ?', 'Which document defines the National Security Agent Training Programme in Gabon?', NULL, NULL, 'PNFSAC — Programme National de Formation en Sûreté de l\'Aviation Civile', 'PNFSAC — National Civil Aviation Security Training Programme', 'PNSAC — Programme National de Sûreté', 'PNSAC — National Security Programme', 'RAG 3 — Réglementation Aéronautique Gabonaise', 'RAG 3 — Gabonese Aeronautical Regulation', 'L\'Annexe 17 de l\'OACI uniquement', 'ICAO Annex 17 only', 1, 2.00, '2026-03-24 20:51:58', NULL),
(33, 3, 'theorique', 'Comment évaluer efficacement l\'acquisition des compétences lors d\'une formation en sûreté ?', 'How to effectively assess skill acquisition during security training?', NULL, NULL, 'Par des tests pratiques, mises en situation réelles et évaluations formatives continues', 'Through practical tests, real-world simulations and continuous formative assessments', 'Par le nombre d\'heures de présence et la signature des feuilles uniquement', 'By attendance hours and signature sheets only', 'Par la satisfaction globale des apprenants en fin de session', 'By overall learner satisfaction at the end of the session', 'Par l\'obtention d\'un diplôme universitaire', 'By obtaining a university degree', 1, 2.00, '2026-03-24 20:51:58', NULL),
(34, 3, 'theorique', 'La certification d\'instructeur AVSEC doit être renouvelée tous les :', 'AVSEC instructor certification must be renewed every:', NULL, NULL, '3 ans avec formation continue obligatoire', '3 years with mandatory continuous training', '1 an', '1 year', '2 ans', '2 years', '5 ans sans formation complémentaire', '5 years without additional training', 1, 2.00, '2026-03-24 20:51:58', NULL),
(35, 3, 'theorique', 'Quel organisme délivre et valide la certification d\'instructeur en sûreté au Gabon ?', 'Which body issues and validates security instructor certification in Gabon?', NULL, NULL, 'L\'ANAC Gabon (Agence Nationale de l\'Aviation Civile)', 'ANAC Gabon (National Civil Aviation Agency)', 'L\'IATA (Association Internationale du Transport Aérien)', 'IATA (International Air Transport Association)', 'L\'OACI directement par ses bureaux régionaux', 'ICAO directly through its regional offices', 'Le Ministère gabonais des Transports exclusivement', 'The Gabonese Ministry of Transport exclusively', 1, 2.00, '2026-03-24 20:51:58', NULL),
(36, 3, 'theorique', 'Le plan de cours d\'un instructeur certifié doit obligatoirement inclure :', 'A certified instructor\'s lesson plan must include:', NULL, NULL, 'Objectifs d\'apprentissage, contenu structuré, méthodes pédagogiques et modalités d\'évaluation', 'Learning objectives, structured content, teaching methods and assessment modalities', 'Uniquement la date, la salle et le nom de l\'instructeur', 'Only the date, room and instructor name', 'La liste des apprenants et le budget alloué', 'The learner list and allocated budget', 'Le nombre d\'heures prévu et le matériel disponible', 'The planned number of hours and available materials', 1, 2.00, '2026-03-24 20:51:58', NULL),
(37, 3, 'theorique', 'La méthode pédagogique SAVI dans le contexte de la formation AVSEC correspond à :', 'The SAVI teaching method in the AVSEC training context corresponds to:', NULL, NULL, 'Savoir — Attitudes — Valeurs — Intérêts (compétences globales)', 'Knowledge — Attitudes — Values — Interests (global competencies)', 'Situation — Action — Vérification — Impact', 'Situation — Action — Verification — Impact', 'Stratégie — Application — Valorisation — Intégration', 'Strategy — Application — Valorisation — Integration', 'Synthèse — Analyse — Vision — Implémentation', 'Synthesis — Analysis — Vision — Implementation', 1, 2.00, '2026-03-24 20:51:58', NULL),
(38, 3, 'theorique', 'Dans une session de formation en sûreté, la proportion recommandée pratique/théorie est :', 'In a security training session, the recommended practical/theory ratio is:', NULL, NULL, '70% pratique / 30% théorie pour une meilleure acquisition des compétences', '70% practical / 30% theory for better skill acquisition', '50% pratique / 50% théorie équilibré', '50% practical / 50% theory balanced', '30% pratique / 70% théorie avec accent sur les textes', '30% practical / 70% theory with emphasis on texts', '100% théorie pour garantir les connaissances réglementaires', '100% theory to guarantee regulatory knowledge', 1, 2.00, '2026-03-24 20:51:58', NULL),
(39, 3, 'theorique', 'Un instructeur certifié découvre qu\'un agent de son groupe possède une certification de sûreté falsifiée. Il doit :', 'A certified instructor discovers that an agent in their group has a forged security certification. They must:', NULL, NULL, 'Signaler immédiatement le fait à l\'ANAC (autorité compétente)', 'Immediately report the fact to ANAC (competent authority)', 'Ignorer si l\'agent semble techniquement compétent', 'Ignore if the agent seems technically competent', 'En discuter en premier lieu avec ses collègues instructeurs', 'Discuss it first with fellow instructors', 'Attendre une inspection officielle de l\'ANAC pour en parler', 'Wait for an official ANAC inspection to mention it', 1, 2.00, '2026-03-24 20:51:58', NULL),
(40, 3, 'theorique', 'L\'audit de qualité d\'une formation en sûreté aéronautique est réalisé par :', 'Quality audit of aeronautical security training is carried out by:', NULL, NULL, 'L\'ANAC et/ou des auditeurs indépendants accrédités OACI', 'ANAC and/or ICAO-accredited independent auditors', 'L\'instructeur lui-même en auto-évaluation uniquement', 'The instructor alone through self-assessment only', 'Les apprenants par notation anonyme', 'Learners through anonymous rating', 'Le responsable RH de l\'organisme de formation', 'The HR manager of the training organisation', 1, 2.00, '2026-03-24 20:51:58', NULL),
(41, 4, 'theorique', 'Qu\'est-ce que la sensibilisation à la sûreté aéroportuaire ?', 'What is airport security awareness?', NULL, NULL, 'Informer et responsabiliser tout le personnel sur les menaces et risques liés à la sûreté', 'Inform and empower all staff about security threats and risks', 'Apprendre à combattre physiquement des terroristes', 'Learning to physically fight terrorists', 'Surveiller uniquement le comportement des passagers', 'Monitoring passenger behaviour only', 'Former des agents de sécurité armés pour les couloirs', 'Training armed security agents for corridors', 1, 5.00, '2026-03-24 20:51:58', NULL),
(42, 4, 'theorique', 'À qui s\'adresse principalement la sensibilisation à la sûreté aéroportuaire ?', 'Who is airport security awareness primarily aimed at?', NULL, NULL, 'À tout le personnel ayant accès à l\'aéroport (agents, techniciens, commerçants, prestataires)', 'To all personnel with airport access (agents, technicians, traders, contractors)', 'Uniquement aux agents de sûreté certifiés', 'Only to certified security agents', 'Uniquement aux pilotes et au personnel navigant de cabine', 'Only to pilots and cabin crew', 'Uniquement aux passagers des vols internationaux', 'Only to passengers on international flights', 1, 5.00, '2026-03-24 20:51:58', NULL),
(43, 4, 'theorique', 'Face à un bagage abandonné dans la zone publique de l\'aéroport, la première action est :', 'When facing an abandoned bag in the public area of the airport, the first action is:', NULL, NULL, 'Ne pas toucher le bagage et alerter immédiatement les agents de sûreté', 'Do not touch the bag and immediately alert security agents', 'Ouvrir le bagage pour vérifier son contenu soi-même', 'Open the bag to check its contents yourself', 'Le déplacer dans un endroit plus discret pour éviter la panique', 'Move it to a more discreet location to avoid panic', 'Attendre 30 minutes avant d\'agir pour ne pas surréagir', 'Wait 30 minutes before acting to avoid overreacting', 1, 5.00, '2026-03-24 20:51:58', NULL),
(44, 4, 'theorique', 'Un badge d\'accès aéroportuaire est :', 'An airport access badge is:', NULL, NULL, 'Strictement personnel, non transférable et doit être visible en permanence sur la tenue', 'Strictly personal, non-transferable and must be visibly worn at all times', 'Partageable entre collègues du même service en cas d\'urgence', 'Shareable between colleagues of the same department in an emergency', 'Valable sur tous les aéroports du monde sans restriction', 'Valid at all airports worldwide without restriction', 'Optionnel si vous êtes connu du personnel de sécurité', 'Optional if you are known to the security staff', 1, 5.00, '2026-03-24 20:51:58', NULL),
(45, 4, 'theorique', 'Que faire si vous observez une personne non autorisée tentant d\'accéder à une zone réglementée ?', 'What to do if you observe an unauthorised person attempting to access a restricted area?', NULL, NULL, 'Alerter immédiatement le service de sûreté sans intervenir physiquement vous-même', 'Immediately alert the security service without physically intervening yourself', 'L\'interpeller et l\'immobiliser physiquement vous-même', 'Physically confront and restrain them yourself', 'Ignorer si la personne semble inoffensive et tranquille', 'Ignore if the person seems harmless and calm', 'Attendre qu\'un collègue arrive avant d\'agir', 'Wait for a colleague to arrive before acting', 1, 5.00, '2026-03-24 20:51:58', NULL),
(46, 5, 'theorique', '[Module 2] Quel est le fondement juridique international de la sûreté de l\'aviation civile ?', '[Module 2] What is the international legal basis for civil aviation security?', NULL, NULL, 'La Convention de Chicago (1944) et l\'Annexe 17 de l\'OACI', 'The Chicago Convention (1944) and ICAO Annex 17', 'Le Traité de Rome (1957)', 'The Treaty of Rome (1957)', 'La Charte des Nations Unies (1945)', 'The United Nations Charter (1945)', 'La Convention de Genève (1949)', 'The Geneva Convention (1949)', 1, 2.00, '2026-03-24 20:51:58', NULL),
(47, 5, 'theorique', '[Module 2] Qu\'est-ce que le PNSAC et quel est son rôle dans l\'aviation civile gabonaise ?', '[Module 2] What is PNSAC and what is its role in Gabonese civil aviation?', NULL, NULL, 'Programme National de Sûreté de l\'Aviation Civile du Gabon — document-cadre de la sûreté nationale', 'National Civil Aviation Security Programme of Gabon — national security framework document', 'Plan de Normalisation des Services Aéroportuaires Civils', 'Civil Airport Services Standardisation Plan', 'Protocole National de Surveillance Aéronautique Civile', 'National Civil Aeronautical Surveillance Protocol', 'Programme de Navigation et Sécurité Aérienne Contrôlée', 'Controlled Air Navigation and Safety Programme', 1, 2.00, '2026-03-24 20:51:58', NULL),
(48, 5, 'theorique', '[Module 2] L\'ANAC Gabon est principalement responsable de :', '[Module 2] ANAC Gabon is primarily responsible for:', NULL, NULL, 'La réglementation, la supervision et la certification de l\'aviation civile nationale', 'Regulation, supervision and certification of national civil aviation', 'La gestion commerciale des compagnies aériennes au Gabon', 'Commercial management of airlines in Gabon', 'La conception et la construction des aéroports nationaux', 'Design and construction of national airports', 'La délivrance des visas d\'entrée sur le territoire gabonais', 'Issuing entry visas to Gabonese territory', 1, 2.00, '2026-03-24 20:51:58', NULL),
(49, 5, 'theorique', '[Module 2] La sanction en cas de violation grave des règles de sûreté aéroportuaire peut aller jusqu\'à :', '[Module 2] The penalty for serious violation of airport security rules can go up to:', NULL, NULL, 'Des amendes lourdes et/ou l\'emprisonnement selon la législation nationale gabonaise', 'Heavy fines and/or imprisonment under Gabonese national legislation', 'Un simple avertissement oral de la hiérarchie', 'A simple verbal warning from management', 'Une suspension temporaire de badge aéroportuaire uniquement', 'A temporary airport badge suspension only', 'Aucune sanction légale spécifique n\'est prévue', 'No specific legal sanction is provided', 1, 2.00, '2026-03-24 20:51:58', NULL),
(50, 5, 'theorique', '[Module 3] Quelles sont les principales mesures de sûreté à appliquer dans un aéroport ?', '[Module 3] What are the main security measures to be applied in an airport?', NULL, NULL, 'Contrôle d\'accès, inspection filtrage, surveillance périmétrique et fouille des bagages', 'Access control, security screening, perimeter surveillance and baggage search', 'Vente de billets, gestion des files et information voyageurs', 'Ticket sales, queue management and passenger information', 'Nettoyage des pistes, maintenance des terminaux', 'Runway cleaning, terminal maintenance', 'Gestion des parkings, restauration et boutiques hors-taxe', 'Parking management, catering and duty-free shops', 1, 2.00, '2026-03-24 20:51:58', NULL),
(51, 5, 'theorique', '[Module 3] Le contrôle d\'accès en zone réglementée aéroportuaire repose sur :', '[Module 3] Access control in an airport restricted area is based on:', NULL, NULL, 'L\'identification formelle, l\'habilitation vérifiée et le contrôle physique systématique', 'Formal identification, verified clearance and systematic physical control', 'La confiance mutuelle entre les agents et collègues connus', 'Mutual trust between agents and known colleagues', 'La simple présentation d\'une carte professionnelle de l\'entreprise', 'Simple presentation of a company professional card', 'La reconnaissance visuelle du personnel par les agents en poste', 'Visual recognition of staff by agents on duty', 1, 2.00, '2026-03-24 20:51:58', NULL),
(52, 5, 'theorique', '[Module 3] Une zone côté piste (airside) est définie comme :', '[Module 3] An airside zone is defined as:', NULL, NULL, 'Toute zone de mouvement des aéronefs, de chargement et zones adjacentes dont l\'accès est strictement contrôlé', 'Any aircraft movement area, loading area and adjacent zones with strictly controlled access', 'La piste d\'atterrissage et les voies de circulation uniquement', 'The landing runway and taxiways only', 'La zone commerciale et les boutiques de l\'aéroport', 'The commercial zone and airport shops', 'Les bureaux administratifs de la direction de l\'aéroport', 'The administrative offices of the airport management', 1, 2.00, '2026-03-24 20:51:58', NULL),
(55, 5, 'theorique', 'question sur la forme vfvfvf', '', NULL, NULL, 'vv', '', 'ddc', '', 'ddcd', NULL, 'dd', NULL, 1, 2.00, '2026-03-26 11:04:46', NULL),
(99, 1, 'theorique', 'Un passager à mobilité réduite (PMR) se présente dans un fauteuil roulant au PIF. Son fauteuil est soumis à l\'inspection filtrage à l\'aide du portique.', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(100, 1, 'theorique', 'Parmi les personnes exemptées de l\'inspection filtrage conformément à la PEN se trouve les ministres des affaires étrangères', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(101, 1, 'theorique', 'Suivant la PEN du PIF salle embarquement, le minimum d\'agent est de trois (03)', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(102, 1, 'theorique', 'Suivant la PEN du PIF salle embarquement, la prise de service ne prend pas en compte le statut des équipements', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(103, 1, 'theorique', 'Le détecteur de traces d\'explosifs (ETD) n\'est pas un équipement du PIF', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(104, 1, 'theorique', 'Un passager sous garde judiciaire accompagné d\'une escorte est exempté de l\'inspection filtrage au PIF', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(105, 1, 'theorique', 'La fouille de niveau 2 est faite nécessairement dans un isoloir sans un témoin', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(106, 1, 'theorique', 'La GTA est en charge des mesures de sûreté coté piste et cote ville', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(107, 1, 'theorique', 'HPG a pour mission d\'assurer la protection des installations sensibles du coté piste', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(108, 1, 'theorique', 'La rotation du personnel préposé au RX est de 25mn', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(109, 1, 'theorique', 'Parmi les équipements au PIF de filtrage des bagages de soute se trouve un ETD', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(110, 1, 'theorique', 'Suivant la PEN du PIF de filtrage des bagages de soute, les appareils électroniques ne sont pas autorités à embarquer dans les soutes des aéronefs', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(111, 1, 'theorique', 'A l\'ouverture du poste et prise de service, au PIF de filtrage des bagages de soute, le chef de poste n\'assure pas nécessairement la vérification de l\'état des équipements du poste.', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(112, 1, 'theorique', 'La clôture de l\'aéroport a pour objet principal d\'empêcher les gens de regarder les aéronefs', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(113, 1, 'theorique', 'En cas d\'urgence, le personnel de la GTA en uniforme est exempté du contrôle d\'accès au PARIF', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(114, 1, 'theorique', 'Suivant la PEN au PIF filtrage des bagages de soute, l\'agent chargé de l\'examen radioscopique au moyen d\'un EDS ou un RX conventionnel envoie un bagage suspect à la fouille manuelle', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(115, 1, 'theorique', 'Au PIF filtrage des bagages de soute, l\'agent chargé de l\'examen du bagage au moyen de l\'EDS, en cas de détection d\'une menace, demande nécessairement à un collègue de procéder à une fouille manuelle ou à un examen à l\'ETD', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(116, 1, 'theorique', 'Les animaux bénéficient d\'une exemption d\'inspection/filtrage', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(117, 1, 'theorique', 'Si la fouille manuelle d\'un bagage de soute a été effectuée devant un représentant de la compagnie, il n\'est plus nécessaire de mettre un tampon attestant que le bagage a été inspecté/filtré par l\'ONSFAG', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(118, 1, 'theorique', 'Le Passage au PIF du Directeur Général de l\'ONSFAG ne fait pas l\'objet d\'une mention dans le registre', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(119, 1, 'theorique', 'Le Directeur Général de l\'ANAC peut solliciter l\'accès en ZSAR des membres de sa famille non détenteurs de TCA', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(120, 1, 'theorique', 'La PAF est le service en charge de la coordination des mesures de sûreté à l\'aéroport de Libreville', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(121, 1, 'theorique', 'L\'ANAC est l\'autorité aéronautique du Gabon', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(122, 1, 'theorique', 'Un bagage abandonné en salle d\'embarquement avec son étiquette « CHEF CABINE » ne doit pas être traité comme un colis suspect.', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(123, 1, 'theorique', 'Les agents chargés de la patrouille dans les halls de l\'aérogare passagers ne doivent pas s\'occuper de la surveillance des colis abandonnés parce que c\'est le rôle de l\'équipe de la vidéosurveillance', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(124, 1, 'theorique', 'Le Directeur général de GSEZ -Airport assure la présidence du COLSA de Libreville', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(125, 1, 'theorique', 'Les agents chargés de la patrouille dans les halls de l\'aérogare passagers ne doivent pas dresser un rapport de patrouille parce que c\'est le rôle de l\'équipe de la vidéosurveillance', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(126, 1, 'theorique', 'HPG et le COMPOL assurent les mêmes missions de sûreté', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(127, 1, 'theorique', 'Parmi les articles interdits en cabine, les munitions sont considérées comme « Substances Explosives »', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(128, 1, 'theorique', 'Les matières toxiques sont considérées comme des matières dangereuses et sont interdites en cabine', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(129, 1, 'theorique', 'En cas de découverte d\'un article classé dans la catégorie des matières dangereuses, le représentant de la compagnie doit être informé pour qu\'il prenne les mesures appropriées', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(130, 1, 'theorique', 'la certification du personnel de sûreté n\'est pas exigée aux services de l\'Etat qui assurent l\'IF', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(131, 1, 'theorique', 'Les effets personnels des PMR sont uniquement soumis à la fouille manuelle', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(132, 1, 'theorique', 'Le personnel d\'inspection filtrage doit être certifié parce que c\'est une norme de l\'OACI et une exigence nationale avant d\'être être autorisé à inspecter filtrer les passagers.', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(133, 1, 'theorique', 'Au PIF de la salle d\'embarquement, il n\'est pas nécessaire de procéder au contrôle des titres d\'accès des passagers parce qu\'ils ont été déjà contrôlés à l\'enregistrement.', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(134, 1, 'theorique', 'Une femme enceinte n\'est pas autorisée à passer sous le portique', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(135, 1, 'theorique', 'Pour les passagers invoquant les motifs religieux ou culturels, l\'agent de sûreté doit obtenir l\'avis du chef de poste avant de les soumettre aux contrôles de sûreté', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(136, 1, 'theorique', 'En cas d\'absence de personnels spécialisés en déminage, le personnel de Police peut procéder au désamorçage d\'un EEI', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(137, 1, 'theorique', 'Le seuil de réussite (moyenne) à l\'examen de certification est de 80%', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(138, 1, 'theorique', 'Le personnel aéroportuaire doit être inspecté/filtré à un PIF de la même façon qu\'un passager', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(139, 1, 'theorique', 'Un agent de sûreté certifié agent d\'inspection filtrage est exempté des contrôles de sûreté', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(140, 1, 'theorique', 'Les cartes d\'embarquement constituent des titres d\'accès pour les passagers', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(141, 1, 'theorique', 'Le principe de fonctionnement du portique est la détection de tout objet (métallique et non métallique) d\'une certaine masse', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(142, 1, 'theorique', 'Lorsqu\'un exploitant d\'aéronef prend l\'engagement qu\'un passager ne subisse pas les contrôles de sûreté, le passager est exempté', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(143, 1, 'theorique', 'En cas d\'absence du passager, la fouille de son bagage de soute peut être faite en présence d\'un représentant de la compagnie aérienne', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(144, 1, 'theorique', 'Les passagers en correspondance sont soumis à un contrôle allégé parce qu\'ils ont déjà fait l\'objet de contrôles sur les aéroports de départ.', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(145, 1, 'theorique', 'Le Ministère des transports est l\'autorité compétente de l\'aviation civile au Gabon', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(146, 1, 'theorique', 'Le passager titulaire d\'un passeport diplomatique est exempté de l\'inspection filtrage', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(147, 1, 'theorique', 'Le titulaire du titre d\'accès visiteur n\'a pas besoin d\'être escorté s\'il connait le circuit aéroportuaire', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(148, 1, 'theorique', 'Les LAG contenus dans des récipients de plus de 100ml sont acceptés en cabine s\'ils sont partiellement remplis', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(149, 1, 'theorique', 'Un passager se présente au PIF avec une bouteille d\'un litre de Coca-Cola. Que faites-vous ?', '', NULL, NULL, '(a) Vous ne faites rien de spécial.', '', '(b) Vous permettez au passager de procéder à l\'embarquement après lui avoir demandé de gouter au liquide.', '', '(c) Vous lui confisquez l\'article', NULL, 'd) Aucune réponse ci-dessus', NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(150, 1, 'theorique', 'Les membres du personnel de l\'aéroport capables de justifier leur besoin de franchir le point de filtrage des passagers dans le cadre de l\'exercice de leurs fonctions :', '', NULL, NULL, '(a) N\'ont pas à se soumettre à une fouille physique.', '', '(b) N\'ont pas besoin de passer sous le portique de détection des métaux.', '', '(c) Doivent se soumettre aux mêmes contrôles que les passagers.', NULL, NULL, NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(151, 1, 'theorique', 'Une arme est définie par l\'OACI comme étant un article :', '', NULL, NULL, 'a) destiné essentiellement à tuer, blesser, immobiliser ou à réduire à l\'impuissance', '', 'b) utilisé pour compromettre la sécurité des passagers ou celle de l\'aéronef', '', 'c) dont l\'explosion peut être déclenchée.', NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(152, 1, 'theorique', 'Tout engin explosif a quatre composantes principales : laquelle des réponses ci-dessous ne fait pas partie', '', NULL, NULL, 'a) Bloc d\'alimentation', '', 'b) Minuterie / mécanisme de retardement', '', 'c) Détonateur (ou initiateur)', NULL, 'd) Matière incendiaire', NULL, 4, 2.00, '2026-04-09 11:55:58', NULL),
(153, 1, 'theorique', 'Certaines matières réglementées, bien qu\'elles ne soient pas autorisées à voyager en cabine, peuvent être transportées dans la soute d\'un aéronef dans certaines conditions. Choisir la bonne réponse', '', NULL, NULL, 'a) autorisées par le transporteur aérien', '', 'b) autorisées par l\'ONSFAG', '', 'c) ne dépassent pas 100ml', NULL, 'd) autorisées par l\'ANAC', NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(154, 1, 'theorique', 'Quel est le sort réservé aux articles confisqués au PIF. Choisir la bonne réponse', '', NULL, NULL, 'a) détruits par le chef de poste', '', 'b) transmis à la compagnie aérienne', '', 'c) transmis au Chef d\'équipe', NULL, 'd) prendre pour l\'usage du personnel du PIF', NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(155, 1, 'theorique', 'Avant d\'être candidat à la certification, le personnel d\'inspection filtrage doit avoir suivi avec succès les formations ci-après. Choisir la bonne réponse.', '', NULL, NULL, 'a) Gestion de crises et 123 BASE', '', 'b) Sûreté du fret et imagerie radioscopique', '', 'c) 123 BASE et imagerie radioscopique', NULL, 'd) 123 BASE et Sûreté du fret', NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(156, 1, 'theorique', 'Un prisonnier sous escorte de gardes armés ne doit pas être soumis aux contrôles de sureté avant de voyager à bord des aéronefs parce que : (Choisir la bonne réponse)', '', NULL, NULL, 'a) L\'autorité judiciaire est responsable de son transport ;', '', 'b) Il a subi tous les contrôles avant d\'arriver à l\'aéroport ;', '', 'c) Le gouvernement et l\'exploitant d\'aéronef ont convenu des dispositions préalables ;', NULL, 'd) Aucune des réponses ci-dessus', NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(157, 1, 'theorique', 'Toute personne travaillant à l\'aéroport de Libreville doit porter un titre de circulation (badge d\'identification) chaque fois qu\'elle entre dans les lieux ci-après. Choisir la bonne réponse', '', NULL, NULL, 'a) zone coté ville', '', 'b) zone à accès réglementé', '', 'c) parking à véhicules', NULL, 'd) aucune réponse ci-dessus', NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(158, 1, 'theorique', 'Quelles mesures doivent être prises si un mélange ou un contact entre des passagers ayant été soumis à l\'inspection filtrage et d\'autres personnes non soumises à ce contrôle se réalise ? Choisir la bonne réponse', '', NULL, NULL, 'a) aucune mesure', '', 'b) tous les passagers doivent être soumis de nouveau à l\'inspection filtrage après la fouille de la salle d\'attente', '', 'c) annuler tous les vols et demander aux passagers de revenir le lendemain', NULL, 'd) toutes les réponses ci-dessus', NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(159, 1, 'theorique', 'En effectuant l\'inspection filtrage d\'un passager, il est essentiel de s\'assurer que toutes les alarmes sont traitées. Choisir la bonne réponse.', '', NULL, NULL, 'a) en demandant aux passagers de nous dire quels sont les objets métalliques qu\'il porte sur lui.', '', 'b) en diminuant le volume de l\'alarme sonore', '', 'c) en demandant au passager de présenter son passeport et sa carte d\'embarquement', NULL, 'd) aucune des réponses ci-dessus', NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(160, 1, 'theorique', 'A l\'aéroport de Libreville, les zones ci-après sont des zones publiques. Choisir la réponse fausse', '', NULL, NULL, 'a) le salons VIP SAMBA', '', 'b) le salons VIP EKENA', '', 'c) le parking auto', NULL, 'd) la salle d\'enregistrement', NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(161, 1, 'theorique', 'Une maman et son bébé se présentent au PIF. Le bébé est porteur d\'une arme jouet. Choisir la bonne réponse', '', NULL, NULL, 'a) l\'agent de sûreté s\'assure que le jouet appartient effectivement au bébé avant de laisser passer', '', 'b) l\'agent de sûreté vérifie le reçu d\'achat du jouet avant de laisser passer', '', 'c) l\'agent de sûreté confisque le jouet', NULL, NULL, NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(162, 1, 'theorique', 'Vous êtes l\'opérateur de radioscopie du PIF des passagers, vous constatez à l\'écran la présence d\'une arme ou d\'une grenade dans le bagage. Choisir la bonne réponse', '', NULL, NULL, 'a) Vous demandez la fouille manuelle du bagage', '', 'b) Vous bloquez le tunnel et avisez le superviseur', '', 'c) Vous demandez des explications au passager', NULL, 'd) aucune des réponses ci-dessus', NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(163, 1, 'theorique', 'Vous êtes superviseur du PIF : le passager refuse de soumettre son bagage de cabine à la fouille manuelle. Choisir la bonne réponse', '', NULL, NULL, 'a) le passager est immédiatement interpellé', '', 'b) le bagage est confisqué', '', 'c) avoir l\'autorisation de la compagnie aérienne avant de le laissez-passer', NULL, 'd) aucune réponse ci-dessus', NULL, 4, 2.00, '2026-04-09 11:55:58', NULL),
(164, 1, 'theorique', 'La PEN du PIF salle d\'embarquement fait obligation de faire la fouille de la zone stérile. Quel est l\'objectif de cette fouille ? Choisir la bonne réponse', '', NULL, NULL, 'a) s\'assurer qu\'aucun passager n\'a oublié ses bagages contenant des objets de valeurs dans la zone', '', 'b) s\'assurer de la propreté des lieux avant sa mise en utilisation', '', 'c) s\'assurer que toutes les portes menant au côté piste sont verrouillées', NULL, 'd) s\'assurer que la zone ne contient aucun article pouvant servir à commettre un acte d\'intervention illicite', NULL, 4, 2.00, '2026-04-09 11:55:58', NULL),
(165, 1, 'theorique', 'Parmi les articles ci-après lequel n\'est pas autorisé en soute. Choisir la bonne réponse', '', NULL, NULL, 'a) Pistolets à fusées de signalisation', '', 'b) Pistolets d\'enfants de tous types', '', 'c) Pistolets de départ', NULL, 'd) Explosifs', NULL, 4, 2.00, '2026-04-09 11:55:58', NULL),
(166, 1, 'theorique', 'En matière de transport d\'arme, le détenteur doit avoir (choisir la bonne réponse)', '', NULL, NULL, 'a) une autorisation écrite de port d\'arme', '', 'b) des autorisations de l\'ONSFAG et l\'ANAC', '', 'c) des autorisations délivrées par la compagnie', NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(167, 1, 'theorique', 'La PEN du PIF salle d\'embarquement indique les missions du chef de poste. (Choisir la réponse fausse)', '', NULL, NULL, 'a) remplir et signer les fiches d\'incident', '', 'b) signaler le passage du personnel des membres d\'équipage', '', 'c) remplir les fiches de saisies s\'il y a lieu', NULL, 'd) renseigner les évènements dans registre du poste', NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(168, 1, 'theorique', 'En sûreté la norme est', '', NULL, NULL, 'a) obligatoire', '', 'b) nécessaire', '', 'c) souhaitable', NULL, 'd) A et B', NULL, 4, 2.00, '2026-04-09 11:55:58', NULL),
(169, 1, 'theorique', 'Les agents de maintenance des équipements de sûreté ont la responsabilité. Choisir la réponse fausse', '', NULL, NULL, 'a) d\'assurer la maintenance des équipements', '', 'b) de faire les vérifications de bon fonctionnement des équipements', '', 'd) de renseigner le passage des agents Opérateurs', NULL, NULL, NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(170, 1, 'theorique', 'A partir de quel instant une zone peut être considérée comme zone stérile. Choisir la bonne réponse', '', NULL, NULL, 'a) après le nettoyage quotidien par la société de nettoyage', '', 'b) une fois que les portes sont fermées', '', 'c) juste après la fouille de la zone par une équipe d\'agents de sûreté', NULL, 'd) à l\'ouverture du vol', NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(171, 1, 'theorique', 'Dans le cas où l\'agent de sûreté au PIF se trouve en face d\'une situation avec un personnel aéroportuaire et que cette situation n\'est pas prévue par la PEN, il doit (Choisir la bonne réponse)', '', NULL, NULL, 'a) faire preuve d\'imagination et trouver la solution', '', 'b) s\'adresser au chef de poste pour la suite à donner', '', 'c) demander au personnel de proposer la solution appropriée', NULL, 'd) aucune des réponses ci-dessus', NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(172, 1, 'theorique', 'Avant de procéder à la fouille manuelle d\'un bagage d\'un passager qui est sélectionné suivant la règle de fouille aléatoire, l\'agent de sûreté doit (Choisir la bonne réponse)', '', NULL, NULL, 'a) expliquer au passager ce pourquoi le bagage est choisi', '', 'b) demander au passager de se mettre à l\'écart de la table de fouille', '', 'c) demander au passager d\'ouvrir son bagage pour avoir son consentement', NULL, 'd) solliciter un agent de sûreté de même sexe pour ouvrir le bagage', NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(173, 1, 'theorique', 'L\'un des principes cardinaux de la fouille physique des personnes est « les femmes fouillent les femmes et les hommes fouillent les hommes ». Que faire si l\'équipe de sûreté au PIF ne comporte pas de femme ? Choisir la réponse fausse', '', NULL, NULL, 'a) demander au passager d\'attendre jusqu\'à ce que l\'équipe de relève qui comprend une femme soit en service', '', 'b) solliciter un personnel aéroportuaire femme et lui demander son assistance', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(174, 1, 'theorique', 'Au PIF correspondance des passagers, un passager se présente avec des bouteilles de liqueur contenues dans un sac de sûreté à indicateur d\'effraction scellé (STEB) fourni par le vendeur. Que faites-vous ? (Choisir la bonne réponse)', '', NULL, NULL, 'a) refuser l\'accès si les bouteilles ont une contenance supérieure à 100ml', '', 'b) appeler le représentant de la compagnie aérienne pour avoir son accord', '', 'c) le soumettre à l\'examen radioscopique', NULL, 'd) confisquer automatiquement les bouteilles et rendre compte au chef de poste', NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(175, 1, 'theorique', 'La PEN des PIF passagers indique des exemptions peuvent être accordées quant au volume des LAG autorisées. Il s\'agit de : (Choisir la réponse fausse)', '', NULL, NULL, 'a) LAG nécessaires pour raisons médicales', '', 'b) LAG nécessaires des besoins diététiques spéciaux', '', 'c) Bouteille d\'eau nécessaire pendant l\'attente en zone stérile (salle d\'embarquement)', NULL, NULL, NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(176, 1, 'theorique', 'Un passager accompagné d\'un nourrisson dans une poussette se présente au PIF. Que faites-vous ? (Choisir la réponse fausse)', '', NULL, NULL, 'a) le nourrisson doit être retiré de la poussette avant d\'être inspecté filtré', '', 'b) la poussette doit être inspectée filtrée séparément au RX', '', 'c) l\'agent de sûreté retire le nourrisson et traverse le portique', NULL, NULL, NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(177, 1, 'theorique', 'A l\'entrée d\'un PIF l\'agent de sûreté doit vérifier, pour les membres d\'équipage : (Choisir la réponse fausse)', '', NULL, NULL, 'a) avoir leur nom sur la déclaration générale du vol en cours', '', 'b) le badge d\'accès de l\'année en cours', '', 'c) la concordance entre le nom sur la carte de membre d\'équipage et la déclaration générale', NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(178, 1, 'theorique', 'En cas de panne du portique, que faites-vous ? (Choisir la réponse fausse)', '', NULL, NULL, 'a) rendre compte immédiatement au superviseur', '', 'b) procéder systématiquement à la palpation des passagers', '', 'c) informer sans délai les techniciens de maintenance', NULL, 'd) utiliser le détecteur manuel de métaux', NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(179, 1, 'theorique', 'En cas de panne du portique et du détecteur manuel des métaux, que faites-vous ? (Choisir la réponse fausse)', '', NULL, NULL, 'a) rendre compte immédiatement au superviseur', '', 'b) procéder systématiquement à la palpation de 100% des passagers', '', 'c) informer sans délai les techniciens de maintenance', NULL, 'd) toutes les réponses ci-dessus', NULL, 4, 2.00, '2026-04-09 11:55:58', NULL),
(180, 1, 'theorique', 'En cas de panne du RX au PIF des passagers que faites-vous ? (Choisir la réponse fausse)', '', NULL, NULL, 'a) rendre compte immédiatement au superviseur', '', 'b) procéder systématiquement à la fouille manuelle de 100% des bagages de cabine', '', 'c) attendre la réparation du RX', NULL, 'd) informer sans délai les techniciens de maintenance', NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(181, 1, 'theorique', 'Au PIF dédié à l\'accès des personnels, la PEN prévoit que la fouille aléatoire est appliquée à : (Choisir la bonne réponse)', '', NULL, NULL, 'a) 20% du personnel en cas de menace de niveau 2', '', 'b) 15% du personnel en cas de menace de niveau 1', '', 'c) 10% du personnel', NULL, 'd) aucune réponse ci-dessus', NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(182, 1, 'theorique', 'Au PIF du terminal Afrijet, le personnel AVSEC certifié de l\'ONSFAG : (Choisir la bonne réponse)', '', NULL, NULL, 'a) n\'a pas besoin de se soumettre à l\'IF', '', 'b) peut se soumettre à l\'IF mais sans TCA', '', 'c) se soumet aux mêmes contrôles que les passagers et le personnel aéroportuaire', NULL, 'd) aucune réponse ci-dessus', NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(183, 1, 'theorique', 'L\'accès en zone Tri bagage est réservé aux personnes détentrices des badges avec la zone : (Choisir la réponse fausse)', '', NULL, NULL, 'a) TRA', '', 'b) B', '', 'c) A', NULL, 'd) F', NULL, 4, 2.00, '2026-04-09 11:55:58', NULL),
(184, 1, 'theorique', 'L\'accès peut être accordé à un personnel aéroportuaire non détenteur de badge dans les cas suivants (Choisir la bonne réponse)', '', NULL, NULL, 'a) il explique que son badge est perdu la veille', '', 'b) il vous informe qu\'il a reçu une autorisation verbale du Délégué de l\'ONSFAG', '', 'c) ce personnel est connu comme instructeur en sûreté', NULL, 'd) aucune des réponses ci-dessus', NULL, 4, 2.00, '2026-04-09 11:55:58', NULL),
(185, 1, 'theorique', 'Sur les titres d\'accès de l\'aéroport, les zones de sûreté sont matérialisées par les lettres suivantes (Choisir la réponse fausse)', '', NULL, NULL, 'a) A pour la salle d\'enregistrement', '', 'b) B pour le tri bagages et la galerie des bagages', '', 'c) T pour l\'aire de stationnement des aéronefs et bloc technique ASECNA', NULL, 'f) F pour l\'aérogare fret', NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(186, 1, 'theorique', 'Les articles suivants sont interdits en cabine (Choisir la réponse fausse)', '', NULL, NULL, 'a) armes à feu', '', 'b) objets pointus', '', 'c) sèche-cheveux', NULL, 'd) briquets', NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(187, 1, 'theorique', 'Le personnel de la GTA peut accéder coté piste par la porte d\'accès Brigade GTA (Choisir la bonne réponse)', '', NULL, NULL, 'a) en présentant seulement le badge sûreté', '', 'b) en se présentant avec l\'uniforme et le badge sûreté', '', 'c) en se faisant accompagner par un gendarme en tenue', NULL, 'd) aucune des réponses ci-dessus', NULL, 4, 2.00, '2026-04-09 11:55:58', NULL),
(188, 1, 'theorique', 'Lors du contrôle du badge, l\'agent de sûreté vérifie particulièrement les mentions suivantes (Choisir la réponse fausse)', '', NULL, NULL, 'a) le nom, la photographie, la signature de l\'autorité et la date de validité', '', 'b) le nom, la porte d\'accès autorisée, la signature du Directeur Général de l\'ONSFAG et la date de validité', '', 'c) le nom, la signature du Directeur Général de l\'ONSFAG, la date de validité et les zones autorisées', NULL, 'd) le nom, la photographie, la signature de l\'autorité et le nom de l\'employeur', NULL, 4, 2.00, '2026-04-09 11:55:58', NULL),
(189, 1, 'theorique', 'En cas de découverte d\'un article suspect, l\'agent de sûreté doit suivre les consignes suivantes (Choisir la réponse fausse)', '', NULL, NULL, 'a) ne pas toucher l\'engin ni le déplacer', '', 'b) laisser si possible quelque chose de distinctif auprès de l\'engin sans le toucher', '', 'c) s\'éloigner de l\'engin', NULL, 'd) prendre une photographie de l\'engin pour compte rendu', NULL, 4, 2.00, '2026-04-09 11:55:58', NULL),
(190, 1, 'theorique', 'Le maintien de la stérilité de la salle d\'attente des passagers se fait comme suit (Choisir la réponse fausse)', '', NULL, NULL, 'a) refuser les accès non autorisés', '', 'b) refuser l\'accès à tout personnel qui n\'est pas passager', '', 'c) inspecter et filtrer toutes les marchandises, fournitures et personnes conformément aux PEN', NULL, 'd) verrouiller les portes et autres points d\'entrée de la salle d\'attente', NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(191, 1, 'theorique', 'La liste des badges perdus permet à un agent au PIF de (Choisir la bonne réponse)', '', NULL, NULL, 'a) s\'assurer qu\'aucun badge perdu ne sera utilisé pour accéder frauduleusement', '', 'b) connaitre le nombre de badge qui ne sont plus en la possession des titulaires', '', 'c) identifier les vrais titulaires des badges perdus', NULL, 'd) toutes les réponses ci-dessus', NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(192, 1, 'theorique', 'Un passager mal voyant se présente au PIF. Quelles sont les dispositions à prendre pour son inspection filtrage ? (Choisir la bonne réponse)', '', NULL, NULL, 'a) demander l\'assistance d\'un témoin', '', 'b) demander une attestation médicale prouvant qu\'il est mal voyant', '', 'c) lui appliquer une fouille de niveau 2', NULL, 'd) Toutes les réponses ci-dessus', NULL, 4, 2.00, '2026-04-09 11:55:58', NULL),
(193, 1, 'theorique', 'Quel est le principal document de travail d\'un agent de sûreté à un PIF?', '', NULL, NULL, 'a) PEN', '', 'b) PNSAC', '', 'c) AVSEC-FAL', NULL, 'd) PNSQ', NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(195, 2, 'theorique', 'Un passager à mobilité réduite (PMR) se présente dans un fauteuil roulant au PIF. Son fauteuil est soumis à l\'inspection filtrage à l\'aide du portique.', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(196, 2, 'theorique', 'Parmi les personnes exemptées de l\'inspection filtrage conformément à la PEN se trouve les ministres des affaires étrangères', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(197, 2, 'theorique', 'Suivant la PEN du PIF salle embarquement, le minimum d\'agent est de trois (03)', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(198, 2, 'theorique', 'Suivant la PEN du PIF salle embarquement, la prise de service ne prend pas en compte le statut des équipements', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(199, 2, 'theorique', 'Le détecteur de traces d\'explosifs (ETD) n\'est pas un équipement du PIF', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(200, 2, 'theorique', 'Un passager sous garde judiciaire accompagné d\'une escorte est exempté de l\'inspection filtrage au PIF', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(201, 2, 'theorique', 'La fouille de niveau 2 est faite nécessairement dans un isoloir sans un témoin', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(202, 2, 'theorique', 'La GTA est en charge des mesures de sûreté coté piste et cote ville', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(203, 2, 'theorique', 'HPG a pour mission d\'assurer la protection des installations sensibles du coté piste', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(204, 2, 'theorique', 'La rotation du personnel préposé au RX est de 25mn', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(205, 2, 'theorique', 'Parmi les équipements au PIF de filtrage des bagages de soute se trouve un ETD', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(206, 2, 'theorique', 'Suivant la PEN du PIF de filtrage des bagages de soute, les appareils électroniques ne sont pas autorités à embarquer dans les soutes des aéronefs', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(207, 2, 'theorique', 'A l\'ouverture du poste et prise de service, au PIF de filtrage des bagages de soute, le chef de poste n\'assure pas nécessairement la vérification de l\'état des équipements du poste.', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(208, 2, 'theorique', 'La clôture de l\'aéroport a pour objet principal d\'empêcher les gens de regarder les aéronefs', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(209, 2, 'theorique', 'En cas d\'urgence, le personnel de la GTA en uniforme est exempté du contrôle d\'accès au PARIF', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL);
INSERT INTO `question` (`id`, `idtype_examen`, `type_question`, `question_text_fr`, `question_text_en`, `images`, `images_data`, `option1_fr`, `option1_en`, `option2_fr`, `option2_en`, `option3_fr`, `option3_en`, `option4_fr`, `option4_en`, `correct_option`, `bareme`, `created_at`, `images_traitements`) VALUES
(210, 2, 'theorique', 'Suivant la PEN au PIF filtrage des bagages de soute, l\'agent chargé de l\'examen radioscopique au moyen d\'un EDS ou un RX conventionnel envoie un bagage suspect à la fouille manuelle', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(211, 2, 'theorique', 'Au PIF filtrage des bagages de soute, l\'agent chargé de l\'examen du bagage au moyen de l\'EDS, en cas de détection d\'une menace, demande nécessairement à un collègue de procéder à une fouille manuelle ou à un examen à l\'ETD', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(212, 2, 'theorique', 'Les animaux bénéficient d\'une exemption d\'inspection/filtrage', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(213, 2, 'theorique', 'Si la fouille manuelle d\'un bagage de soute a été effectuée devant un représentant de la compagnie, il n\'est plus nécessaire de mettre un tampon attestant que le bagage a été inspecté/filtré par l\'ONSFAG', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(214, 2, 'theorique', 'Le Passage au PIF du Directeur Général de l\'ONSFAG ne fait pas l\'objet d\'une mention dans le registre', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(215, 2, 'theorique', 'Le Directeur Général de l\'ANAC peut solliciter l\'accès en ZSAR des membres de sa famille non détenteurs de TCA', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(216, 2, 'theorique', 'La PAF est le service en charge de la coordination des mesures de sûreté à l\'aéroport de Libreville', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(217, 2, 'theorique', 'L\'ANAC est l\'autorité aéronautique du Gabon', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(218, 2, 'theorique', 'Un bagage abandonné en salle d\'embarquement avec son étiquette « CHEF CABINE » ne doit pas être traité comme un colis suspect.', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(219, 2, 'theorique', 'Les agents chargés de la patrouille dans les halls de l\'aérogare passagers ne doivent pas s\'occuper de la surveillance des colis abandonnés parce que c\'est le rôle de l\'équipe de la vidéosurveillance', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(220, 2, 'theorique', 'Le Directeur général de GSEZ -Airport assure la présidence du COLSA de Libreville', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(221, 2, 'theorique', 'Les agents chargés de la patrouille dans les halls de l\'aérogare passagers ne doivent pas dresser un rapport de patrouille parce que c\'est le rôle de l\'équipe de la vidéosurveillance', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(222, 2, 'theorique', 'HPG et le COMPOL assurent les mêmes missions de sûreté', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(223, 2, 'theorique', 'Parmi les articles interdits en cabine, les munitions sont considérées comme « Substances Explosives »', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(224, 2, 'theorique', 'Les matières toxiques sont considérées comme des matières dangereuses et sont interdites en cabine', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(225, 2, 'theorique', 'En cas de découverte d\'un article classé dans la catégorie des matières dangereuses, le représentant de la compagnie doit être informé pour qu\'il prenne les mesures appropriées', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(226, 2, 'theorique', 'la certification du personnel de sûreté n\'est pas exigée aux services de l\'Etat qui assurent l\'IF', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(227, 2, 'theorique', 'Les effets personnels des PMR sont uniquement soumis à la fouille manuelle', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(228, 2, 'theorique', 'Le personnel d\'inspection filtrage doit être certifié parce que c\'est une norme de l\'OACI et une exigence nationale avant d\'être être autorisé à inspecter filtrer les passagers.', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(229, 2, 'theorique', 'Au PIF de la salle d\'embarquement, il n\'est pas nécessaire de procéder au contrôle des titres d\'accès des passagers parce qu\'ils ont été déjà contrôlés à l\'enregistrement.', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(230, 2, 'theorique', 'Une femme enceinte n\'est pas autorisée à passer sous le portique', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(231, 2, 'theorique', 'Pour les passagers invoquant les motifs religieux ou culturels, l\'agent de sûreté doit obtenir l\'avis du chef de poste avant de les soumettre aux contrôles de sûreté', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(232, 2, 'theorique', 'En cas d\'absence de personnels spécialisés en déminage, le personnel de Police peut procéder au désamorçage d\'un EEI', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(233, 2, 'theorique', 'Le seuil de réussite (moyenne) à l\'examen de certification est de 80%', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(234, 2, 'theorique', 'Le personnel aéroportuaire doit être inspecté/filtré à un PIF de la même façon qu\'un passager', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(235, 2, 'theorique', 'Un agent de sûreté certifié agent d\'inspection filtrage est exempté des contrôles de sûreté', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(236, 2, 'theorique', 'Les cartes d\'embarquement constituent des titres d\'accès pour les passagers', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(237, 2, 'theorique', 'Le principe de fonctionnement du portique est la détection de tout objet (métallique et non métallique) d\'une certaine masse', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(238, 2, 'theorique', 'Lorsqu\'un exploitant d\'aéronef prend l\'engagement qu\'un passager ne subisse pas les contrôles de sûreté, le passager est exempté', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(239, 2, 'theorique', 'En cas d\'absence du passager, la fouille de son bagage de soute peut être faite en présence d\'un représentant de la compagnie aérienne', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(240, 2, 'theorique', 'Les passagers en correspondance sont soumis à un contrôle allégé parce qu\'ils ont déjà fait l\'objet de contrôles sur les aéroports de départ.', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(241, 2, 'theorique', 'Le Ministère des transports est l\'autorité compétente de l\'aviation civile au Gabon', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(242, 2, 'theorique', 'Le passager titulaire d\'un passeport diplomatique est exempté de l\'inspection filtrage', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(243, 2, 'theorique', 'Le titulaire du titre d\'accès visiteur n\'a pas besoin d\'être escorté s\'il connait le circuit aéroportuaire', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(244, 2, 'theorique', 'Les LAG contenus dans des récipients de plus de 100ml sont acceptés en cabine s\'ils sont partiellement remplis', '', NULL, NULL, 'VRAI', '', 'FAUX', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(245, 2, 'theorique', 'Un passager se présente au PIF avec une bouteille d\'un litre de Coca-Cola. Que faites-vous ?', '', NULL, NULL, '(a) Vous ne faites rien de spécial.', '', '(b) Vous permettez au passager de procéder à l\'embarquement après lui avoir demandé de gouter au liquide.', '', '(c) Vous lui confisquez l\'article', NULL, 'd) Aucune réponse ci-dessus', NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(246, 2, 'theorique', 'Les membres du personnel de l\'aéroport capables de justifier leur besoin de franchir le point de filtrage des passagers dans le cadre de l\'exercice de leurs fonctions :', '', NULL, NULL, '(a) N\'ont pas à se soumettre à une fouille physique.', '', '(b) N\'ont pas besoin de passer sous le portique de détection des métaux.', '', '(c) Doivent se soumettre aux mêmes contrôles que les passagers.', NULL, NULL, NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(247, 2, 'theorique', 'Une arme est définie par l\'OACI comme étant un article :', '', NULL, NULL, 'a) destiné essentiellement à tuer, blesser, immobiliser ou à réduire à l\'impuissance', '', 'b) utilisé pour compromettre la sécurité des passagers ou celle de l\'aéronef', '', 'c) dont l\'explosion peut être déclenchée.', NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(248, 2, 'theorique', 'Tout engin explosif a quatre composantes principales : laquelle des réponses ci-dessous ne fait pas partie', '', NULL, NULL, 'a) Bloc d\'alimentation', '', 'b) Minuterie / mécanisme de retardement', '', 'c) Détonateur (ou initiateur)', NULL, 'd) Matière incendiaire', NULL, 4, 2.00, '2026-04-09 11:55:58', NULL),
(249, 2, 'theorique', 'Certaines matières réglementées, bien qu\'elles ne soient pas autorisées à voyager en cabine, peuvent être transportées dans la soute d\'un aéronef dans certaines conditions. Choisir la bonne réponse', '', NULL, NULL, 'a) autorisées par le transporteur aérien', '', 'b) autorisées par l\'ONSFAG', '', 'c) ne dépassent pas 100ml', NULL, 'd) autorisées par l\'ANAC', NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(250, 2, 'theorique', 'Quel est le sort réservé aux articles confisqués au PIF. Choisir la bonne réponse', '', NULL, NULL, 'a) détruits par le chef de poste', '', 'b) transmis à la compagnie aérienne', '', 'c) transmis au Chef d\'équipe', NULL, 'd) prendre pour l\'usage du personnel du PIF', NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(251, 2, 'theorique', 'Avant d\'être candidat à la certification, le personnel d\'inspection filtrage doit avoir suivi avec succès les formations ci-après. Choisir la bonne réponse.', '', NULL, NULL, 'a) Gestion de crises et 123 BASE', '', 'b) Sûreté du fret et imagerie radioscopique', '', 'c) 123 BASE et imagerie radioscopique', NULL, 'd) 123 BASE et Sûreté du fret', NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(252, 2, 'theorique', 'Un prisonnier sous escorte de gardes armés ne doit pas être soumis aux contrôles de sureté avant de voyager à bord des aéronefs parce que : (Choisir la bonne réponse)', '', NULL, NULL, 'a) L\'autorité judiciaire est responsable de son transport ;', '', 'b) Il a subi tous les contrôles avant d\'arriver à l\'aéroport ;', '', 'c) Le gouvernement et l\'exploitant d\'aéronef ont convenu des dispositions préalables ;', NULL, 'd) Aucune des réponses ci-dessus', NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(253, 2, 'theorique', 'Toute personne travaillant à l\'aéroport de Libreville doit porter un titre de circulation (badge d\'identification) chaque fois qu\'elle entre dans les lieux ci-après. Choisir la bonne réponse', '', NULL, NULL, 'a) zone coté ville', '', 'b) zone à accès réglementé', '', 'c) parking à véhicules', NULL, 'd) aucune réponse ci-dessus', NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(254, 2, 'theorique', 'Quelles mesures doivent être prises si un mélange ou un contact entre des passagers ayant été soumis à l\'inspection filtrage et d\'autres personnes non soumises à ce contrôle se réalise ? Choisir la bonne réponse', '', NULL, NULL, 'a) aucune mesure', '', 'b) tous les passagers doivent être soumis de nouveau à l\'inspection filtrage après la fouille de la salle d\'attente', '', 'c) annuler tous les vols et demander aux passagers de revenir le lendemain', NULL, 'd) toutes les réponses ci-dessus', NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(255, 2, 'theorique', 'En effectuant l\'inspection filtrage d\'un passager, il est essentiel de s\'assurer que toutes les alarmes sont traitées. Choisir la bonne réponse.', '', NULL, NULL, 'a) en demandant aux passagers de nous dire quels sont les objets métalliques qu\'il porte sur lui.', '', 'b) en diminuant le volume de l\'alarme sonore', '', 'c) en demandant au passager de présenter son passeport et sa carte d\'embarquement', NULL, 'd) aucune des réponses ci-dessus', NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(256, 2, 'theorique', 'A l\'aéroport de Libreville, les zones ci-après sont des zones publiques. Choisir la réponse fausse', '', NULL, NULL, 'a) le salons VIP SAMBA', '', 'b) le salons VIP EKENA', '', 'c) le parking auto', NULL, 'd) la salle d\'enregistrement', NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(257, 2, 'theorique', 'Une maman et son bébé se présentent au PIF. Le bébé est porteur d\'une arme jouet. Choisir la bonne réponse', '', NULL, NULL, 'a) l\'agent de sûreté s\'assure que le jouet appartient effectivement au bébé avant de laisser passer', '', 'b) l\'agent de sûreté vérifie le reçu d\'achat du jouet avant de laisser passer', '', 'c) l\'agent de sûreté confisque le jouet', NULL, NULL, NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(258, 2, 'theorique', 'Vous êtes l\'opérateur de radioscopie du PIF des passagers, vous constatez à l\'écran la présence d\'une arme ou d\'une grenade dans le bagage. Choisir la bonne réponse', '', NULL, NULL, 'a) Vous demandez la fouille manuelle du bagage', '', 'b) Vous bloquez le tunnel et avisez le superviseur', '', 'c) Vous demandez des explications au passager', NULL, 'd) aucune des réponses ci-dessus', NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(259, 2, 'theorique', 'Vous êtes superviseur du PIF : le passager refuse de soumettre son bagage de cabine à la fouille manuelle. Choisir la bonne réponse', '', NULL, NULL, 'a) le passager est immédiatement interpellé', '', 'b) le bagage est confisqué', '', 'c) avoir l\'autorisation de la compagnie aérienne avant de le laissez-passer', NULL, 'd) aucune réponse ci-dessus', NULL, 4, 2.00, '2026-04-09 11:55:58', NULL),
(260, 2, 'theorique', 'La PEN du PIF salle d\'embarquement fait obligation de faire la fouille de la zone stérile. Quel est l\'objectif de cette fouille ? Choisir la bonne réponse', '', NULL, NULL, 'a) s\'assurer qu\'aucun passager n\'a oublié ses bagages contenant des objets de valeurs dans la zone', '', 'b) s\'assurer de la propreté des lieux avant sa mise en utilisation', '', 'c) s\'assurer que toutes les portes menant au côté piste sont verrouillées', NULL, 'd) s\'assurer que la zone ne contient aucun article pouvant servir à commettre un acte d\'intervention illicite', NULL, 4, 2.00, '2026-04-09 11:55:58', NULL),
(261, 2, 'theorique', 'Parmi les articles ci-après lequel n\'est pas autorisé en soute. Choisir la bonne réponse', '', NULL, NULL, 'a) Pistolets à fusées de signalisation', '', 'b) Pistolets d\'enfants de tous types', '', 'c) Pistolets de départ', NULL, 'd) Explosifs', NULL, 4, 2.00, '2026-04-09 11:55:58', NULL),
(262, 2, 'theorique', 'En matière de transport d\'arme, le détenteur doit avoir (choisir la bonne réponse)', '', NULL, NULL, 'a) une autorisation écrite de port d\'arme', '', 'b) des autorisations de l\'ONSFAG et l\'ANAC', '', 'c) des autorisations délivrées par la compagnie', NULL, NULL, NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(263, 2, 'theorique', 'La PEN du PIF salle d\'embarquement indique les missions du chef de poste. (Choisir la réponse fausse)', '', NULL, NULL, 'a) remplir et signer les fiches d\'incident', '', 'b) signaler le passage du personnel des membres d\'équipage', '', 'c) remplir les fiches de saisies s\'il y a lieu', NULL, 'd) renseigner les évènements dans registre du poste', NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(264, 2, 'theorique', 'En sûreté la norme est', '', NULL, NULL, 'a) obligatoire', '', 'b) nécessaire', '', 'c) souhaitable', NULL, 'd) A et B', NULL, 4, 2.00, '2026-04-09 11:55:58', NULL),
(265, 2, 'theorique', 'Les agents de maintenance des équipements de sûreté ont la responsabilité. Choisir la réponse fausse', '', NULL, NULL, 'a) d\'assurer la maintenance des équipements', '', 'b) de faire les vérifications de bon fonctionnement des équipements', '', 'd) de renseigner le passage des agents Opérateurs', NULL, NULL, NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(266, 2, 'theorique', 'A partir de quel instant une zone peut être considérée comme zone stérile. Choisir la bonne réponse', '', NULL, NULL, 'a) après le nettoyage quotidien par la société de nettoyage', '', 'b) une fois que les portes sont fermées', '', 'c) juste après la fouille de la zone par une équipe d\'agents de sûreté', NULL, 'd) à l\'ouverture du vol', NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(267, 2, 'theorique', 'Dans le cas où l\'agent de sûreté au PIF se trouve en face d\'une situation avec un personnel aéroportuaire et que cette situation n\'est pas prévue par la PEN, il doit (Choisir la bonne réponse)', '', NULL, NULL, 'a) faire preuve d\'imagination et trouver la solution', '', 'b) s\'adresser au chef de poste pour la suite à donner', '', 'c) demander au personnel de proposer la solution appropriée', NULL, 'd) aucune des réponses ci-dessus', NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(268, 2, 'theorique', 'Avant de procéder à la fouille manuelle d\'un bagage d\'un passager qui est sélectionné suivant la règle de fouille aléatoire, l\'agent de sûreté doit (Choisir la bonne réponse)', '', NULL, NULL, 'a) expliquer au passager ce pourquoi le bagage est choisi', '', 'b) demander au passager de se mettre à l\'écart de la table de fouille', '', 'c) demander au passager d\'ouvrir son bagage pour avoir son consentement', NULL, 'd) solliciter un agent de sûreté de même sexe pour ouvrir le bagage', NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(269, 2, 'theorique', 'L\'un des principes cardinaux de la fouille physique des personnes est « les femmes fouillent les femmes et les hommes fouillent les hommes ». Que faire si l\'équipe de sûreté au PIF ne comporte pas de femme ? Choisir la réponse fausse', '', NULL, NULL, 'a) demander au passager d\'attendre jusqu\'à ce que l\'équipe de relève qui comprend une femme soit en service', '', 'b) solliciter un personnel aéroportuaire femme et lui demander son assistance', '', NULL, NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(270, 2, 'theorique', 'Au PIF correspondance des passagers, un passager se présente avec des bouteilles de liqueur contenues dans un sac de sûreté à indicateur d\'effraction scellé (STEB) fourni par le vendeur. Que faites-vous ? (Choisir la bonne réponse)', '', NULL, NULL, 'a) refuser l\'accès si les bouteilles ont une contenance supérieure à 100ml', '', 'b) appeler le représentant de la compagnie aérienne pour avoir son accord', '', 'c) le soumettre à l\'examen radioscopique', NULL, 'd) confisquer automatiquement les bouteilles et rendre compte au chef de poste', NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(271, 2, 'theorique', 'La PEN des PIF passagers indique des exemptions peuvent être accordées quant au volume des LAG autorisées. Il s\'agit de : (Choisir la réponse fausse)', '', NULL, NULL, 'a) LAG nécessaires pour raisons médicales', '', 'b) LAG nécessaires des besoins diététiques spéciaux', '', 'c) Bouteille d\'eau nécessaire pendant l\'attente en zone stérile (salle d\'embarquement)', NULL, NULL, NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(272, 2, 'theorique', 'Un passager accompagné d\'un nourrisson dans une poussette se présente au PIF. Que faites-vous ? (Choisir la réponse fausse)', '', NULL, NULL, 'a) le nourrisson doit être retiré de la poussette avant d\'être inspecté filtré', '', 'b) la poussette doit être inspectée filtrée séparément au RX', '', 'c) l\'agent de sûreté retire le nourrisson et traverse le portique', NULL, NULL, NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(273, 2, 'theorique', 'A l\'entrée d\'un PIF l\'agent de sûreté doit vérifier, pour les membres d\'équipage : (Choisir la réponse fausse)', '', NULL, NULL, 'a) avoir leur nom sur la déclaration générale du vol en cours', '', 'b) le badge d\'accès de l\'année en cours', '', 'c) la concordance entre le nom sur la carte de membre d\'équipage et la déclaration générale', NULL, NULL, NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(274, 2, 'theorique', 'En cas de panne du portique, que faites-vous ? (Choisir la réponse fausse)', '', NULL, NULL, 'a) rendre compte immédiatement au superviseur', '', 'b) procéder systématiquement à la palpation des passagers', '', 'c) informer sans délai les techniciens de maintenance', NULL, 'd) utiliser le détecteur manuel de métaux', NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(275, 2, 'theorique', 'En cas de panne du portique et du détecteur manuel des métaux, que faites-vous ? (Choisir la réponse fausse)', '', NULL, NULL, 'a) rendre compte immédiatement au superviseur', '', 'b) procéder systématiquement à la palpation de 100% des passagers', '', 'c) informer sans délai les techniciens de maintenance', NULL, 'd) toutes les réponses ci-dessus', NULL, 4, 2.00, '2026-04-09 11:55:58', NULL),
(276, 2, 'theorique', 'En cas de panne du RX au PIF des passagers que faites-vous ? (Choisir la réponse fausse)', '', NULL, NULL, 'a) rendre compte immédiatement au superviseur', '', 'b) procéder systématiquement à la fouille manuelle de 100% des bagages de cabine', '', 'c) attendre la réparation du RX', NULL, 'd) informer sans délai les techniciens de maintenance', NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(277, 2, 'theorique', 'Au PIF dédié à l\'accès des personnels, la PEN prévoit que la fouille aléatoire est appliquée à : (Choisir la bonne réponse)', '', NULL, NULL, 'a) 20% du personnel en cas de menace de niveau 2', '', 'b) 15% du personnel en cas de menace de niveau 1', '', 'c) 10% du personnel', NULL, 'd) aucune réponse ci-dessus', NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(278, 2, 'theorique', 'Au PIF du terminal Afrijet, le personnel AVSEC certifié de l\'ONSFAG : (Choisir la bonne réponse)', '', NULL, NULL, 'a) n\'a pas besoin de se soumettre à l\'IF', '', 'b) peut se soumettre à l\'IF mais sans TCA', '', 'c) se soumet aux mêmes contrôles que les passagers et le personnel aéroportuaire', NULL, 'd) aucune réponse ci-dessus', NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(279, 2, 'theorique', 'L\'accès en zone Tri bagage est réservé aux personnes détentrices des badges avec la zone : (Choisir la réponse fausse)', '', NULL, NULL, 'a) TRA', '', 'b) B', '', 'c) A', NULL, 'd) F', NULL, 4, 2.00, '2026-04-09 11:55:58', NULL),
(280, 2, 'theorique', 'L\'accès peut être accordé à un personnel aéroportuaire non détenteur de badge dans les cas suivants (Choisir la bonne réponse)', '', NULL, NULL, 'a) il explique que son badge est perdu la veille', '', 'b) il vous informe qu\'il a reçu une autorisation verbale du Délégué de l\'ONSFAG', '', 'c) ce personnel est connu comme instructeur en sûreté', NULL, 'd) aucune des réponses ci-dessus', NULL, 4, 2.00, '2026-04-09 11:55:58', NULL),
(281, 2, 'theorique', 'Sur les titres d\'accès de l\'aéroport, les zones de sûreté sont matérialisées par les lettres suivantes (Choisir la réponse fausse)', '', NULL, NULL, 'a) A pour la salle d\'enregistrement', '', 'b) B pour le tri bagages et la galerie des bagages', '', 'c) T pour l\'aire de stationnement des aéronefs et bloc technique ASECNA', NULL, 'f) F pour l\'aérogare fret', NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(282, 2, 'theorique', 'Les articles suivants sont interdits en cabine (Choisir la réponse fausse)', '', NULL, NULL, 'a) armes à feu', '', 'b) objets pointus', '', 'c) sèche-cheveux', NULL, 'd) briquets', NULL, 3, 2.00, '2026-04-09 11:55:58', NULL),
(283, 2, 'theorique', 'Le personnel de la GTA peut accéder coté piste par la porte d\'accès Brigade GTA (Choisir la bonne réponse)', '', NULL, NULL, 'a) en présentant seulement le badge sûreté', '', 'b) en se présentant avec l\'uniforme et le badge sûreté', '', 'c) en se faisant accompagner par un gendarme en tenue', NULL, 'd) aucune des réponses ci-dessus', NULL, 4, 2.00, '2026-04-09 11:55:58', NULL),
(284, 2, 'theorique', 'Lors du contrôle du badge, l\'agent de sûreté vérifie particulièrement les mentions suivantes (Choisir la réponse fausse)', '', NULL, NULL, 'a) le nom, la photographie, la signature de l\'autorité et la date de validité', '', 'b) le nom, la porte d\'accès autorisée, la signature du Directeur Général de l\'ONSFAG et la date de validité', '', 'c) le nom, la signature du Directeur Général de l\'ONSFAG, la date de validité et les zones autorisées', NULL, 'd) le nom, la photographie, la signature de l\'autorité et le nom de l\'employeur', NULL, 4, 2.00, '2026-04-09 11:55:58', NULL),
(285, 2, 'theorique', 'En cas de découverte d\'un article suspect, l\'agent de sûreté doit suivre les consignes suivantes (Choisir la réponse fausse)', '', NULL, NULL, 'a) ne pas toucher l\'engin ni le déplacer', '', 'b) laisser si possible quelque chose de distinctif auprès de l\'engin sans le toucher', '', 'c) s\'éloigner de l\'engin', NULL, 'd) prendre une photographie de l\'engin pour compte rendu', NULL, 4, 2.00, '2026-04-09 11:55:58', NULL),
(286, 2, 'theorique', 'Le maintien de la stérilité de la salle d\'attente des passagers se fait comme suit (Choisir la réponse fausse)', '', NULL, NULL, 'a) refuser les accès non autorisés', '', 'b) refuser l\'accès à tout personnel qui n\'est pas passager', '', 'c) inspecter et filtrer toutes les marchandises, fournitures et personnes conformément aux PEN', NULL, 'd) verrouiller les portes et autres points d\'entrée de la salle d\'attente', NULL, 2, 2.00, '2026-04-09 11:55:58', NULL),
(287, 2, 'theorique', 'La liste des badges perdus permet à un agent au PIF de (Choisir la bonne réponse)', '', NULL, NULL, 'a) s\'assurer qu\'aucun badge perdu ne sera utilisé pour accéder frauduleusement', '', 'b) connaitre le nombre de badge qui ne sont plus en la possession des titulaires', '', 'c) identifier les vrais titulaires des badges perdus', NULL, 'd) toutes les réponses ci-dessus', NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(288, 2, 'theorique', 'Un passager mal voyant se présente au PIF. Quelles sont les dispositions à prendre pour son inspection filtrage ? (Choisir la bonne réponse)', '', NULL, NULL, 'a) demander l\'assistance d\'un témoin', '', 'b) demander une attestation médicale prouvant qu\'il est mal voyant', '', 'c) lui appliquer une fouille de niveau 2', NULL, 'd) Toutes les réponses ci-dessus', NULL, 4, 2.00, '2026-04-09 11:55:58', NULL),
(289, 2, 'theorique', 'Quel est le principal document de travail d\'un agent de sûreté à un PIF?', '', NULL, NULL, 'a) PEN', '', 'b) PNSAC', '', 'c) AVSEC-FAL', NULL, 'd) PNSQ', NULL, 1, 2.00, '2026-04-09 11:55:58', NULL),
(290, 2, 'pratique', 'PRISE DE DÉCISION\r\nBAGAGE DE CABINE N°1', 'DECISION MAKING\r\nCABIN LUGGAGE N°1', '[\"q_1776269247_0_804.jpg\",\"q_1776269247_1_452.jpg\",\"q_1776269247_2_170.jpg\"]', NULL, 'Bagage CLAIR', 'CLEAR Baggage', 'Bagage SUSPECT', 'SUSPECT Baggage', NULL, NULL, NULL, NULL, 1, 20.00, '2026-04-15 16:07:27', '{\"q_1776269247_0_804.jpg\":\"hp\",\"q_1776269247_1_452.jpg\":\"color\",\"q_1776269247_2_170.jpg\":\"normal\"}'),
(291, 2, 'pratique', 'PRISE DE DÉCISION\r\nBAGAGE DE CABINE N°2', 'DECISION MAKING\r\nCABIN LUGGAGE N°2', '[\"q_1776269678_0_804.jpg\",\"q_1776269678_1_743.jpg\",\"q_1776269678_2_815.jpg\"]', NULL, 'Bagage CLAIR', 'CLEAR Baggage', 'Bagage SUSPECT', 'SUSPECT Baggage', 'Armes à feu, fusils et autres armes', NULL, NULL, NULL, 2, 20.00, '2026-04-15 16:14:38', '{\"q_1776269678_0_804.jpg\":\"color\",\"q_1776269678_1_743.jpg\":\"hp\",\"q_1776269678_2_815.jpg\":\"grayscale\"}'),
(292, 2, 'pratique', 'PRISE DE DÉCISION\r\nBAGAGE DE CABINE N°3', 'DECISION MAKING\r\nCABIN LUGGAGE NO.3', '[\"q_1776281443_0_315.jpg\",\"q_1776281443_1_372.jpg\",\"q_1776281443_2_375.jpg\"]', NULL, 'Bagage CLAIR', 'CLEAR Baggage', 'Bagage SUSPECT', 'SUSPECT Baggage', NULL, NULL, NULL, NULL, 1, 20.00, '2026-04-15 19:30:43', '{\"q_1776281443_0_315.jpg\":\"color\",\"q_1776281443_1_372.jpg\":\"inorganic\",\"q_1776281443_2_375.jpg\":\"normal\"}'),
(293, 2, 'pratique', 'PRISE DE DÉCISION\r\nBAGAGE DE CABINE N°4', 'DECISION MAKING\r\nCABIN LUGGAGE NO.4', '[\"q_1776281669_0_662.jpg\",\"q_1776281669_1_488.jpg\",\"q_1776281669_2_379.jpg\",\"q_1776281669_3_513.jpg\"]', NULL, 'Bagage CLAIR', 'CLEAR Baggage', 'Bagage SUSPECT', 'SUSPECT Baggage', 'Instruments contondants', NULL, NULL, NULL, 4, 20.00, '2026-04-15 19:34:29', '{\"q_1776281669_0_662.jpg\":\"inorganic\",\"q_1776281669_1_488.jpg\":\"hp\",\"q_1776281669_2_379.jpg\":\"inorganic\",\"q_1776281669_3_513.jpg\":\"normal\"}'),
(294, 2, 'pratique', 'PRISE DE DÉCISION\r\nBAGAGE DE CABINE N°5', 'DECISION MAKING\r\nCABIN LUGGAGE NO.5', '[\"q_1776281915_0_519.jpg\",\"q_1776281915_1_883.jpg\",\"q_1776281915_2_700.jpg\"]', NULL, 'Bagage CLAIR', 'CLEAR Baggage', 'Bagage SUSPECT', 'SUSPECT Baggage', 'Instruments contondants', NULL, NULL, NULL, 4, 20.00, '2026-04-15 19:38:35', '{\"q_1776281915_0_519.jpg\":\"normal\",\"q_1776281915_1_883.jpg\":\"normal\",\"q_1776281915_2_700.jpg\":\"normal\"}'),
(295, 2, 'pratique', 'PRISE DE DÉCISION BAGAGE DE CABINE N°6', 'DECISION MAKING CABIN BAGGAGE N°6', '[\"q_1776413267_0_341.jpg\",\"q_1776413267_1_268.jpg\",\"q_1776413267_2_957.jpg\"]', NULL, 'Bagage CLAIR', 'CLEAR Baggage', 'Bagage SUSPECT', 'SUSPECT Baggage', NULL, NULL, NULL, NULL, 1, 20.00, '2026-04-17 08:07:47', '{\"q_1776413267_0_341.jpg\":\"hp\",\"q_1776413267_1_268.jpg\":\"grayscale\",\"q_1776413267_2_957.jpg\":\"inorganic\"}'),
(296, 2, 'pratique', 'PRENDRE UNE  DÉCISION\r\nBAGAGE DE CABINE N°7', 'MAKE A  DECISION\r\nCABIN LUGGAGE NO.7', '[\"q_1776415726_0_163.jpg\",\"q_1776415726_1_117.jpg\",\"q_1776415726_2_815.jpg\"]', NULL, 'Bagage CLAIR', 'CLEAR Baggage', 'Bagage SUSPECT', 'SUSPECT Baggage', NULL, NULL, NULL, NULL, 1, 2.00, '2026-04-17 08:48:46', '{\"q_1776415726_0_163.jpg\":\"color\",\"q_1776415726_1_117.jpg\":\"contour\",\"q_1776415726_2_815.jpg\":\"grayscale\"}');

-- --------------------------------------------------------

--
-- Structure de la table `reponses_candidat`
--

CREATE TABLE `reponses_candidat` (
  `id` int(11) NOT NULL,
  `idcandidat` int(11) NOT NULL,
  `id_session` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_option` int(1) DEFAULT NULL,
  `est_correcte` tinyint(1) DEFAULT 0,
  `date_reponse` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `reponses_candidat`
--

INSERT INTO `reponses_candidat` (`id`, `idcandidat`, `id_session`, `question_id`, `selected_option`, `est_correcte`, `date_reponse`) VALUES
(1, 11, 1, 191, 1, 1, '2026-04-15 20:38:05'),
(2, 11, 1, 104, 1, 0, '2026-04-15 20:38:15'),
(3, 11, 1, 121, 1, 0, '2026-04-15 20:38:23'),
(4, 11, 1, 100, 1, 1, '2026-04-15 20:38:31'),
(5, 11, 1, 158, 1, 0, '2026-04-15 20:38:39'),
(6, 11, 1, 192, 1, 0, '2026-04-15 20:38:46'),
(7, 11, 1, 187, 2, 0, '2026-04-15 20:38:53'),
(8, 11, 1, 183, 1, 0, '2026-04-15 20:38:55'),
(9, 11, 1, 129, 1, 1, '2026-04-15 20:38:57'),
(10, 11, 1, 163, 1, 0, '2026-04-15 20:38:59'),
(11, 11, 1, 106, 1, 0, '2026-04-15 20:39:02'),
(12, 11, 1, 178, 2, 1, '2026-04-15 20:39:04'),
(13, 11, 1, 176, 1, 0, '2026-04-15 20:39:06'),
(14, 11, 1, 112, 1, 0, '2026-04-15 20:39:08'),
(15, 11, 1, 126, 1, 0, '2026-04-15 20:39:16'),
(16, 11, 1, 172, 1, 0, '2026-04-15 20:39:18'),
(17, 11, 1, 105, 1, 0, '2026-04-15 20:39:20'),
(18, 11, 1, 107, 1, 0, '2026-04-15 20:39:22'),
(19, 11, 1, 127, 1, 1, '2026-04-15 20:39:24'),
(20, 11, 1, 193, 1, 1, '2026-04-15 20:39:32'),
(21, 11, 1, 153, 1, 1, '2026-04-15 20:39:51'),
(22, 11, 1, 128, 1, 1, '2026-04-15 20:39:56'),
(23, 11, 1, 152, 1, 0, '2026-04-15 20:40:06'),
(24, 11, 1, 143, 1, 1, '2026-04-15 20:40:08'),
(25, 11, 1, 164, 1, 0, '2026-04-15 20:40:12'),
(26, 11, 1, 101, 1, 1, '2026-04-15 20:40:17'),
(27, 11, 1, 115, 1, 1, '2026-04-15 20:40:20'),
(28, 11, 1, 165, 1, 0, '2026-04-15 20:40:23'),
(29, 11, 1, 159, 2, 0, '2026-04-15 20:40:25'),
(30, 11, 1, 184, 2, 0, '2026-04-15 20:40:27'),
(31, 11, 1, 155, 2, 0, '2026-04-15 20:40:29'),
(32, 11, 1, 171, 2, 1, '2026-04-15 20:40:32'),
(33, 11, 1, 136, 1, 0, '2026-04-15 20:40:34'),
(34, 11, 1, 169, 2, 0, '2026-04-15 20:40:36'),
(35, 11, 1, 180, 3, 1, '2026-04-15 20:40:38'),
(36, 11, 1, 137, 1, 1, '2026-04-15 20:40:40'),
(37, 11, 1, 174, 3, 1, '2026-04-15 20:40:43'),
(38, 11, 1, 102, 1, 0, '2026-04-15 20:40:46'),
(39, 11, 1, 160, 1, 0, '2026-04-15 20:40:48'),
(40, 11, 1, 189, 1, 0, '2026-04-15 20:40:49'),
(41, 11, 1, 138, 1, 1, '2026-04-15 20:40:51'),
(42, 11, 1, 124, 1, 0, '2026-04-15 20:40:53'),
(43, 11, 1, 154, 3, 1, '2026-04-15 20:40:55'),
(44, 11, 1, 148, 1, 0, '2026-04-15 20:40:57'),
(45, 11, 1, 123, 1, 0, '2026-04-15 20:40:59'),
(46, 11, 1, 130, 1, 0, '2026-04-15 20:41:01'),
(47, 11, 1, 144, 1, 0, '2026-04-15 20:41:04'),
(48, 11, 1, 118, 1, 0, '2026-04-15 20:41:06'),
(49, 11, 1, 125, 1, 0, '2026-04-15 20:41:08'),
(50, 11, 1, 132, 1, 1, '2026-04-15 20:41:10'),
(51, 15, 4, 31, 1, 1, '2026-04-16 16:38:16'),
(52, 15, 4, 34, 1, 1, '2026-04-16 16:38:23'),
(53, 15, 4, 33, 1, 1, '2026-04-16 16:38:28'),
(54, 15, 4, 40, 2, 0, '2026-04-16 16:38:32'),
(55, 15, 4, 32, 1, 1, '2026-04-16 16:39:33'),
(56, 15, 4, 38, 1, 1, '2026-04-16 16:39:37'),
(57, 15, 4, 36, 1, 1, '2026-04-16 16:39:41'),
(58, 15, 4, 37, 1, 1, '2026-04-16 16:39:44'),
(59, 15, 4, 35, 1, 1, '2026-04-16 16:39:51'),
(60, 15, 4, 39, 1, 1, '2026-04-16 16:39:54'),
(61, 17, 5, 42, 1, 1, '2026-04-16 16:50:52'),
(62, 15, 5, 45, 1, 1, '2026-04-16 16:50:58'),
(63, 17, 5, 43, 1, 1, '2026-04-16 16:51:02'),
(64, 15, 5, 41, 1, 1, '2026-04-16 16:51:03'),
(65, 15, 5, 44, 2, 0, '2026-04-16 16:51:08'),
(66, 15, 5, 42, 1, 1, '2026-04-16 16:51:10'),
(67, 17, 5, 41, 3, 0, '2026-04-16 16:51:12'),
(68, 15, 5, 43, 4, 0, '2026-04-16 16:51:17'),
(69, 17, 5, 45, 3, 0, '2026-04-16 16:51:20'),
(70, 17, 5, 44, 4, 0, '2026-04-16 16:51:24'),
(71, 3, 8, 52, 1, 1, '2026-04-16 17:34:48'),
(72, 3, 8, 47, 1, 1, '2026-04-16 17:35:09'),
(73, 3, 8, 49, 2, 0, '2026-04-16 17:35:12'),
(74, 3, 8, 55, 2, 0, '2026-04-16 17:35:22'),
(75, 3, 8, 48, 2, 0, '2026-04-16 17:35:26'),
(76, 3, 8, 51, 3, 0, '2026-04-16 17:35:26'),
(77, 3, 8, 50, 2, 0, '2026-04-16 17:35:29'),
(78, 3, 8, 46, 2, 0, '2026-04-16 17:35:30'),
(79, 3, 9, 51, 1, 1, '2026-04-16 17:37:02'),
(80, 3, 9, 50, 2, 0, '2026-04-16 17:37:03'),
(81, 3, 9, 49, 2, 0, '2026-04-16 17:37:05'),
(82, 3, 9, 46, 3, 0, '2026-04-16 17:37:07'),
(83, 3, 9, 47, 1, 1, '2026-04-16 17:37:09'),
(84, 3, 9, 55, 2, 0, '2026-04-16 17:37:11'),
(85, 3, 9, 48, 1, 1, '2026-04-16 17:37:14'),
(86, 3, 9, 52, 2, 0, '2026-04-16 17:37:16'),
(87, 20, 8, 47, 2, 0, '2026-04-16 17:40:58'),
(88, 20, 8, 52, 2, 0, '2026-04-16 17:40:59'),
(89, 20, 8, 50, 1, 1, '2026-04-16 17:41:01'),
(90, 20, 8, 49, 1, 1, '2026-04-16 17:41:18'),
(91, 20, 8, 46, 1, 1, '2026-04-16 17:41:28'),
(92, 20, 8, 55, 1, 1, '2026-04-16 17:41:45'),
(93, 20, 8, 51, 2, 0, '2026-04-16 17:41:51'),
(94, 20, 8, 48, 2, 0, '2026-04-16 17:42:54'),
(95, 8, 2, 288, 4, 1, '2026-04-17 06:41:45'),
(96, 8, 2, 284, 1, 0, '2026-04-17 06:41:56'),
(97, 8, 2, 270, 3, 1, '2026-04-17 06:42:03'),
(98, 8, 2, 240, 2, 1, '2026-04-17 06:42:26'),
(99, 8, 2, 202, 2, 1, '2026-04-17 06:42:50'),
(100, 8, 2, 201, 2, 1, '2026-04-17 06:43:00'),
(101, 8, 2, 278, 3, 1, '2026-04-17 06:43:26'),
(102, 8, 2, 204, 2, 1, '2026-04-17 06:43:59'),
(103, 8, 2, 258, 2, 1, '2026-04-17 06:44:26'),
(104, 8, 2, 195, 2, 1, '2026-04-17 06:44:49'),
(105, 8, 2, 228, 1, 1, '2026-04-17 06:44:57'),
(106, 8, 2, 208, 2, 1, '2026-04-17 06:45:20'),
(107, 8, 2, 238, 2, 1, '2026-04-17 06:45:46'),
(108, 8, 2, 256, 2, 1, '2026-04-17 06:46:10'),
(109, 8, 2, 285, 4, 1, '2026-04-17 06:47:21'),
(110, 8, 2, 221, 2, 1, '2026-04-17 06:47:49'),
(111, 8, 2, 250, 3, 1, '2026-04-17 06:48:08'),
(112, 8, 2, 266, 3, 1, '2026-04-17 06:51:35'),
(113, 8, 2, 225, 1, 1, '2026-04-17 06:52:23'),
(114, 8, 2, 237, 2, 1, '2026-04-17 06:52:37'),
(115, 8, 2, 257, 3, 1, '2026-04-17 06:53:06'),
(116, 8, 2, 233, 1, 1, '2026-04-17 06:53:25'),
(117, 8, 2, 273, 2, 1, '2026-04-17 06:53:58'),
(118, 8, 2, 214, 2, 1, '2026-04-17 06:54:48'),
(119, 8, 2, 244, 2, 1, '2026-04-17 06:55:08'),
(120, 8, 2, 269, 2, 1, '2026-04-17 06:55:38'),
(121, 8, 2, 283, 4, 1, '2026-04-17 06:56:27'),
(122, 8, 2, 265, 3, 1, '2026-04-17 06:56:37'),
(123, 8, 2, 267, 2, 1, '2026-04-17 06:56:45'),
(124, 8, 2, 205, 1, 1, '2026-04-17 06:57:12'),
(125, 8, 2, 243, 2, 1, '2026-04-17 06:57:29'),
(126, 8, 2, 261, 4, 1, '2026-04-17 06:57:52'),
(127, 8, 2, 210, 1, 1, '2026-04-17 06:58:29'),
(128, 8, 2, 252, 2, 1, '2026-04-17 06:59:02'),
(129, 8, 2, 203, 2, 1, '2026-04-17 06:59:19'),
(130, 8, 2, 236, 1, 1, '2026-04-17 06:59:33'),
(131, 8, 2, 232, 2, 1, '2026-04-17 06:59:47'),
(132, 8, 2, 276, 3, 1, '2026-04-17 07:00:06'),
(133, 8, 2, 272, 1, 0, '2026-04-17 07:00:12'),
(134, 8, 2, 259, 2, 0, '2026-04-17 07:00:14'),
(135, 8, 2, 219, 2, 1, '2026-04-17 07:00:40'),
(136, 8, 2, 239, 1, 1, '2026-04-17 07:00:47'),
(137, 8, 2, 286, 2, 1, '2026-04-17 07:01:15'),
(138, 8, 2, 215, 2, 1, '2026-04-17 07:01:39'),
(139, 8, 2, 253, 2, 1, '2026-04-17 07:02:11'),
(140, 8, 2, 280, 1, 0, '2026-04-17 07:02:23'),
(141, 8, 2, 268, 1, 0, '2026-04-17 07:02:28'),
(142, 8, 2, 196, 1, 1, '2026-04-17 07:02:29'),
(143, 8, 2, 245, 1, 0, '2026-04-17 07:02:31'),
(144, 8, 2, 251, 3, 1, '2026-04-17 07:02:46'),
(155, 8, 3, 293, 1, 0, '2026-04-17 08:16:35'),
(156, 8, 3, 292, 1, 1, '2026-04-17 08:16:38'),
(157, 8, 3, 291, 1, 0, '2026-04-17 08:16:42'),
(158, 8, 3, 290, 1, 1, '2026-04-17 08:16:46'),
(159, 8, 3, 294, 1, 0, '2026-04-17 08:16:48'),
(160, 8, 3, 290, 1, 1, '2026-04-17 08:24:40'),
(161, 8, 3, 294, 1, 0, '2026-04-17 08:25:02'),
(162, 8, 3, 292, 1, 1, '2026-04-17 08:57:21'),
(163, 8, 3, 294, 1, 0, '2026-04-17 08:57:48'),
(164, 8, 3, 295, 1, 1, '2026-04-17 09:06:50'),
(165, 8, 3, 296, 1, 1, '2026-04-17 09:07:29'),
(166, 8, 3, 294, 1, 0, '2026-04-17 09:07:49'),
(167, 8, 3, 293, 1, 0, '2026-04-17 09:08:15');

-- --------------------------------------------------------

--
-- Structure de la table `resultats`
--

CREATE TABLE `resultats` (
  `id` int(11) NOT NULL,
  `idcandidat` int(11) NOT NULL,
  `id_session` int(11) NOT NULL,
  `idtype_examen` int(11) NOT NULL,
  `note_theorique` decimal(5,2) DEFAULT NULL COMMENT 'Points bruts théorie (IF)',
  `note_pratique` decimal(5,2) DEFAULT NULL COMMENT 'Points bruts pratique (IF)',
  `note_finale` decimal(5,2) NOT NULL,
  `note_sur` decimal(6,2) NOT NULL,
  `pourcentage` decimal(5,2) NOT NULL,
  `moyenne_if` decimal(5,2) DEFAULT NULL COMMENT 'Moyenne (theo+prat)/2 — IF uniquement',
  `reussite_theo` tinyint(1) DEFAULT NULL COMMENT 'Réussite théorie ≥80% — IF',
  `reussite_prat` tinyint(1) DEFAULT NULL COMMENT 'Réussite pratique ≥80% — IF',
  `reussite` tinyint(1) NOT NULL DEFAULT 0,
  `locked` tinyint(1) DEFAULT 0,
  `reason` varchar(255) DEFAULT NULL,
  `date_fin` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `resultats`
--

INSERT INTO `resultats` (`id`, `idcandidat`, `id_session`, `idtype_examen`, `note_theorique`, `note_pratique`, `note_finale`, `note_sur`, `pourcentage`, `moyenne_if`, `reussite_theo`, `reussite_prat`, `reussite`, `locked`, `reason`, `date_fin`) VALUES
(1, 11, 1, 1, NULL, NULL, 36.00, 100.00, 36.00, NULL, NULL, NULL, 0, 0, '', '2026-04-15 20:41:13'),
(2, 15, 4, 3, NULL, NULL, 18.00, 20.00, 90.00, NULL, NULL, NULL, 1, 0, '', '2026-04-16 16:39:58'),
(3, 15, 5, 4, NULL, NULL, 15.00, 25.00, 60.00, NULL, NULL, NULL, 0, 0, '', '2026-04-16 16:51:22'),
(4, 17, 5, 4, NULL, NULL, 10.00, 25.00, 40.00, NULL, NULL, NULL, 0, 0, '', '2026-04-16 16:51:27'),
(5, 3, 8, 5, NULL, NULL, 4.00, 16.00, 25.00, NULL, NULL, NULL, 0, 0, '', '2026-04-16 17:35:35'),
(6, 3, 9, 5, NULL, NULL, 6.00, 16.00, 37.50, NULL, NULL, NULL, 0, 0, '', '2026-04-16 17:37:20'),
(7, 20, 8, 5, NULL, NULL, 8.00, 16.00, 50.00, NULL, NULL, NULL, 0, 0, '', '2026-04-16 17:42:56'),
(8, 8, 2, 2, 88.00, NULL, 88.00, 100.00, 88.00, NULL, 1, NULL, 0, 0, '', '2026-04-17 07:02:58'),
(12, 8, 3, 2, NULL, 62.00, 62.00, 122.00, 50.82, 69.41, NULL, 0, 0, 0, 'Pratique insuffisante (50.8%/min.80%)', '2026-04-17 09:08:17');

-- --------------------------------------------------------

--
-- Structure de la table `session_examen`
--

CREATE TABLE `session_examen` (
  `id_session` int(11) NOT NULL,
  `nom_session` varchar(255) NOT NULL,
  `idtype_examen` int(11) NOT NULL,
  `idtypeformation` int(11) DEFAULT NULL,
  `idmodule` int(11) DEFAULT NULL COMMENT 'Module évalué — FORM uniquement',
  `type_session` enum('theorie','pratique','normal') DEFAULT 'normal',
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `duree_minutes` int(11) NOT NULL DEFAULT 90,
  `statut` enum('planifiee','en_cours','terminee','annulee') DEFAULT 'planifiee',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `session_examen`
--

INSERT INTO `session_examen` (`id_session`, `nom_session`, `idtype_examen`, `idtypeformation`, `idmodule`, `type_session`, `date_debut`, `date_fin`, `duree_minutes`, `statut`, `created_at`) VALUES
(1, 'AS — Certification AVSEC — 13/04/2026', 1, 40, NULL, 'normal', '2026-04-13', '2026-04-24', 90, 'planifiee', '2026-04-15 20:00:48'),
(2, 'IF — Certification AVSEC — 13/04/2026', 2, 40, NULL, 'theorie', '2026-04-13', '2026-04-24', 60, 'planifiee', '2026-04-15 20:03:05'),
(3, 'IF — Pratique — Certification AVSEC — 13/04/2026', 2, 40, NULL, 'pratique', '2026-04-13', '2026-04-24', 60, 'planifiee', '2026-04-15 20:03:05'),
(4, 'INST — Certification AVSEC — 13/04/2026', 3, 40, NULL, 'normal', '2026-04-13', '2026-04-24', 90, 'planifiee', '2026-04-15 20:03:27'),
(5, 'SENS — sensibilisation à la sûreté de l\\\'Aviation Civile — 13/04/2026', 4, 75, NULL, 'normal', '2026-04-13', '2026-04-24', 60, 'planifiee', '2026-04-15 20:04:30'),
(6, 'FORM — sûrété du Fret et de la Poste — 13/04/2026', 5, 43, NULL, 'normal', '2026-04-13', '2026-04-24', 90, 'planifiee', '2026-04-15 20:04:49'),
(7, 'FORM —    Gestion de Crises en sûrété de l\\\'Aviation Civile — 13/04/2026', 5, 38, NULL, 'normal', '2026-04-13', '2026-04-24', 90, 'planifiee', '2026-04-15 20:04:49'),
(8, 'FORM — Module 2 — sûrété du Fret et de la Poste — 13/04/2026', 5, 43, 15, '', '2026-04-13', '2026-04-24', 90, 'planifiee', '2026-04-15 20:09:54'),
(9, 'FORM — Module 4 — sûrété du Fret et de la Poste — 13/04/2026', 5, 43, 17, '', '2026-04-13', '2026-04-24', 90, 'planifiee', '2026-04-15 20:10:17');

-- --------------------------------------------------------

--
-- Structure de la table `session_modules`
--

CREATE TABLE `session_modules` (
  `id` int(11) NOT NULL,
  `id_session` int(11) NOT NULL COMMENT 'session_examen.id_session',
  `idmodule` int(11) NOT NULL COMMENT 'module_formation.idmodule',
  `ordre` int(3) NOT NULL DEFAULT 1 COMMENT 'Ordre de passage',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_eval` date DEFAULT NULL COMMENT 'Date à laquelle ce module peut être évalué',
  `duree_minutes` int(11) DEFAULT 90
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Modules à évaluer pour une session FORM, dans l''ordre';

-- --------------------------------------------------------

--
-- Structure de la table `session_questions`
--

CREATE TABLE `session_questions` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `ordre` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `session_questions`
--

INSERT INTO `session_questions` (`id`, `session_id`, `question_id`, `ordre`) VALUES
(1, 5, 41, 1),
(2, 5, 42, 2),
(3, 5, 43, 3),
(4, 5, 44, 4),
(5, 5, 45, 5),
(6, 4, 31, 1),
(7, 4, 32, 2),
(8, 4, 33, 3),
(9, 4, 34, 4),
(10, 4, 35, 5),
(11, 4, 36, 6),
(12, 4, 37, 7),
(13, 4, 38, 8),
(14, 4, 39, 9),
(15, 4, 40, 10),
(21, 2, 195, 1),
(22, 2, 196, 2),
(23, 2, 201, 3),
(24, 2, 202, 4),
(25, 2, 203, 5),
(26, 2, 204, 6),
(27, 2, 205, 7),
(28, 2, 208, 8),
(29, 2, 210, 9),
(30, 2, 214, 10),
(31, 2, 215, 11),
(32, 2, 219, 12),
(33, 2, 221, 13),
(34, 2, 225, 14),
(35, 2, 228, 15),
(36, 2, 232, 16),
(37, 2, 233, 17),
(38, 2, 236, 18),
(39, 2, 237, 19),
(40, 2, 238, 20),
(41, 2, 239, 21),
(42, 2, 240, 22),
(43, 2, 243, 23),
(44, 2, 244, 24),
(45, 2, 245, 25),
(46, 2, 250, 26),
(47, 2, 251, 27),
(48, 2, 252, 28),
(49, 2, 253, 29),
(50, 2, 256, 30),
(51, 2, 257, 31),
(52, 2, 258, 32),
(53, 2, 259, 33),
(54, 2, 261, 34),
(55, 2, 265, 35),
(56, 2, 266, 36),
(57, 2, 267, 37),
(58, 2, 268, 38),
(59, 2, 269, 39),
(60, 2, 270, 40),
(61, 2, 272, 41),
(62, 2, 273, 42),
(63, 2, 276, 43),
(64, 2, 278, 44),
(65, 2, 280, 45),
(66, 2, 283, 46),
(67, 2, 284, 47),
(68, 2, 285, 48),
(69, 2, 286, 49),
(70, 2, 288, 50),
(71, 1, 100, 1),
(72, 1, 101, 2),
(73, 1, 102, 3),
(74, 1, 104, 4),
(75, 1, 105, 5),
(76, 1, 106, 6),
(77, 1, 107, 7),
(78, 1, 112, 8),
(79, 1, 115, 9),
(80, 1, 118, 10),
(81, 1, 121, 11),
(82, 1, 123, 12),
(83, 1, 124, 13),
(84, 1, 125, 14),
(85, 1, 126, 15),
(86, 1, 127, 16),
(87, 1, 128, 17),
(88, 1, 129, 18),
(89, 1, 130, 19),
(90, 1, 132, 20),
(91, 1, 136, 21),
(92, 1, 137, 22),
(93, 1, 138, 23),
(94, 1, 143, 24),
(95, 1, 144, 25),
(96, 1, 148, 26),
(97, 1, 152, 27),
(98, 1, 153, 28),
(99, 1, 154, 29),
(100, 1, 155, 30),
(101, 1, 158, 31),
(102, 1, 159, 32),
(103, 1, 160, 33),
(104, 1, 163, 34),
(105, 1, 164, 35),
(106, 1, 165, 36),
(107, 1, 169, 37),
(108, 1, 171, 38),
(109, 1, 172, 39),
(110, 1, 174, 40),
(111, 1, 176, 41),
(112, 1, 178, 42),
(113, 1, 180, 43),
(114, 1, 183, 44),
(115, 1, 184, 45),
(116, 1, 187, 46),
(117, 1, 189, 47),
(118, 1, 191, 48),
(119, 1, 192, 49),
(120, 1, 193, 50),
(121, 9, 46, 1),
(122, 9, 47, 2),
(123, 9, 48, 3),
(124, 9, 49, 4),
(125, 9, 50, 5),
(126, 9, 51, 6),
(127, 9, 52, 7),
(128, 9, 55, 8),
(129, 8, 46, 1),
(130, 8, 47, 2),
(131, 8, 48, 3),
(132, 8, 49, 4),
(133, 8, 50, 5),
(134, 8, 51, 6),
(135, 8, 52, 7),
(136, 8, 55, 8),
(143, 3, 290, 1),
(144, 3, 291, 2),
(145, 3, 292, 3),
(146, 3, 293, 4),
(147, 3, 294, 5),
(148, 3, 295, 6),
(149, 3, 296, 7);

-- --------------------------------------------------------

--
-- Structure de la table `traitements_image`
--

CREATE TABLE `traitements_image` (
  `id` int(11) NOT NULL,
  `code` varchar(30) NOT NULL COMMENT 'clé JS : normal, grayscale, color...',
  `libelle` varchar(60) NOT NULL COMMENT 'Libellé affiché',
  `actif` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `traitements_image`
--

INSERT INTO `traitements_image` (`id`, `code`, `libelle`, `actif`) VALUES
(1, 'normal', 'Normal', 1),
(2, 'grayscale', 'Noir et Blanc', 1),
(3, 'color', 'Couleur+', 1),
(4, 'hp', 'Haute Pénétration', 1),
(5, 'organic', 'Mat. Organique', 1),
(6, 'inorganic', 'Mat. Inorganique', 1),
(7, 'contour', 'Renforcement Contours', 1);

-- --------------------------------------------------------

--
-- Structure de la table `type_examen`
--

CREATE TABLE `type_examen` (
  `idtype_examen` int(11) NOT NULL,
  `code` varchar(20) NOT NULL COMMENT 'AS, IF, INST, SENS, FORM',
  `nom_fr` varchar(100) NOT NULL,
  `nom_en` varchar(100) NOT NULL,
  `description_fr` text DEFAULT NULL,
  `duree_minutes` int(11) NOT NULL DEFAULT 90,
  `seuil_reussite` decimal(5,2) NOT NULL DEFAULT 80.00,
  `nb_questions_theorique` int(11) DEFAULT 0,
  `nb_questions_pratique` int(11) DEFAULT 0,
  `a_deux_parties` tinyint(1) DEFAULT 0,
  `actif` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `type_examen`
--

INSERT INTO `type_examen` (`idtype_examen`, `code`, `nom_fr`, `nom_en`, `description_fr`, `duree_minutes`, `seuil_reussite`, `nb_questions_theorique`, `nb_questions_pratique`, `a_deux_parties`, `actif`) VALUES
(1, 'AS', 'Agent de Sûreté', 'Security Agent', 'Certification Agent de Sûreté — épreuve théorique QCM 50 questions', 90, 80.00, 50, 0, 0, 1),
(2, 'IF', 'Agent Inspection Filtrage', 'Screening Agent', 'Certification IF — théorie QCM 50q + pratique images 50q (2×1h)', 120, 80.00, 50, 50, 1, 1),
(3, 'INST', 'Instructeur', 'Instructor', 'Certification Instructeur — épreuve théorique QCM 50 questions', 90, 80.00, 50, 0, 0, 1),
(4, 'SENS', 'Sensibilisation Sûreté', 'Security Awareness', 'Sensibilisation à la sûreté — épreuve QCM 20 questions', 60, 70.00, 20, 0, 0, 1),
(5, 'FORM', 'Évaluation Formation', 'Training Evaluation', 'Évaluation par modules (modules 2,3,4,6,8,9,11)', 90, 70.00, 0, 0, 0, 1);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `v_candidats_types`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `v_candidats_types` (
`idstagiaire` int(11)
,`nomstagiaire` varchar(40)
,`prenomstagiaire` varchar(50)
,`statut` varchar(50)
,`code_type` varchar(4)
,`idtype_examen` int(1)
);

-- --------------------------------------------------------

--
-- Structure de la vue `v_candidats_types`
--
DROP TABLE IF EXISTS `v_candidats_types`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_candidats_types`  AS SELECT `s`.`idstagiaire` AS `idstagiaire`, `s`.`nomstagiaire` AS `nomstagiaire`, `s`.`prenomstagiaire` AS `prenomstagiaire`, `ff`.`statut` AS `statut`, CASE WHEN `ff`.`statut` = 'Contrôle d\'Acces' THEN 'AS' WHEN `ff`.`statut` = 'Inspection Filtrage' THEN 'IF' WHEN `ff`.`statut` = 'Formation' THEN 'FORM' WHEN `ff`.`statut` = 'Sensibilisation' THEN 'SENS' ELSE 'INST' END AS `code_type`, CASE WHEN `ff`.`statut` = 'Contrôle d\'Acces' THEN 1 WHEN `ff`.`statut` = 'Inspection Filtrage' THEN 2 WHEN `ff`.`statut` = 'Formation' THEN 5 WHEN `ff`.`statut` = 'Sensibilisation' THEN 4 ELSE 3 END AS `idtype_examen` FROM (`si_anac`.`stagiaire` `s` join `si_anac`.`faire_formation` `ff` on(`ff`.`idstagiaire` = `s`.`idstagiaire`)) WHERE `ff`.`statut` <> 'Maintien competences' ;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `administrateurs`
--
ALTER TABLE `administrateurs`
  ADD PRIMARY KEY (`idadmin`),
  ADD UNIQUE KEY `code_acces` (`code_acces`);

--
-- Index pour la table `admin_permissions`
--
ALTER TABLE `admin_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_perm` (`idadmin`,`module`),
  ADD KEY `fk_perm_admin` (`idadmin`);

--
-- Index pour la table `candidat`
--
ALTER TABLE `candidat`
  ADD PRIMARY KEY (`idcandidat`),
  ADD UNIQUE KEY `uq_code_acces` (`code_acces`),
  ADD KEY `idx_stagiaire` (`idstagiaire`);

--
-- Index pour la table `candidat_session`
--
ALTER TABLE `candidat_session`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cand_sess` (`idcandidat`,`id_session`),
  ADD KEY `fk_cs_cand` (`idcandidat`),
  ADD KEY `fk_cs_sess` (`id_session`);

--
-- Index pour la table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_eval` (`idcandidat`);

--
-- Index pour la table `evaluation_module`
--
ALTER TABLE `evaluation_module`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_em_cand` (`idcandidat`),
  ADD KEY `fk_em_module` (`idmodule`),
  ADD KEY `fk_em_sess` (`id_session`);

--
-- Index pour la table `module_formation`
--
ALTER TABLE `module_formation`
  ADD PRIMARY KEY (`idmodule`),
  ADD KEY `idx_typeform` (`idtypeformation`);

--
-- Index pour la table `progression_candidat`
--
ALTER TABLE `progression_candidat`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_prog` (`idcandidat`,`id_session`),
  ADD KEY `fk_prog_sess` (`id_session`);

--
-- Index pour la table `question`
--
ALTER TABLE `question`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`idtype_examen`,`type_question`);

--
-- Index pour la table `reponses_candidat`
--
ALTER TABLE `reponses_candidat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_rep_cand` (`idcandidat`),
  ADD KEY `fk_rep_sess` (`id_session`),
  ADD KEY `fk_rep_q` (`question_id`);

--
-- Index pour la table `resultats`
--
ALTER TABLE `resultats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_res_cand` (`idcandidat`),
  ADD KEY `fk_res_sess` (`id_session`),
  ADD KEY `fk_res_type` (`idtype_examen`);

--
-- Index pour la table `session_examen`
--
ALTER TABLE `session_examen`
  ADD PRIMARY KEY (`id_session`),
  ADD KEY `idtype_examen` (`idtype_examen`);

--
-- Index pour la table `session_modules`
--
ALTER TABLE `session_modules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_session_module` (`id_session`,`idmodule`),
  ADD KEY `fk_sm_sess` (`id_session`),
  ADD KEY `fk_sm_module` (`idmodule`);

--
-- Index pour la table `session_questions`
--
ALTER TABLE `session_questions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sq` (`session_id`,`question_id`),
  ADD KEY `fk_sq_q` (`question_id`);

--
-- Index pour la table `traitements_image`
--
ALTER TABLE `traitements_image`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_code` (`code`);

--
-- Index pour la table `type_examen`
--
ALTER TABLE `type_examen`
  ADD PRIMARY KEY (`idtype_examen`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `administrateurs`
--
ALTER TABLE `administrateurs`
  MODIFY `idadmin` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `admin_permissions`
--
ALTER TABLE `admin_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT pour la table `candidat`
--
ALTER TABLE `candidat`
  MODIFY `idcandidat` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT pour la table `candidat_session`
--
ALTER TABLE `candidat_session`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT pour la table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `evaluation_module`
--
ALTER TABLE `evaluation_module`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `module_formation`
--
ALTER TABLE `module_formation`
  MODIFY `idmodule` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT pour la table `progression_candidat`
--
ALTER TABLE `progression_candidat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `question`
--
ALTER TABLE `question`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=297;

--
-- AUTO_INCREMENT pour la table `reponses_candidat`
--
ALTER TABLE `reponses_candidat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=168;

--
-- AUTO_INCREMENT pour la table `resultats`
--
ALTER TABLE `resultats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `session_examen`
--
ALTER TABLE `session_examen`
  MODIFY `id_session` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `session_modules`
--
ALTER TABLE `session_modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `session_questions`
--
ALTER TABLE `session_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=150;

--
-- AUTO_INCREMENT pour la table `traitements_image`
--
ALTER TABLE `traitements_image`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `type_examen`
--
ALTER TABLE `type_examen`
  MODIFY `idtype_examen` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `candidat_session`
--
ALTER TABLE `candidat_session`
  ADD CONSTRAINT `fk_cs_cand` FOREIGN KEY (`idcandidat`) REFERENCES `candidat` (`idcandidat`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cs_sess` FOREIGN KEY (`id_session`) REFERENCES `session_examen` (`id_session`) ON DELETE CASCADE;

--
-- Contraintes pour la table `evaluations`
--
ALTER TABLE `evaluations`
  ADD CONSTRAINT `fk_eval_cand` FOREIGN KEY (`idcandidat`) REFERENCES `candidat` (`idcandidat`);

--
-- Contraintes pour la table `evaluation_module`
--
ALTER TABLE `evaluation_module`
  ADD CONSTRAINT `fk_em_cand` FOREIGN KEY (`idcandidat`) REFERENCES `candidat` (`idcandidat`),
  ADD CONSTRAINT `fk_em_module` FOREIGN KEY (`idmodule`) REFERENCES `module_formation` (`idmodule`),
  ADD CONSTRAINT `fk_em_sess` FOREIGN KEY (`id_session`) REFERENCES `session_examen` (`id_session`);

--
-- Contraintes pour la table `progression_candidat`
--
ALTER TABLE `progression_candidat`
  ADD CONSTRAINT `fk_prog_cand` FOREIGN KEY (`idcandidat`) REFERENCES `candidat` (`idcandidat`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_prog_sess` FOREIGN KEY (`id_session`) REFERENCES `session_examen` (`id_session`) ON DELETE CASCADE;

--
-- Contraintes pour la table `question`
--
ALTER TABLE `question`
  ADD CONSTRAINT `fk_q_type` FOREIGN KEY (`idtype_examen`) REFERENCES `type_examen` (`idtype_examen`);

--
-- Contraintes pour la table `reponses_candidat`
--
ALTER TABLE `reponses_candidat`
  ADD CONSTRAINT `fk_rep_cand` FOREIGN KEY (`idcandidat`) REFERENCES `candidat` (`idcandidat`),
  ADD CONSTRAINT `fk_rep_q` FOREIGN KEY (`question_id`) REFERENCES `question` (`id`),
  ADD CONSTRAINT `fk_rep_sess` FOREIGN KEY (`id_session`) REFERENCES `session_examen` (`id_session`);

--
-- Contraintes pour la table `resultats`
--
ALTER TABLE `resultats`
  ADD CONSTRAINT `fk_res_cand` FOREIGN KEY (`idcandidat`) REFERENCES `candidat` (`idcandidat`),
  ADD CONSTRAINT `fk_res_sess` FOREIGN KEY (`id_session`) REFERENCES `session_examen` (`id_session`),
  ADD CONSTRAINT `fk_res_type` FOREIGN KEY (`idtype_examen`) REFERENCES `type_examen` (`idtype_examen`);

--
-- Contraintes pour la table `session_examen`
--
ALTER TABLE `session_examen`
  ADD CONSTRAINT `fk_se_type` FOREIGN KEY (`idtype_examen`) REFERENCES `type_examen` (`idtype_examen`);

--
-- Contraintes pour la table `session_modules`
--
ALTER TABLE `session_modules`
  ADD CONSTRAINT `fk_sm_module` FOREIGN KEY (`idmodule`) REFERENCES `module_formation` (`idmodule`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sm_sess` FOREIGN KEY (`id_session`) REFERENCES `session_examen` (`id_session`) ON DELETE CASCADE;

--
-- Contraintes pour la table `session_questions`
--
ALTER TABLE `session_questions`
  ADD CONSTRAINT `fk_sq_q` FOREIGN KEY (`question_id`) REFERENCES `question` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sq_s` FOREIGN KEY (`session_id`) REFERENCES `session_examen` (`id_session`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

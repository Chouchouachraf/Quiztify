-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : lun. 10 mars 2025 à 09:07
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
-- Base de données : `quiztifydatabase`
--

-- --------------------------------------------------------

--
-- Structure de la table `classrooms`
--

CREATE TABLE `classrooms` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Déchargement des données de la table `classrooms`
--

INSERT INTO `classrooms` (`id`, `teacher_id`, `name`, `department`, `description`, `created_at`) VALUES
(1, 2, 'DD201', 'DEVELOPEMENT', '', '2025-02-13 15:21:46'),
(2, 7, 'Finance', 'ge', '2 eme annee', '2025-02-19 16:57:59');

-- --------------------------------------------------------

--
-- Structure de la table `classroom_students`
--

CREATE TABLE `classroom_students` (
  `id` int(11) NOT NULL,
  `classroom_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Déchargement des données de la table `classroom_students`
--

INSERT INTO `classroom_students` (`id`, `classroom_id`, `student_id`, `joined_at`) VALUES
(1, 1, 1, '2025-02-13 15:21:52'),
(2, 2, 3, '2025-02-19 16:58:09'),
(3, 1, 3, '2025-03-10 07:18:50');

-- --------------------------------------------------------

--
-- Structure de la table `exams`
--

CREATE TABLE `exams` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_points` enum('10','20','40','100') NOT NULL DEFAULT '10',
  `attempts_allowed` int(11) NOT NULL DEFAULT 1,
  `passing_score` int(11) NOT NULL DEFAULT 60,
  `has_timer` tinyint(1) DEFAULT 0,
  `duration_minutes` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Déchargement des données de la table `exams`
--

INSERT INTO `exams` (`id`, `title`, `description`, `is_published`, `start_date`, `end_date`, `created_by`, `created_at`, `total_points`, `attempts_allowed`, `passing_score`, `has_timer`, `duration_minutes`) VALUES
(61, 'test', 'poiuyt', 1, '2025-03-10 12:00:00', '2025-03-10 13:00:00', 7, '2025-03-10 06:23:46', '20', 1, 60, 1, 60),
(62, 'L7WA', 'AAA', 1, '2025-03-10 12:00:00', '2025-03-10 13:00:00', 7, '2025-03-10 06:28:09', '20', 1, 60, 1, 60);

-- --------------------------------------------------------

--
-- Structure de la table `exam_attempts`
--

CREATE TABLE `exam_attempts` (
  `id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `score` decimal(5,2) DEFAULT NULL,
  `graded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `published` tinyint(1) NOT NULL DEFAULT 0,
  `teacher_feedback` text DEFAULT NULL,
  `graded_at` timestamp NULL DEFAULT NULL,
  `violations` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Déchargement des données de la table `exam_attempts`
--

INSERT INTO `exam_attempts` (`id`, `exam_id`, `student_id`, `start_time`, `end_time`, `is_completed`, `score`, `graded_by`, `created_at`, `published`, `teacher_feedback`, `graded_at`, `violations`) VALUES
(50, 61, 3, '2025-03-10 06:24:03', NULL, 0, NULL, NULL, '2025-03-10 06:24:03', 0, NULL, NULL, 0),
(51, 62, 3, '2025-03-10 06:28:23', NULL, 0, NULL, NULL, '2025-03-10 06:28:23', 0, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Structure de la table `exam_cheating_logs`
--

CREATE TABLE `exam_cheating_logs` (
  `id` int(11) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `cheating_type` varchar(50) NOT NULL,
  `detected_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `exam_classrooms`
--

CREATE TABLE `exam_classrooms` (
  `id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `classroom_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `exam_classrooms`
--

INSERT INTO `exam_classrooms` (`id`, `exam_id`, `classroom_id`, `created_at`) VALUES
(52, 61, 2, '2025-03-10 06:23:46'),
(53, 62, 2, '2025-03-10 06:28:09');

-- --------------------------------------------------------

--
-- Structure de la table `exam_teachers`
--

CREATE TABLE `exam_teachers` (
  `id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `grades`
--

CREATE TABLE `grades` (
  `id` int(11) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `total_score` decimal(5,2) NOT NULL,
  `comments` text DEFAULT NULL,
  `graded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `mcq_options`
--

CREATE TABLE `mcq_options` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `option_text` text NOT NULL,
  `option_type` enum('text','true_false') NOT NULL DEFAULT 'text',
  `true_false_value` enum('true','false') DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Déchargement des données de la table `mcq_options`
--

INSERT INTO `mcq_options` (`id`, `question_id`, `option_text`, `option_type`, `true_false_value`, `is_correct`) VALUES
(60, 80, 'op1', 'text', NULL, 0),
(61, 80, 'op2', 'text', NULL, 1),
(62, 83, 'HHH1', 'text', NULL, 0),
(63, 83, 'HHH2', 'text', NULL, 1);

-- --------------------------------------------------------

--
-- Structure de la table `questions`
--

CREATE TABLE `questions` (
  `id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('mcq','open','true_false') NOT NULL,
  `points` decimal(5,2) NOT NULL DEFAULT 1.00,
  `order_num` int(11) NOT NULL DEFAULT 1,
  `question_order` int(11) DEFAULT 0,
  `correct_answer` varchar(255) DEFAULT NULL,
  `question_image` varchar(255) DEFAULT NULL,
  `code_language` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Déchargement des données de la table `questions`
--

INSERT INTO `questions` (`id`, `exam_id`, `question_text`, `question_type`, `points`, `order_num`, `question_order`, `correct_answer`, `question_image`, `code_language`) VALUES
(79, 61, 'Q1', 'true_false', 10.00, 1, 0, 'true', NULL, NULL),
(80, 61, 'q2', 'mcq', 10.00, 2, 0, NULL, NULL, NULL),
(81, 62, 'SOAL ', 'open', 10.00, 1, 0, NULL, NULL, NULL),
(82, 62, 'S7I7 WLA GHALT', 'true_false', 5.00, 2, 0, 'true', NULL, NULL),
(83, 62, 'BZAAF', 'mcq', 5.00, 3, 0, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Déchargement des données de la table `remember_tokens`
--

INSERT INTO `remember_tokens` (`id`, `user_id`, `token`, `created_at`, `expires_at`) VALUES
(4, 2, 'd1dfb6fb45f50f58a16e15f6d742c9f4586436159655fee14db12f1df3fd53ea', '2025-02-12 17:09:33', '2025-03-14 18:09:33'),
(5, 2, '0807413bfe17d5dbd9b58b98f07c649714878d191cbd08f454dab4c0d6a6fa08', '2025-02-13 13:58:27', '2025-03-15 14:58:27'),
(6, 1, '651814c6e691b48000a24585fe369692cea1cc2579a3b3301530ad79bbaee0c4', '2025-02-13 13:59:10', '2025-03-15 14:59:10'),
(7, 1, '5ba210b28c9a3fff1c7ff292509dddf835653213c86ed790d47e0590d1e04dec', '2025-02-13 14:06:25', '2025-03-15 15:06:25'),
(8, 2, 'd4b97c2dc90ec3795b8e75f519dc32aa0dc0a3cf86325b517217d9f6b056369a', '2025-02-13 14:06:59', '2025-03-15 15:06:59'),
(9, 1, '4b409ff16f532ee483478fab9e8be035efe039befabd5d41fae8586597768070', '2025-02-13 14:08:47', '2025-03-15 15:08:47'),
(10, 1, '7a1c8ef8fba2289c335dbcbba4b00c4f5f192e9e72d345d802fe3e960391d102', '2025-02-13 15:15:58', '2025-03-15 16:15:58'),
(11, 1, '3899f22aeda968e6a48c6359e21a8bfb0030f163e638d72d78a1af74081453dc', '2025-02-13 16:10:20', '2025-03-15 17:10:20'),
(12, 2, '2c5e389b7019fc2e722ba808efc1dce4266361236561d17296c82cc1c8ea4756', '2025-02-13 16:10:33', '2025-03-15 17:10:33'),
(13, 1, '0dfb0f19973592e3f39fd2202b5fc9cbd836ce093c449180b95542aef473e603', '2025-02-13 16:13:09', '2025-03-15 17:13:09'),
(14, 1, '17106f580c3039fb6611e8392039b0f5a6cece9d7db36b474f82bcf7062435ac', '2025-02-13 16:19:15', '2025-03-15 17:19:15'),
(15, 2, '6f535ae715340227e2151605c42365281159d9eefbce90ec46822246fa8df5c9', '2025-02-13 16:21:32', '2025-03-15 17:21:32'),
(16, 1, 'b158b1ae25ce5b14c30fb5ccd78a7ebd9a3e8fa32d6a8873825f5d81944b2860', '2025-02-13 16:23:25', '2025-03-15 17:23:25'),
(17, 2, 'c4ca89442920040669bc1c856f972a298d38f83f1d1a5afeccca71bc1948331f', '2025-02-13 16:24:14', '2025-03-15 17:24:14'),
(18, 1, '80c8fd610764181b9b2ccdc3964db83968bb61476fb79818f4ba9673488f9470', '2025-02-13 16:24:58', '2025-03-15 17:24:58'),
(19, 2, '3790f38694d9f88418f0e78069942dce38ea150c2ecd38cff2aed0bc95b1c05c', '2025-02-13 16:27:35', '2025-03-15 17:27:35'),
(20, 1, 'f0e4fccba790a8bc739f57399002fa5a8128497e4672dc7d84fe66acdb5916bf', '2025-02-16 20:54:33', '2025-03-18 21:54:33'),
(21, 2, '34a0745b241c171fc7a130a2703194b22ae0d875eb93e280dedcf9a8bb01cf98', '2025-02-16 20:55:02', '2025-03-18 21:55:02'),
(22, 1, '9316ac57ba3448de945dba906915d9ecbfa5184ed6921826f75a4ba49b25caa8', '2025-02-16 21:00:04', '2025-03-18 22:00:04'),
(23, 2, '23fc180a42ae883d4eb44949a03d856572548e87a38d921c1369ca56c46b57e9', '2025-02-16 21:00:34', '2025-03-18 22:00:34'),
(24, 1, '0d6a8edef7ff5a01a17ecf8c6876081665070e043a82ececf19a091533e3669d', '2025-02-16 21:48:25', '2025-03-18 22:48:25'),
(25, 2, '6300b3e345a8b366f908cc7a5a64f01033d106ce3cf15bab0d3c3bfc33011996', '2025-02-16 21:48:43', '2025-03-18 22:48:43'),
(26, 1, '672a4e3053c327571b1c9ce6c5c1613899cc649ee5e901e49bef37a4345faab0', '2025-02-16 21:50:42', '2025-03-18 22:50:42'),
(27, 2, '598a0194190d15bed569ccaa3c4676520d9c980929d4590aeb728a90f0ce49f7', '2025-02-16 21:51:11', '2025-03-18 22:51:11'),
(28, 1, '298ada66f07eb8b910967e98d3215371563c79b9cdb666ea27e2acee8a374b55', '2025-02-16 21:52:10', '2025-03-18 22:52:10'),
(29, 2, '6e69180338c49ac8165a261ae1c6aa0cb5be78978d8f6cd2e32e14744da4bd29', '2025-02-16 21:52:57', '2025-03-18 22:52:57'),
(30, 1, 'a377fd4c44196f92726caf6594f79802ee78cd4651109795eba49c02dc36d266', '2025-02-16 21:53:38', '2025-03-18 22:53:38'),
(31, 3, 'cd0efc8b5ebacd3fb0647d1197fb15ee735d91506d0cf5ed2090c7f5817dab56', '2025-02-20 15:58:41', '2025-03-22 16:58:41'),
(32, 7, 'd753ca087864765fa46e5b1a070e328804b361851de4107edd77d5593380c038', '2025-02-20 16:39:23', '2025-03-22 17:39:23'),
(33, 7, 'a526e6b840149519a70ee224c28a782c52a2f9738db79d8d562f4551fbb56d3b', '2025-02-23 16:15:19', '2025-03-25 16:15:19'),
(34, 2, '1de832088e15c075fcfeddb51885e18ecd629e0cdd11bc800a9762bd66d3c539', '2025-02-23 17:31:46', '2025-03-25 17:31:46'),
(35, 2, '80ec2f55fd37bd04d989723819ce625b59d2cca5f5403a7c7e35c78211f4d4b1', '2025-02-23 19:01:04', '2025-03-25 19:01:04'),
(36, 1, 'fe59cd828cb377b2d75e8c68fbab9b65211dc052be9140bbee9fff50be7ff429', '2025-03-09 19:43:01', '2025-04-08 18:43:01');

-- --------------------------------------------------------

--
-- Structure de la table `student_answers`
--

CREATE TABLE `student_answers` (
  `id` int(11) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `answer_type` enum('mcq','true_false','open','code') NOT NULL,
  `answer_text` text DEFAULT NULL,
  `selected_option_id` int(11) DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `points_earned` decimal(5,2) DEFAULT NULL,
  `manual_grade` decimal(5,2) DEFAULT NULL,
  `teacher_comment` text DEFAULT NULL,
  `graded_by` int(11) DEFAULT NULL,
  `graded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','teacher','student') NOT NULL DEFAULT 'student',
  `department` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `full_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `classroom_id` int(11) DEFAULT NULL,
  `is_approved` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `department`, `status`, `full_name`, `created_at`, `last_login`, `classroom_id`, `is_approved`) VALUES
(1, 'achraf.gzl', '$2y$10$bfEmxE.1LXW.5y8uCTMXg.4a8Rohz3QxiBdUtsXqxf2SsV3M7c3ba', 'achraf@email.com', 'student', NULL, 'active', 'Achraf Ghazal', '2025-02-11 23:51:22', '2025-02-16 21:53:38', NULL, 0),
(2, 'hayanisam', '$2y$10$MAU70g3lfjsXD.jPnuGytevL/FOpz8ufXLd8k5OR53OPwlei8ZsZS', 'isam@email.com', 'teacher', NULL, 'active', 'isam', '2025-02-11 23:53:50', '2025-02-16 21:55:57', NULL, 0),
(3, 'ghitouuu___', '$2y$10$8nggNKcqMI0EwsOmvXcP.OBO6WtFsVXv40UMud87WdiMKKmL9qifa', 'ghita@example.com', 'student', NULL, 'active', 'Ghita Khaia', '2025-02-19 07:45:15', NULL, NULL, 0),
(4, 'admin', '$2y$10$bfEmxE.1LXW.5y8uCTMXg.4a8Rohz3QxiBdUtsXqxf2SsV3M7c3ba', 'admin@quiztify.com', 'admin', NULL, 'active', 'System Administrator', '2025-02-19 15:50:00', NULL, NULL, 0),
(5, 'tacheroune', '$2y$10$ayNHTZgKfqte8DaqEdxj0ucAV.1ZFsd5HPGtjU054wy04uMryqZq6', 'tach@email.com', 'student', 'DEVELOPEMENT digital', 'active', 'simo tacheroune', '2025-02-19 16:20:00', NULL, NULL, 0),
(6, 'dino', '$2y$10$6z3eWxn1D8YkSffVsQyFl.ytbHa4vn1Mr.l6WfsSwshSAskr5ZLbe', 'dino@email.com', 'student', 'DEVELOPEMENT digital', 'active', 'Anas lbaz', '2025-02-19 16:54:37', NULL, NULL, 0),
(7, 'bousfiha', '$2y$10$8Q63CJe3TWt.ENRx0iCq9.ZPLGwKCvJjecZt4wNFDkLlhwV/QuuG2', 'bousfiha@email.com', 'teacher', 'GE', 'active', 'bsfh', '2025-02-19 16:55:39', NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Structure de la table `user_activity_logs`
--

CREATE TABLE `user_activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `activity_type` enum('login','logout','failed_login') NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Déchargement des données de la table `user_activity_logs`
--

INSERT INTO `user_activity_logs` (`id`, `user_id`, `activity_type`, `ip_address`, `user_agent`, `created_at`) VALUES
(2, 1, 'logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-11 23:51:56'),
(3, 2, 'logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-11 23:55:00'),
(4, 1, 'logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-11 23:55:57'),
(5, 2, 'logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-12 00:00:55'),
(6, 1, 'logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-12 00:04:20'),
(7, 2, 'logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-12 15:49:14'),
(8, 1, 'logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-12 15:56:18'),
(9, 2, 'logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-12 16:46:33'),
(10, 1, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-13 16:10:20'),
(11, 2, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-13 16:10:33'),
(12, 1, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-13 16:13:09'),
(13, 1, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-13 16:19:15'),
(14, 2, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-13 16:21:32'),
(15, 1, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-13 16:23:25'),
(16, 2, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-13 16:24:14'),
(17, 1, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-13 16:24:58'),
(18, 2, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-13 16:27:35'),
(19, 1, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-14 16:03:55'),
(20, 1, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-16 20:54:33'),
(21, 2, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-16 20:55:02'),
(22, 1, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-16 21:00:04'),
(23, 2, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-16 21:00:34'),
(24, 1, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-16 21:02:10'),
(25, 2, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-16 21:02:44'),
(26, 1, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-16 21:04:15'),
(27, 2, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-16 21:14:44'),
(28, 2, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-16 21:15:27'),
(29, 1, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-16 21:16:25'),
(30, 2, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-16 21:35:29'),
(31, 1, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-16 21:48:25'),
(32, 2, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-16 21:48:43'),
(33, 1, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-16 21:50:42'),
(34, 2, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-16 21:51:11'),
(35, 1, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-16 21:52:10'),
(36, 2, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-16 21:52:57'),
(37, 1, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-16 21:53:38'),
(38, 2, 'login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', '2025-02-16 21:55:57');

-- --------------------------------------------------------

--
-- Structure de la table `user_preferences`
--

CREATE TABLE `user_preferences` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email_notifications` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Déchargement des données de la table `user_preferences`
--

INSERT INTO `user_preferences` (`id`, `user_id`, `email_notifications`, `created_at`, `updated_at`) VALUES
(1, 2, 1, '2025-02-13 14:51:46', '2025-02-13 14:51:46');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `classrooms`
--
ALTER TABLE `classrooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Index pour la table `classroom_students`
--
ALTER TABLE `classroom_students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `classroom_id` (`classroom_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Index pour la table `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_exam_published` (`is_published`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_start_end_date` (`start_date`,`end_date`);

--
-- Index pour la table `exam_attempts`
--
ALTER TABLE `exam_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attempt_completion` (`is_completed`),
  ADD KEY `idx_student_exam` (`student_id`,`exam_id`),
  ADD KEY `idx_completion_time` (`end_time`),
  ADD KEY `exam_attempts_ibfk_1` (`exam_id`);

--
-- Index pour la table `exam_cheating_logs`
--
ALTER TABLE `exam_cheating_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attempt_id` (`attempt_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Index pour la table `exam_classrooms`
--
ALTER TABLE `exam_classrooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `exam_id` (`exam_id`),
  ADD KEY `classroom_id` (`classroom_id`);

--
-- Index pour la table `exam_teachers`
--
ALTER TABLE `exam_teachers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `exam_id` (`exam_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Index pour la table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attempt_id` (`attempt_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Index pour la table `mcq_options`
--
ALTER TABLE `mcq_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_question_correct` (`question_id`,`is_correct`),
  ADD KEY `idx_question_id` (`question_id`);

--
-- Index pour la table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_question_type` (`question_type`),
  ADD KEY `idx_exam_id` (`exam_id`);

--
-- Index pour la table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_token` (`token`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Index pour la table `student_answers`
--
ALTER TABLE `student_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_student_answers_mcq_options` (`selected_option_id`),
  ADD KEY `fk_student_answers_graded_by` (`graded_by`),
  ADD KEY `idx_student_answers_attempt` (`attempt_id`),
  ADD KEY `idx_student_answers_question` (`question_id`),
  ADD KEY `idx_student_answers_student` (`student_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_user_classroom` (`classroom_id`);

--
-- Index pour la table `user_activity_logs`
--
ALTER TABLE `user_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `classrooms`
--
ALTER TABLE `classrooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `classroom_students`
--
ALTER TABLE `classroom_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `exams`
--
ALTER TABLE `exams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT pour la table `exam_attempts`
--
ALTER TABLE `exam_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT pour la table `exam_cheating_logs`
--
ALTER TABLE `exam_cheating_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT pour la table `exam_classrooms`
--
ALTER TABLE `exam_classrooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT pour la table `exam_teachers`
--
ALTER TABLE `exam_teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `mcq_options`
--
ALTER TABLE `mcq_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT pour la table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT pour la table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT pour la table `student_answers`
--
ALTER TABLE `student_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `user_activity_logs`
--
ALTER TABLE `user_activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT pour la table `user_preferences`
--
ALTER TABLE `user_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `classrooms`
--
ALTER TABLE `classrooms`
  ADD CONSTRAINT `classrooms_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `classroom_students`
--
ALTER TABLE `classroom_students`
  ADD CONSTRAINT `classroom_students_ibfk_1` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`),
  ADD CONSTRAINT `classroom_students_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `exams`
--
ALTER TABLE `exams`
  ADD CONSTRAINT `exams_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `exam_attempts`
--
ALTER TABLE `exam_attempts`
  ADD CONSTRAINT `exam_attempts_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_attempts_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `exam_cheating_logs`
--
ALTER TABLE `exam_cheating_logs`
  ADD CONSTRAINT `exam_cheating_logs_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`),
  ADD CONSTRAINT `exam_cheating_logs_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `exam_classrooms`
--
ALTER TABLE `exam_classrooms`
  ADD CONSTRAINT `exam_classrooms_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_classrooms_ibfk_2` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `exam_teachers`
--
ALTER TABLE `exam_teachers`
  ADD CONSTRAINT `exam_teachers_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_teachers_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `mcq_options`
--
ALTER TABLE `mcq_options`
  ADD CONSTRAINT `mcq_options_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`);

--
-- Contraintes pour la table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`);

--
-- Contraintes pour la table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD CONSTRAINT `remember_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `student_answers`
--
ALTER TABLE `student_answers`
  ADD CONSTRAINT `fk_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_student_answers_graded_by` FOREIGN KEY (`graded_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_student_answers_mcq_options` FOREIGN KEY (`selected_option_id`) REFERENCES `mcq_options` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_student_answers_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`),
  ADD CONSTRAINT `student_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  ADD CONSTRAINT `student_answers_ibfk_3` FOREIGN KEY (`selected_option_id`) REFERENCES `mcq_options` (`id`),
  ADD CONSTRAINT `student_answers_ibfk_4` FOREIGN KEY (`graded_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_classroom` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `user_activity_logs`
--
ALTER TABLE `user_activity_logs`
  ADD CONSTRAINT `user_activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 22, 2026 at 01:29 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `project_management`
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `employee_summary`
-- (See below for the actual view)
--
CREATE TABLE `employee_summary` (
`user_id` int(11)
,`full_name` varchar(101)
,`role` enum('admin','manager','team_leader','employee')
,`department` varchar(100)
,`tasks_completed` bigint(21)
,`tasks_pending` bigint(21)
,`avg_rating` decimal(8,6)
,`total_hours_worked` decimal(26,2)
,`total_bonus` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `leaderboard`
--

CREATE TABLE `leaderboard` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `period_type` enum('daily','weekly','monthly') NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `rank_position` int(11) DEFAULT NULL,
  `score` decimal(10,2) DEFAULT NULL,
  `tasks_completed` int(11) DEFAULT 0,
  `hours_logged` decimal(8,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `type` enum('task_assigned','task_updated','deadline','sos','reminder','performance','system') DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `related_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `type`, `is_read`, `related_id`, `created_at`) VALUES
(2, 4, 'New Task Assigned', 'You have been assigned a new task: a', 'task_assigned', 1, 17, '2026-01-11 11:18:37'),
(3, 4, 'New Task Assigned', 'You have been assigned a new task: a', 'task_assigned', 1, 18, '2026-01-11 11:20:17'),
(4, 4, 'Added to Project', '0', 'system', 1, NULL, '2026-01-12 10:30:50'),
(6, 10, 'Project Update', 'You\'ve been added as Manager to a project', 'system', 0, NULL, '2026-01-12 20:41:52'),
(7, 13, 'Project Update', 'You\'ve been added to a project as team_leader', 'system', 0, NULL, '2026-01-16 18:27:22'),
(8, 5, 'Project Update', 'You\'ve been added to a project as employee', 'system', 0, NULL, '2026-01-16 18:29:02'),
(9, 4, 'New Task Assigned', 'You have been assigned a new task: undefined', 'task_assigned', 1, 24, '2026-01-16 19:08:11'),
(10, 5, 'New Task Assigned', 'You have been assigned a new task: Task 1', 'task_assigned', 0, 27, '2026-01-16 19:18:04'),
(11, 4, 'New Task Assigned', 'You have been assigned a new task: Task 1', 'task_assigned', 1, 28, '2026-01-16 19:25:39'),
(12, 5, 'New Task Assigned', 'You have been assigned a new task: task 4', 'task_assigned', 0, 31, '2026-01-16 19:32:32'),
(13, 14, 'New Task Assigned', 'You have been assigned a new task: Task 1', 'task_assigned', 0, 33, '2026-01-16 19:56:33'),
(14, 14, 'New Task Assigned', 'You have been assigned a new task: Task 2', 'task_assigned', 0, 34, '2026-01-16 19:57:04'),
(15, 6, 'SOS Request', 'Employee needs help with task: Task 1', 'sos', 1, 28, '2026-01-21 15:04:18'),
(16, 4, 'SOS Response Received', 'Team leader has responded to your SOS for task: Task 1', 'sos', 1, 1, '2026-01-21 15:04:58');

-- --------------------------------------------------------

--
-- Table structure for table `parent_task`
--

CREATE TABLE `parent_task` (
  `parent_task_id` int(10) NOT NULL,
  `parent_task_name` varchar(200) NOT NULL,
  `project_id` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parent_task`
--

INSERT INTO `parent_task` (`parent_task_id`, `parent_task_name`, `project_id`) VALUES
(2, 'Design', 1),
(3, 'Developement', 1),
(4, 'Testing', 1),
(5, 'Deployment', 1),
(9, 'PT1', 5),
(12, 'PT1', 7);

-- --------------------------------------------------------

--
-- Table structure for table `performance_ratings`
--

CREATE TABLE `performance_ratings` (
  `rating_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rated_by` int(11) NOT NULL,
  `rating_month` date NOT NULL,
  `timeliness_score` int(11) DEFAULT NULL,
  `quality_score` int(11) DEFAULT NULL,
  `communication_score` int(11) DEFAULT NULL,
  `overall_score` decimal(4,2) DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `bonus_amount` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `performance_ratings`
--

INSERT INTO `performance_ratings` (`rating_id`, `user_id`, `rated_by`, `rating_month`, `timeliness_score`, `quality_score`, `communication_score`, `overall_score`, `comments`, `bonus_amount`, `created_at`) VALUES
(1, 4, 3, '2024-01-01', 4, 5, 4, 4.30, NULL, 500.00, '2025-12-23 06:49:42'),
(2, 5, 3, '2024-01-01', 5, 4, 3, 4.00, NULL, 400.00, '2025-12-23 06:49:42');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `project_id` int(11) NOT NULL,
  `project_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('planning','active','completed','on_hold') DEFAULT 'active',
  `progress_percentage` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `admin_id` int(10) NOT NULL,
  `team_leader_id` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`project_id`, `project_name`, `description`, `manager_id`, `start_date`, `end_date`, `status`, `progress_percentage`, `created_at`, `admin_id`, `team_leader_id`) VALUES
(1, 'Website Redesign', 'Redesign company website with modern UI', 9, '2024-01-01', '2024-06-30', 'active', 0.00, '2025-12-23 06:49:42', 7, 6),
(2, 'Mobile App', 'Develop mobile application for Android/iOS', 2, '2024-02-01', '2024-08-31', 'active', 0.00, '2025-12-23 06:49:42', 0, 0),
(5, 'UCAM', 'aa', 11, '2026-01-13', '2026-01-14', 'active', 0.00, '2026-01-12 13:04:52', 7, 13),
(7, 'ELMS', 'ss', 7, '2026-01-24', '2026-01-16', 'active', 0.00, '2026-01-15 06:45:47', 7, 0);

-- --------------------------------------------------------

--
-- Table structure for table `project_members`
--

CREATE TABLE `project_members` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_in_project` enum('manager','team_leader','employee') NOT NULL DEFAULT 'employee',
  `is_team_leader` tinyint(1) DEFAULT 0,
  `added_by` int(11) NOT NULL,
  `added_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_members`
--

INSERT INTO `project_members` (`id`, `project_id`, `user_id`, `role_in_project`, `is_team_leader`, `added_by`, `added_at`) VALUES
(4, 1, 9, 'manager', 0, 7, '2026-01-11 00:34:44'),
(5, 1, 4, 'employee', 0, 9, '2026-01-12 16:30:50'),
(8, 5, 11, 'manager', 0, 7, '2026-01-12 19:04:52'),
(9, 2, 2, 'manager', 0, 7, '2026-01-12 21:16:57'),
(10, 1, 6, 'team_leader', 1, 7, '2026-01-12 23:32:14'),
(13, 7, 10, 'manager', 0, 7, '2026-01-15 12:45:47'),
(16, 5, 13, 'team_leader', 1, 7, '2026-01-17 00:27:22'),
(17, 1, 5, 'employee', 0, 7, '2026-01-17 00:29:02'),
(18, 5, 14, 'employee', 0, 7, '2026-01-17 00:36:33');

-- --------------------------------------------------------

--
-- Stand-in structure for view `project_progress`
-- (See below for the actual view)
--
CREATE TABLE `project_progress` (
`project_id` int(11)
,`project_name` varchar(200)
,`start_date` date
,`end_date` date
,`progress_percentage` decimal(5,2)
,`total_tasks` bigint(21)
,`completed_tasks` bigint(21)
,`in_progress_tasks` bigint(21)
,`stuck_tasks` bigint(21)
,`project_manager` varchar(101)
);

-- --------------------------------------------------------

--
-- Table structure for table `reminders`
--

CREATE TABLE `reminders` (
  `reminder_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reminder_type` enum('deadline','stuck_task','update_missing') NOT NULL,
  `scheduled_time` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sent_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `message` text DEFAULT NULL,
  `channel` enum('email','telegram','whatsapp') DEFAULT 'email',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `skills`
--

CREATE TABLE `skills` (
  `skill_id` int(11) NOT NULL,
  `skill_name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `skills`
--

INSERT INTO `skills` (`skill_id`, `skill_name`, `category`, `created_at`) VALUES
(1, 'Python', 'Programming', '2025-12-23 06:49:42'),
(2, 'React', 'Frontend', '2025-12-23 06:49:42'),
(3, 'UI/UX Design', 'Design', '2025-12-23 06:49:42'),
(4, 'Project Management', 'Management', '2025-12-23 06:49:42'),
(5, 'Database Design', 'Backend', '2025-12-23 06:49:42'),
(6, 'Mobile Development', 'Programming', '2025-12-23 06:49:42'),
(7, 'JavaScript', 'Programming', '2025-12-23 06:49:42'),
(8, 'HTML/CSS', 'Frontend', '2025-12-23 06:49:42');

-- --------------------------------------------------------

--
-- Stand-in structure for view `sos_alerts_view`
-- (See below for the actual view)
--
CREATE TABLE `sos_alerts_view` (
`sos_id` int(11)
,`task_id` int(11)
,`task_title` varchar(200)
,`raised_by` varchar(101)
,`raised_at` timestamp
,`reason` text
,`sos_status` enum('open','acknowledged','resolved')
,`acknowledged_by` varchar(101)
,`due_date` date
);

-- --------------------------------------------------------

--
-- Table structure for table `sos_signals`
--

CREATE TABLE `sos_signals` (
  `sos_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `raised_by` int(11) NOT NULL,
  `raised_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reason` text DEFAULT NULL,
  `status` enum('open','acknowledged','resolved') DEFAULT 'open',
  `acknowledged_by` int(11) DEFAULT NULL,
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `instruction` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sos_signals`
--

INSERT INTO `sos_signals` (`sos_id`, `task_id`, `raised_by`, `raised_at`, `reason`, `status`, `acknowledged_by`, `acknowledged_at`, `resolved_at`, `instruction`) VALUES
(1, 28, 4, '2026-01-21 15:04:18', 'qqq', 'acknowledged', 6, '2026-01-21 15:04:58', NULL, 'ok');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `task_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `status` enum('todo','in_progress','done','approved','blocked') DEFAULT 'todo',
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `estimated_hours` decimal(6,2) DEFAULT NULL,
  `actual_hours` decimal(6,2) DEFAULT 0.00,
  `start_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `kanban_column` varchar(20) DEFAULT 'todo',
  `is_stuck` tinyint(1) DEFAULT 0,
  `parent_task_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`task_id`, `project_id`, `title`, `description`, `assigned_to`, `assigned_by`, `status`, `priority`, `estimated_hours`, `actual_hours`, `start_date`, `due_date`, `completed_at`, `approved_at`, `kanban_column`, `is_stuck`, `parent_task_id`, `created_at`, `updated_at`) VALUES
(28, 1, 'Task 1', '', 4, 7, 'todo', 'medium', NULL, 0.00, '2026-01-28', '2026-01-29', NULL, NULL, 'todo', 0, 2, '2026-01-16 19:25:39', '2026-01-22 11:49:38'),
(29, 1, 'Task 2', '', 4, 7, 'in_progress', 'medium', NULL, 0.00, '2026-01-29', '2026-01-31', NULL, NULL, 'in_progress', 0, 2, '2026-01-16 19:26:47', '2026-01-22 11:49:41'),
(30, 1, 'Task 3', '', 4, 7, 'in_progress', 'medium', NULL, 0.00, '2026-01-29', '2026-02-02', NULL, NULL, 'in_progress', 0, 3, '2026-01-16 19:28:05', '2026-01-22 11:49:42'),
(31, 1, 'task 4', '', 5, 7, 'in_progress', 'medium', NULL, 0.00, '2026-01-31', '2026-02-04', NULL, NULL, 'in_progress', 0, 4, '2026-01-16 19:32:32', '2026-01-22 11:49:40'),
(33, 5, 'Task 1', '', 14, 7, 'in_progress', 'medium', NULL, 0.00, '2026-01-21', '2026-01-23', NULL, NULL, 'in_progress', 0, 9, '2026-01-16 19:56:33', '2026-01-22 11:13:44'),
(34, 5, 'password hash', 'set password 12 char must, store with bcrypt hash', 14, 7, 'todo', 'medium', NULL, 0.00, '2026-01-22', '2026-01-25', NULL, NULL, 'todo', 0, 9, '2026-01-16 19:57:04', '2026-01-21 17:15:32');

-- --------------------------------------------------------

--
-- Table structure for table `task_dependencies`
--

CREATE TABLE `task_dependencies` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `depends_on_task_id` int(11) NOT NULL,
  `dependency_type` varchar(10) DEFAULT 'fs'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `task_recommendations`
--

CREATE TABLE `task_recommendations` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `match_score` decimal(4,2) DEFAULT NULL,
  `recommended_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_accepted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `task_skills`
--

CREATE TABLE `task_skills` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `skill_id` int(11) NOT NULL,
  `required_level` int(11) DEFAULT 3
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_skills`
--

INSERT INTO `task_skills` (`id`, `task_id`, `skill_id`, `required_level`) VALUES
(12, 28, 3, 3),
(13, 29, 3, 3),
(14, 31, 1, 3),
(16, 33, 1, 3),
(17, 34, 2, 3);

-- --------------------------------------------------------

--
-- Table structure for table `temp_passwords`
--

CREATE TABLE `temp_passwords` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `temp_password` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `used` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `role` enum('admin','manager','team_leader','employee') NOT NULL DEFAULT 'admin',
  `department` varchar(100) DEFAULT NULL,
  `profile_picture` text DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password_hash`, `first_name`, `last_name`, `role`, `department`, `profile_picture`, `phone_number`, `is_active`, `created_at`, `last_login`) VALUES
(1, 'admin1', 'admin@company.com', '$2y$10$1l0.262uqtocZuxvkZj.juNz4JwrtDidnxL9Zutch24kis.Is0Yz.', 'John', 'Snow', 'admin', 'Management', NULL, NULL, 1, '2025-12-23 06:49:42', NULL),
(2, 'manager1', 'manager@company.com', '$2y$10$1l0.262uqtocZuxvkZj.juNz4JwrtDidnxL9Zutch24kis.Is0Yz.', 'Iron', 'Man', 'manager', 'Development', NULL, NULL, 1, '2025-12-23 06:49:42', NULL),
(3, 'teamlead1', 'teamlead@company.com', '$2y$10$1l0.262uqtocZuxvkZj.juNz4JwrtDidnxL9Zutch24kis.Is0Yz.', 'Spider', 'Man', 'team_leader', 'Development', NULL, NULL, 1, '2025-12-23 06:49:42', NULL),
(4, 'emp1', 'employee1@company.com', '$2y$10$1l0.262uqtocZuxvkZj.juNz4JwrtDidnxL9Zutch24kis.Is0Yz.', 'Bat', 'Man', 'employee', 'Development', NULL, NULL, 1, '2025-12-23 06:49:42', '2026-01-22 12:22:07'),
(5, 'emp2', 'employee2@company.com', '$2y$10$1l0.262uqtocZuxvkZj.juNz4JwrtDidnxL9Zutch24kis.Is0Yz.', 'Saturo', 'Gojo', 'employee', 'Design', NULL, NULL, 1, '2025-12-23 06:49:42', NULL),
(6, 'alif', 'a@gmail.com', '$2y$10$1l0.262uqtocZuxvkZj.juNz4JwrtDidnxL9Zutch24kis.Is0Yz.', 'Edward', 'Kenway', 'team_leader', 'alif', NULL, NULL, 1, '2025-12-23 06:56:46', '2026-01-22 12:22:38'),
(7, 'dipa', 'dipa@gmail.com', '$2y$10$1l0.262uqtocZuxvkZj.juNz4JwrtDidnxL9Zutch24kis.Is0Yz.', 'sabrina', 'alim', 'admin', 'Management', NULL, NULL, 1, '2026-01-10 08:34:50', '2026-01-22 12:26:23'),
(9, 'sss', 'freaky.alif@gmail.com', '$2y$10$1l0.262uqtocZuxvkZj.juNz4JwrtDidnxL9Zutch24kis.Is0Yz.', 'Ghost', 'Freak', 'manager', NULL, NULL, NULL, 1, '2026-01-10 18:34:44', '2026-01-22 12:03:31'),
(10, 'aaa', 'b@gmail.com', '$2y$10$1l0.262uqtocZuxvkZj.juNz4JwrtDidnxL9Zutch24kis.Is0Yz.', 'Captain', 'Harlock', 'manager', NULL, NULL, NULL, 1, '2026-01-11 07:07:33', '2026-01-12 10:22:05'),
(11, '165926.alif746', '165926.alif@gmail.com', '$2y$10$1l0.262uqtocZuxvkZj.juNz4JwrtDidnxL9Zutch24kis.Is0Yz.', 'Makima', 'M', 'manager', NULL, NULL, NULL, 1, '2026-01-12 15:16:57', NULL),
(13, '281096.alif418', '281096.alif@gmail.com', '$2y$10$1l0.262uqtocZuxvkZj.juNz4JwrtDidnxL9Zutch24kis.Is0Yz.', 'x', 'x', 'team_leader', NULL, NULL, NULL, 1, '2026-01-15 08:05:10', '2026-01-21 15:42:13'),
(14, 'riamoni27732326', 'riamoni27732@gmail.com', '$2y$10$1l0.262uqtocZuxvkZj.juNz4JwrtDidnxL9Zutch24kis.Is0Yz.', 'ria', 'moni', 'employee', NULL, NULL, NULL, 1, '2026-01-16 18:36:33', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_hierarchy`
--

CREATE TABLE `user_hierarchy` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reports_to` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_hierarchy`
--

INSERT INTO `user_hierarchy` (`id`, `user_id`, `reports_to`) VALUES
(1, 2, 1),
(2, 3, 2),
(3, 4, 3),
(4, 5, 3);

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `permissions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`role_id`, `role_name`, `permissions`) VALUES
(1, 'admin', '[\"manage_users\",\"manage_projects\",\"view_all\"]'),
(2, 'manager', '[\"manage_team\",\"view_reports\",\"assign_tasks\"]'),
(3, 'team_leader', '[\"manage_tasks\",\"view_team\",\"log_work\"]'),
(4, 'employee', '[\"view_tasks\",\"log_work\",\"submit_work\"]');

-- --------------------------------------------------------

--
-- Table structure for table `user_skills`
--

CREATE TABLE `user_skills` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `skill_id` int(11) NOT NULL,
  `proficiency_level` int(11) DEFAULT 3
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_skills`
--

INSERT INTO `user_skills` (`id`, `user_id`, `skill_id`, `proficiency_level`) VALUES
(2, 4, 2, 3),
(3, 4, 7, 4),
(4, 4, 8, 5),
(5, 5, 3, 5),
(6, 5, 2, 2),
(7, 5, 8, 4),
(10, 4, 6, 5);

-- --------------------------------------------------------

--
-- Table structure for table `work_logs`
--

CREATE TABLE `work_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `task_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `log_date` date NOT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `hours_worked` decimal(4,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `work_logs`
--

INSERT INTO `work_logs` (`log_id`, `user_id`, `task_id`, `project_id`, `log_date`, `start_time`, `end_time`, `hours_worked`, `description`, `created_at`) VALUES
(1, 4, 28, 1, '2024-01-15', NULL, NULL, 8.00, 'Completed database setup', '2025-12-23 06:49:42'),
(2, 5, 29, 1, '2024-01-16', NULL, NULL, 6.00, 'Worked on homepage design', '2025-12-23 06:49:42'),
(3, 4, 30, 1, '2024-01-17', NULL, NULL, 4.00, 'Started login system development', '2025-12-23 06:49:42');

-- --------------------------------------------------------

--
-- Structure for view `employee_summary`
--
DROP TABLE IF EXISTS `employee_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `employee_summary`  AS SELECT `u`.`user_id` AS `user_id`, concat(`u`.`first_name`,' ',`u`.`last_name`) AS `full_name`, `u`.`role` AS `role`, `u`.`department` AS `department`, count(distinct case when `t`.`status` = 'done' then `t`.`task_id` end) AS `tasks_completed`, count(distinct case when `t`.`status` in ('todo','in_progress') then `t`.`task_id` end) AS `tasks_pending`, coalesce(avg(`pr`.`overall_score`),0) AS `avg_rating`, coalesce(sum(`wl`.`hours_worked`),0) AS `total_hours_worked`, coalesce(sum(`pr`.`bonus_amount`),0) AS `total_bonus` FROM (((`users` `u` left join `tasks` `t` on(`u`.`user_id` = `t`.`assigned_to`)) left join `performance_ratings` `pr` on(`u`.`user_id` = `pr`.`user_id`)) left join `work_logs` `wl` on(`u`.`user_id` = `wl`.`user_id`)) WHERE `u`.`is_active` = 1 GROUP BY `u`.`user_id` ;

-- --------------------------------------------------------

--
-- Structure for view `project_progress`
--
DROP TABLE IF EXISTS `project_progress`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `project_progress`  AS SELECT `p`.`project_id` AS `project_id`, `p`.`project_name` AS `project_name`, `p`.`start_date` AS `start_date`, `p`.`end_date` AS `end_date`, `p`.`progress_percentage` AS `progress_percentage`, count(`t`.`task_id`) AS `total_tasks`, count(case when `t`.`status` = 'done' then `t`.`task_id` end) AS `completed_tasks`, count(case when `t`.`status` = 'in_progress' then `t`.`task_id` end) AS `in_progress_tasks`, count(case when `t`.`is_stuck` = 1 then `t`.`task_id` end) AS `stuck_tasks`, concat(`u`.`first_name`,' ',`u`.`last_name`) AS `project_manager` FROM ((`projects` `p` left join `tasks` `t` on(`p`.`project_id` = `t`.`project_id`)) left join `users` `u` on(`p`.`manager_id` = `u`.`user_id`)) GROUP BY `p`.`project_id` ;

-- --------------------------------------------------------

--
-- Structure for view `sos_alerts_view`
--
DROP TABLE IF EXISTS `sos_alerts_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `sos_alerts_view`  AS SELECT `s`.`sos_id` AS `sos_id`, `t`.`task_id` AS `task_id`, `t`.`title` AS `task_title`, concat(`u1`.`first_name`,' ',`u1`.`last_name`) AS `raised_by`, `s`.`raised_at` AS `raised_at`, `s`.`reason` AS `reason`, `s`.`status` AS `sos_status`, concat(`u2`.`first_name`,' ',`u2`.`last_name`) AS `acknowledged_by`, `t`.`due_date` AS `due_date` FROM (((`sos_signals` `s` join `tasks` `t` on(`s`.`task_id` = `t`.`task_id`)) join `users` `u1` on(`s`.`raised_by` = `u1`.`user_id`)) left join `users` `u2` on(`s`.`acknowledged_by` = `u2`.`user_id`)) WHERE `s`.`status` = 'open' ORDER BY `s`.`raised_at` DESC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `leaderboard`
--
ALTER TABLE `leaderboard`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_period` (`user_id`,`period_type`,`period_start`),
  ADD KEY `idx_leaderboard_period` (`period_type`,`period_start`),
  ADD KEY `idx_leaderboard_rank` (`rank_position`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `parent_task`
--
ALTER TABLE `parent_task`
  ADD PRIMARY KEY (`parent_task_id`),
  ADD KEY `idx_parent_task_project` (`project_id`);

--
-- Indexes for table `performance_ratings`
--
ALTER TABLE `performance_ratings`
  ADD PRIMARY KEY (`rating_id`),
  ADD KEY `idx_perf_user_month` (`user_id`,`rating_month`),
  ADD KEY `idx_perf_rated_by` (`rated_by`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`project_id`),
  ADD KEY `idx_projects_status` (`status`),
  ADD KEY `idx_projects_manager` (`manager_id`),
  ADD KEY `idx_projects_dates` (`start_date`,`end_date`);

--
-- Indexes for table `project_members`
--
ALTER TABLE `project_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_project_user` (`project_id`,`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `added_by` (`added_by`);

--
-- Indexes for table `reminders`
--
ALTER TABLE `reminders`
  ADD PRIMARY KEY (`reminder_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_reminders_scheduled` (`scheduled_time`),
  ADD KEY `idx_reminders_status` (`status`),
  ADD KEY `idx_reminders_task` (`task_id`);

--
-- Indexes for table `skills`
--
ALTER TABLE `skills`
  ADD PRIMARY KEY (`skill_id`),
  ADD KEY `idx_skills_category` (`category`);

--
-- Indexes for table `sos_signals`
--
ALTER TABLE `sos_signals`
  ADD PRIMARY KEY (`sos_id`),
  ADD KEY `raised_by` (`raised_by`),
  ADD KEY `acknowledged_by` (`acknowledged_by`),
  ADD KEY `idx_sos_status` (`status`),
  ADD KEY `idx_sos_raised_at` (`raised_at`),
  ADD KEY `idx_sos_task` (`task_id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `assigned_by` (`assigned_by`),
  ADD KEY `idx_tasks_project` (`project_id`),
  ADD KEY `idx_tasks_assigned` (`assigned_to`),
  ADD KEY `idx_tasks_status` (`status`),
  ADD KEY `idx_tasks_priority` (`priority`),
  ADD KEY `idx_tasks_due_date` (`due_date`),
  ADD KEY `idx_tasks_stuck` (`is_stuck`);

--
-- Indexes for table `task_dependencies`
--
ALTER TABLE `task_dependencies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_deps_task` (`task_id`),
  ADD KEY `idx_deps_depends` (`depends_on_task_id`);

--
-- Indexes for table `task_recommendations`
--
ALTER TABLE `task_recommendations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_task_user` (`task_id`,`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_rec_match_score` (`match_score`);

--
-- Indexes for table `task_skills`
--
ALTER TABLE `task_skills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_task_skill` (`task_id`,`skill_id`),
  ADD KEY `skill_id` (`skill_id`),
  ADD KEY `idx_task_skills_task` (`task_id`);

--
-- Indexes for table `temp_passwords`
--
ALTER TABLE `temp_passwords`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_temp_password` (`temp_password`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_department` (`department`),
  ADD KEY `idx_users_email` (`email`);

--
-- Indexes for table `user_hierarchy`
--
ALTER TABLE `user_hierarchy`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hierarchy_user` (`user_id`),
  ADD KEY `idx_hierarchy_reports` (`reports_to`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `user_skills`
--
ALTER TABLE `user_skills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_skill` (`user_id`,`skill_id`),
  ADD KEY `idx_user_skills_user` (`user_id`),
  ADD KEY `idx_user_skills_skill` (`skill_id`);

--
-- Indexes for table `work_logs`
--
ALTER TABLE `work_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `idx_worklogs_user_date` (`user_id`,`log_date`),
  ADD KEY `idx_worklogs_task` (`task_id`),
  ADD KEY `idx_worklogs_date` (`log_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `leaderboard`
--
ALTER TABLE `leaderboard`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `parent_task`
--
ALTER TABLE `parent_task`
  MODIFY `parent_task_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `performance_ratings`
--
ALTER TABLE `performance_ratings`
  MODIFY `rating_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `project_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `project_members`
--
ALTER TABLE `project_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `reminders`
--
ALTER TABLE `reminders`
  MODIFY `reminder_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `skills`
--
ALTER TABLE `skills`
  MODIFY `skill_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `sos_signals`
--
ALTER TABLE `sos_signals`
  MODIFY `sos_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `task_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `task_dependencies`
--
ALTER TABLE `task_dependencies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `task_recommendations`
--
ALTER TABLE `task_recommendations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `task_skills`
--
ALTER TABLE `task_skills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `temp_passwords`
--
ALTER TABLE `temp_passwords`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `user_hierarchy`
--
ALTER TABLE `user_hierarchy`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_skills`
--
ALTER TABLE `user_skills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `work_logs`
--
ALTER TABLE `work_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `leaderboard`
--
ALTER TABLE `leaderboard`
  ADD CONSTRAINT `leaderboard_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `performance_ratings`
--
ALTER TABLE `performance_ratings`
  ADD CONSTRAINT `performance_ratings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `performance_ratings_ibfk_2` FOREIGN KEY (`rated_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `project_members`
--
ALTER TABLE `project_members`
  ADD CONSTRAINT `project_members_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_members_ibfk_3` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `reminders`
--
ALTER TABLE `reminders`
  ADD CONSTRAINT `reminders_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reminders_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `sos_signals`
--
ALTER TABLE `sos_signals`
  ADD CONSTRAINT `sos_signals_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sos_signals_ibfk_2` FOREIGN KEY (`raised_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sos_signals_ibfk_3` FOREIGN KEY (`acknowledged_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tasks_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `task_dependencies`
--
ALTER TABLE `task_dependencies`
  ADD CONSTRAINT `task_dependencies_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_dependencies_ibfk_2` FOREIGN KEY (`depends_on_task_id`) REFERENCES `tasks` (`task_id`) ON DELETE CASCADE;

--
-- Constraints for table `task_recommendations`
--
ALTER TABLE `task_recommendations`
  ADD CONSTRAINT `task_recommendations_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_recommendations_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `task_skills`
--
ALTER TABLE `task_skills`
  ADD CONSTRAINT `task_skills_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_skills_ibfk_2` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`skill_id`) ON DELETE CASCADE;

--
-- Constraints for table `temp_passwords`
--
ALTER TABLE `temp_passwords`
  ADD CONSTRAINT `temp_passwords_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_hierarchy`
--
ALTER TABLE `user_hierarchy`
  ADD CONSTRAINT `user_hierarchy_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_hierarchy_ibfk_2` FOREIGN KEY (`reports_to`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_skills`
--
ALTER TABLE `user_skills`
  ADD CONSTRAINT `user_skills_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_skills_ibfk_2` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`skill_id`) ON DELETE CASCADE;

--
-- Constraints for table `work_logs`
--
ALTER TABLE `work_logs`
  ADD CONSTRAINT `work_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `work_logs_ibfk_2` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `work_logs_ibfk_3` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

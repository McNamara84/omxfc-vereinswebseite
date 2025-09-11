/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `subject_type` varchar(255) NOT NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `action` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activities_user_id_foreign` (`user_id`),
  CONSTRAINT `activities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `message` varchar(140) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_messages_user_id_foreign` (`user_id`),
  CONSTRAINT `admin_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audiobook_episodes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audiobook_episodes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `episode_number` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) NOT NULL,
  `status` enum('Skripterstellung','Korrekturlesung','Rollenbesetzung','Aufnahmensammlung','Musikerstellung','Audiobearbeitung','Videobearbeitung','Grafiken','Veröffentlichungsplanung','Veröffentlichung') NOT NULL,
  `responsible_user_id` bigint(20) unsigned DEFAULT NULL,
  `progress` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `planned_release_date` varchar(255) DEFAULT NULL,
  `roles_total` smallint(5) unsigned NOT NULL DEFAULT 0,
  `roles_filled` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `audiobook_episodes_episode_number_unique` (`episode_number`),
  KEY `audiobook_episodes_responsible_user_id_foreign` (`responsible_user_id`),
  CONSTRAINT `audiobook_episodes_responsible_user_id_foreign` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audiobook_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audiobook_roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `episode_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `takes` smallint(5) unsigned NOT NULL DEFAULT 0,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `speaker_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audiobook_roles_episode_id_foreign` (`episode_id`),
  KEY `audiobook_roles_user_id_foreign` (`user_id`),
  KEY `audiobook_roles_name_user_speaker_index` (`name`,`user_id`,`speaker_name`),
  CONSTRAINT `audiobook_roles_episode_id_foreign` FOREIGN KEY (`episode_id`) REFERENCES `audiobook_episodes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `audiobook_roles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `book_offers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `book_offers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `series` varchar(255) NOT NULL,
  `book_number` int(11) NOT NULL,
  `book_title` varchar(255) NOT NULL,
  `condition` varchar(255) NOT NULL,
  `photos` json DEFAULT NULL,
  `completed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `book_offers_user_id_foreign` (`user_id`),
  CONSTRAINT `book_offers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `book_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `book_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `series` varchar(255) NOT NULL,
  `book_number` int(11) NOT NULL,
  `book_title` varchar(255) NOT NULL,
  `condition` varchar(255) NOT NULL,
  `completed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `book_requests_user_id_foreign` (`user_id`),
  CONSTRAINT `book_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `book_swaps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `book_swaps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `offer_id` bigint(20) unsigned NOT NULL,
  `request_id` bigint(20) unsigned NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `offer_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `request_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `book_swaps_offer_id_foreign` (`offer_id`),
  KEY `book_swaps_request_id_foreign` (`request_id`),
  CONSTRAINT `book_swaps_offer_id_foreign` FOREIGN KEY (`offer_id`) REFERENCES `book_offers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `book_swaps_request_id_foreign` FOREIGN KEY (`request_id`) REFERENCES `book_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `books`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `books` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `roman_number` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type` enum('Maddrax - Die dunkle Zukunft der Erde','Maddrax-Hardcover','Mission Mars-Heftromane','Das Volk der Tiefe','2012 - Das Jahr der Apokalypse','Die Abenteurer') NOT NULL DEFAULT 'Maddrax - Die dunkle Zukunft der Erde',
  PRIMARY KEY (`id`),
  UNIQUE KEY `books_roman_number_type_unique` (`roman_number`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kassenbuch_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kassenbuch_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `buchungsdatum` date NOT NULL,
  `betrag` decimal(10,2) NOT NULL,
  `beschreibung` varchar(255) NOT NULL,
  `typ` enum('einnahme','ausgabe') NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kassenbuch_entries_team_id_foreign` (`team_id`),
  KEY `kassenbuch_entries_created_by_foreign` (`created_by`),
  CONSTRAINT `kassenbuch_entries_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kassenbuch_entries_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kassenstand`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kassenstand` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint(20) unsigned NOT NULL,
  `betrag` decimal(10,2) NOT NULL DEFAULT 0.00,
  `letzte_aktualisierung` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kassenstand_team_id_foreign` (`team_id`),
  CONSTRAINT `kassenstand_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `missions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `missions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `origin` varchar(255) NOT NULL,
  `destination` varchar(255) NOT NULL,
  `travel_duration` int(11) NOT NULL,
  `mission_duration` int(11) NOT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `arrival_at` timestamp NULL DEFAULT NULL,
  `mission_ends_at` timestamp NULL DEFAULT NULL,
  `completed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `missions_user_id_foreign` (`user_id`),
  CONSTRAINT `missions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `page_visits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `page_visits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `path` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `page_visits_user_id_foreign` (`user_id`),
  CONSTRAINT `page_visits_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `review_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `review_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `review_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `content` text NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `review_comments_review_id_foreign` (`review_id`),
  KEY `review_comments_user_id_foreign` (`user_id`),
  KEY `review_comments_parent_id_foreign` (`parent_id`),
  CONSTRAINT `review_comments_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `review_comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `review_comments_review_id_foreign` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE,
  CONSTRAINT `review_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `book_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reviews_team_id_foreign` (`team_id`),
  KEY `reviews_user_id_foreign` (`user_id`),
  KEY `reviews_book_id_foreign` (`book_id`),
  CONSTRAINT `reviews_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `team_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `team_invitations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint(20) unsigned NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `team_invitations_team_id_email_unique` (`team_id`,`email`),
  CONSTRAINT `team_invitations_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `team_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `team_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `role` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `team_user_team_id_user_id_unique` (`team_id`,`user_id`),
  KEY `team_user_role_index` (`role`),
  KEY `team_user_team_id_role_index` (`team_id`,`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teams` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `personal_team` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `description` text DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `meeting_schedule` varchar(255) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `teams_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `todo_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `todo_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `todo_categories_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `todos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `todos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `verified_by` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `points` int(11) NOT NULL DEFAULT 0,
  `status` enum('open','assigned','completed','verified') NOT NULL DEFAULT 'open',
  `completed_at` timestamp NULL DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `todos_team_id_foreign` (`team_id`),
  KEY `todos_created_by_foreign` (`created_by`),
  KEY `todos_assigned_to_foreign` (`assigned_to`),
  KEY `todos_verified_by_foreign` (`verified_by`),
  KEY `todos_category_id_foreign` (`category_id`),
  CONSTRAINT `todos_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `todos_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `todo_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `todos_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `todos_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `todos_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_points` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `team_id` bigint(20) unsigned NOT NULL,
  `todo_id` bigint(20) unsigned DEFAULT NULL,
  `points` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_points_user_id_foreign` (`user_id`),
  KEY `user_points_team_id_foreign` (`team_id`),
  KEY `user_points_todo_id_foreign` (`todo_id`),
  CONSTRAINT `user_points_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_points_todo_id_foreign` FOREIGN KEY (`todo_id`) REFERENCES `todos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_points_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `two_factor_secret` text DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `current_team_id` bigint(20) unsigned NOT NULL DEFAULT 1,
  `profile_photo_path` varchar(2048) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `vorname` varchar(255) NOT NULL,
  `nachname` varchar(255) NOT NULL,
  `strasse` varchar(255) NOT NULL,
  `hausnummer` varchar(255) NOT NULL,
  `plz` varchar(255) NOT NULL,
  `stadt` varchar(255) NOT NULL,
  `land` varchar(255) NOT NULL,
  `telefon` varchar(255) DEFAULT NULL,
  `verein_gefunden` varchar(255) DEFAULT NULL,
  `mitgliedsbeitrag` decimal(6,2) NOT NULL DEFAULT 12.00,
  `bezahlt_bis` date DEFAULT NULL,
  `mitglied_seit` date DEFAULT NULL,
  `einstiegsroman` varchar(255) DEFAULT NULL,
  `lesestand` varchar(255) DEFAULT NULL,
  `lieblingsroman` varchar(255) DEFAULT NULL,
  `lieblingsfigur` varchar(255) DEFAULT NULL,
  `lieblingsmutation` varchar(255) DEFAULT NULL,
  `lieblingsschauplatz` varchar(255) DEFAULT NULL,
  `lieblingsautor` varchar(255) DEFAULT NULL,
  `lieblingszyklus` varchar(255) DEFAULT NULL,
  `notify_new_review` tinyint(1) NOT NULL DEFAULT 0,
  `last_activity` int(11) DEFAULT NULL,
  `lieblingsthema` varchar(255) DEFAULT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lon` decimal(10,7) DEFAULT NULL,
  `lieblingshardcover` varchar(255) DEFAULT NULL,
  `lieblingscover` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2025_03_18_193704_add_two_factor_columns_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2025_03_18_193733_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2025_03_18_193733_create_teams_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2025_03_18_193734_create_team_user_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2025_03_18_193735_create_team_invitations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2025_03_23_041007_add_fields_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2025_03_23_131556_create_default_admin_and_team',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2025_03_30_024554_add_seriendaten_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2025_04_01_114641_add_mitglied_seit_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2025_04_01_122159_add_bezahlt_bis_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2025_04_01_183444_create_todos_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2025_04_01_183510_create_user_points_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2025_04_02_095145_create_todo_categories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2025_04_07_131350_create_kassenbuch',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2025_04_09_153050_create_missions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2025_04_18_160246_create_book_offers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2025_04_18_160303_create_book_requests_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2025_04_18_160322_create_book_swaps_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2025_05_18_065853_create_books_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2025_05_18_070005_create_reviews_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2025_07_17_132020_create_review_comments',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2025_07_19_180314_add_notify_new_review_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2025_07_26_145711_add_confirmation_flags_to_book_swaps_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2025_07_27_044954_add_indexes_to_team_user_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2025_07_27_134700_create_activities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2025_07_27_151556_add_action_to_activities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2025_07_30_000000_add_fields_to_teams_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2025_08_02_181313_add_last_activity_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2025_08_02_205523_add_lieblingsthema_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2025_08_23_175217_add_lat_lon_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2025_09_01_000000_create_page_visits_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2025_09_02_155207_create_admin_messages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2025_09_15_000000_create_audiobook_episodes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2025_09_16_000000_change_planned_release_date_column_type',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2025_09_17_000000_add_roles_columns_to_audiobook_episodes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2025_09_18_000000_update_status_enum_in_audiobook_episodes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2025_09_19_000000_add_type_to_books_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2025_09_20_000000_add_lieblingshardcover_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2025_09_20_000001_add_lieblingscover_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2025_09_21_000000_create_audiobook_roles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2025_09_22_000000_add_name_user_speaker_index_to_audiobook_roles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2025_10_01_000000_update_book_type_enum',2);

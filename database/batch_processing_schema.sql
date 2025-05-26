-- Batch processing tables for intelligent scraper

CREATE TABLE IF NOT EXISTS `intelligent_scraper_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `total_urls` int(11) NOT NULL DEFAULT 0,
  `processed_urls` int(11) NOT NULL DEFAULT 0,
  `success_count` int(11) NOT NULL DEFAULT 0,
  `error_count` int(11) NOT NULL DEFAULT 0,
  `total_events` int(11) NOT NULL DEFAULT 0,
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `intelligent_scraper_batch_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `url` text NOT NULL,
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `session_id` int(11) DEFAULT NULL,
  `events_found` int(11) NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_batch_id` (`batch_id`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`batch_id`) REFERENCES `intelligent_scraper_batches`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`session_id`) REFERENCES `intelligent_scraper_sessions`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `intelligent_scraper_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) DEFAULT NULL,
  `session_id` int(11) DEFAULT NULL,
  `level` enum('debug','info','warning','error') NOT NULL DEFAULT 'info',
  `message` text NOT NULL,
  `details` longtext DEFAULT NULL,
  `url` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_batch_id` (`batch_id`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_level` (`level`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`batch_id`) REFERENCES `intelligent_scraper_batches`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`session_id`) REFERENCES `intelligent_scraper_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
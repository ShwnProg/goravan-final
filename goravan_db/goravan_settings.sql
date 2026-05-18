-- MySQL dump for goravan_settings table
-- Add to existing goravan database

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `system_name` varchar(100) NOT NULL DEFAULT 'GoraVan Admin',
  `timezone` varchar(50) NOT NULL DEFAULT 'Asia/Manila',
  `date_format` varchar(20) NOT NULL DEFAULT 'd/m/Y',
  `logo_path` varchar(255) NOT NULL DEFAULT 'images/logo.png',
  `default_trip_status` enum('pending','confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
  `max_van_capacity` smallint unsigned NOT NULL DEFAULT 14,
  `booking_rules` text,
  `session_timeout` smallint unsigned NOT NULL DEFAULT 60,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnonoDB DEFAULT CHARSET=utf8mb3;

-- Insert default settings
INSERT INTO `settings` (
    system_name, timezone, date_format, logo_path, default_trip_status, 
    max_van_capacity, booking_rules, session_timeout
) VALUES (
    'GoraVan Admin', 'Asia/Manila', 'd/m/Y', 'images/logo.png', 'confirmed',
    14, 
    '- Minimum 24h advance booking\\n- Max 2 bookings per user per day\\n- Children under 5 free w/ adult',
    60
);

-- Run this SQL in phpMyAdmin or MySQL CLI:
-- source goravan_settings.sql


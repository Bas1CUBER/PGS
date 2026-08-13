<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `engagement_values` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_key` varchar(8) NOT NULL,
  `question_no` int(11) NOT NULL,
  `year` int(11) NOT NULL DEFAULT 2025,
  `percent` decimal(6,3) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_entry` (`section_key`,`question_no`,`year`),
  KEY `fk_engagement_values_user` (`created_by`),
  CONSTRAINT `fk_engagement_values_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS `engagement_values`');
    }
};

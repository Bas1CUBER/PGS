<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `training_tot_personnel` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section` varchar(80) NOT NULL,
  `personnel` varchar(120) NOT NULL,
  `is_head` tinyint(1) NOT NULL DEFAULT 0,
  `y2024` int(11) DEFAULT NULL,
  `y2025` int(11) DEFAULT NULL,
  `y2026` int(11) DEFAULT NULL,
  `y2027` int(11) DEFAULT NULL,
  `y2028` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_row` (`section`,`personnel`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS `training_tot_personnel`');
    }
};

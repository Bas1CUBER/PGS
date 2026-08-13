<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `revenue_hospital_main` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `classification` varchar(180) DEFAULT NULL,
  `y2024` decimal(15,2) DEFAULT NULL,
  `y2025` decimal(15,2) DEFAULT NULL,
  `y2026` decimal(15,2) DEFAULT NULL,
  `y2027` decimal(15,2) DEFAULT NULL,
  `y2028` decimal(15,2) DEFAULT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS `revenue_hospital_main`');
    }
};

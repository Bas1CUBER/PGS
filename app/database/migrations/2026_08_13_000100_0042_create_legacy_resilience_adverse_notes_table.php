<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `resilience_adverse_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(160) DEFAULT NULL,
  `val` varchar(160) DEFAULT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `y2024` int(11) DEFAULT NULL,
  `y2025` int(11) DEFAULT NULL,
  `y2026` int(11) DEFAULT NULL,
  `y2027` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS `resilience_adverse_notes`');
    }
};

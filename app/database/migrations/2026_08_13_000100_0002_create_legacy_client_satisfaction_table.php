<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `client_satisfaction` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `division` varchar(128) NOT NULL,
  `section` varchar(128) NOT NULL,
  `month` tinyint(4) NOT NULL,
  `year` int(11) NOT NULL,
  `satisfied` int(11) NOT NULL DEFAULT 0,
  `total` int(11) NOT NULL DEFAULT 0,
  `row_locked` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `pct` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_year_month` (`year`,`month`),
  KEY `idx_div_sec` (`division`,`section`),
  KEY `fk_client_sat_user` (`created_by`),
  CONSTRAINT `fk_client_sat_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS `client_satisfaction`');
    }
};

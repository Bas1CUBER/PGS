<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `qli_health_rows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registry_no` varchar(50) NOT NULL,
  `program` varchar(100) NOT NULL,
  `overall_during` varchar(50) DEFAULT NULL,
  `overall_after` varchar(50) DEFAULT NULL,
  `physical_during` varchar(50) DEFAULT NULL,
  `physical_after` varchar(50) DEFAULT NULL,
  `mental_during` varchar(50) DEFAULT NULL,
  `mental_after` varchar(50) DEFAULT NULL,
  `social_during` varchar(50) DEFAULT NULL,
  `social_after` varchar(50) DEFAULT NULL,
  `environment_during` varchar(50) DEFAULT NULL,
  `environment_after` varchar(50) DEFAULT NULL,
  `row_locked` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_qli_health_user` (`created_by`),
  CONSTRAINT `fk_qli_health_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS `qli_health_rows`');
    }
};

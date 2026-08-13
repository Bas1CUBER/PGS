<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `client_satisfaction_values` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_key` varchar(3) NOT NULL,
  `division_key` varchar(80) NOT NULL,
  `year` int(11) NOT NULL,
  `january` decimal(7,3) DEFAULT NULL,
  `february` decimal(7,3) DEFAULT NULL,
  `march` decimal(7,3) DEFAULT NULL,
  `april` decimal(7,3) DEFAULT NULL,
  `may` decimal(7,3) DEFAULT NULL,
  `june` decimal(7,3) DEFAULT NULL,
  `july` decimal(7,3) DEFAULT NULL,
  `august` decimal(7,3) DEFAULT NULL,
  `september` decimal(7,3) DEFAULT NULL,
  `october` decimal(7,3) DEFAULT NULL,
  `november` decimal(7,3) DEFAULT NULL,
  `december` decimal(7,3) DEFAULT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `annual` decimal(7,3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_row` (`table_key`,`division_key`,`year`),
  KEY `fk_csv_user` (`created_by`),
  CONSTRAINT `fk_csv_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS `client_satisfaction_values`');
    }
};

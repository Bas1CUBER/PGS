<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `qli_employment_rows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registry_no` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `program` varchar(100) NOT NULL,
  `entry_employment` varchar(100) DEFAULT NULL,
  `entry_occupation` varchar(100) DEFAULT NULL,
  `after_employment` varchar(100) DEFAULT NULL,
  `after_occupation` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `row_locked` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_qli_emp_user` (`created_by`),
  CONSTRAINT `fk_qli_emp_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS `qli_employment_rows`');
    }
};

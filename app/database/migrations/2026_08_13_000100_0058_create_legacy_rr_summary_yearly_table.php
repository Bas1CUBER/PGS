<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `rr_summary_yearly` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year` int(11) NOT NULL,
  `grads_opd` int(11) NOT NULL DEFAULT 0,
  `grads_res` int(11) NOT NULL DEFAULT 0,
  `grads_after` int(11) NOT NULL DEFAULT 0,
  `relapse_opd` int(11) NOT NULL DEFAULT 0,
  `relapse_res` int(11) NOT NULL DEFAULT 0,
  `relapse_after` int(11) NOT NULL DEFAULT 0,
  `row_locked` tinyint(1) NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rr_summary_year` (`year`),
  KEY `fk_rrsy_user` (`created_by`),
  CONSTRAINT `fk_rrsy_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=596 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS `rr_summary_yearly`');
    }
};

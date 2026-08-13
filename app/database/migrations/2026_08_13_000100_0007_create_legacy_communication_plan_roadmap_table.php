<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `communication_plan_roadmap` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `objective` text NOT NULL,
  `target_audience` text DEFAULT NULL,
  `message` text DEFAULT NULL,
  `channel` varchar(255) DEFAULT NULL,
  `timeframe` varchar(255) DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `responsible_person` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Not Accomplished/Started','Ongoing','Completed') NOT NULL DEFAULT 'Not Accomplished/Started',
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `communication_plan_roadmap_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS `communication_plan_roadmap`');
    }
};

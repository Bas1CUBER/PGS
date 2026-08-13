<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `p_deliverables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `form_type` varchar(100) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `focal_person` varchar(255) DEFAULT NULL,
  `division` varchar(255) DEFAULT NULL,
  `target_date` date DEFAULT NULL,
  `status` enum('Accomplished','Ongoing','Not Yet Started') DEFAULT NULL,
  `actual_date` date DEFAULT NULL,
  `mov_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `uploaded_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_target_date` (`target_date`),
  KEY `idx_uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS `p_deliverables`');
    }
};

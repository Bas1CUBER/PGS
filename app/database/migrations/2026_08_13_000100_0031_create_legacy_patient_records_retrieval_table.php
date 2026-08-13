<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `patient_records_retrieval` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registry_no` varchar(32) DEFAULT NULL,
  `request_date` date DEFAULT NULL,
  `request_time` time DEFAULT NULL,
  `released_date` date DEFAULT NULL,
  `released_time` time DEFAULT NULL,
  `returned_date` date DEFAULT NULL,
  `returned_time` time DEFAULT NULL,
  `retrieval_time` varchar(64) DEFAULT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS `patient_records_retrieval`');
    }
};

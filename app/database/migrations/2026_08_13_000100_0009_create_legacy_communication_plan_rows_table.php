<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `communication_plan_rows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_id` int(11) NOT NULL,
  `objective` text DEFAULT NULL,
  `target_audience` text DEFAULT NULL,
  `message` text DEFAULT NULL,
  `channel` text DEFAULT NULL,
  `timeframe` varchar(255) DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `responsible_person` text DEFAULT NULL,
  `position` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `plan_id` (`plan_id`),
  CONSTRAINT `communication_plan_rows_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `communication_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS `communication_plan_rows`');
    }
};

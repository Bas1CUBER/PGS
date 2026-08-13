<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `impact_scorecard_values` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `measure_id` int(11) NOT NULL,
  `year_id` int(11) NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_measure_year` (`measure_id`,`year_id`),
  KEY `idx_year_id` (`year_id`),
  CONSTRAINT `fk_impact_values_measure` FOREIGN KEY (`measure_id`) REFERENCES `impact_scorecard_measures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_impact_values_year` FOREIGN KEY (`year_id`) REFERENCES `impact_scorecard_years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS `impact_scorecard_values`');
    }
};

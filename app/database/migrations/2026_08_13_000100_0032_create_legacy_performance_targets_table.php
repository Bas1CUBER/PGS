<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `performance_targets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `strategic_goal` text DEFAULT NULL,
  `success_indicator` text NOT NULL,
  `division_accountable` varchar(255) NOT NULL,
  `annual_target` varchar(255) DEFAULT NULL,
  `quarter1_target` varchar(255) DEFAULT NULL,
  `quarter2_target` varchar(255) DEFAULT NULL,
  `quarter3_target` varchar(255) DEFAULT NULL,
  `quarter4_target` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS `performance_targets`');
    }
};

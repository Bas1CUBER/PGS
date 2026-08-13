<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `deadline_controls` (
  `role` enum('employee','focal') NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `end_time` datetime DEFAULT NULL,
  `message` varchar(255) DEFAULT 'Please comply with the submission requirements before the deadline.',
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS `deadline_controls`');
    }
};

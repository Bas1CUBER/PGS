<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `user_page_access` (
  `user_id` int(11) NOT NULL,
  `roadmaps` tinyint(1) NOT NULL DEFAULT 1,
  `scorecard` tinyint(1) NOT NULL DEFAULT 1,
  `performance_assessment` tinyint(1) NOT NULL DEFAULT 1,
  `cascading` tinyint(1) NOT NULL DEFAULT 1,
  `governance` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_user_page_access_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS `user_page_access`');
    }
};

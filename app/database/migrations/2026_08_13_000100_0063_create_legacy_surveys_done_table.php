<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `surveys_done` (
  `survey_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `done_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`survey_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `surveys_done_ibfk_1` FOREIGN KEY (`survey_id`) REFERENCES `surveys` (`id`) ON DELETE CASCADE,
  CONSTRAINT `surveys_done_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS `surveys_done`');
    }
};

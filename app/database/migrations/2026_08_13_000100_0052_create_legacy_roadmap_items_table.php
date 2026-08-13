<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `roadmap_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title_id` int(11) NOT NULL,
  `sub_letter` varchar(10) NOT NULL,
  `sub_label` varchar(500) NOT NULL,
  `page_slug` varchar(255) NOT NULL DEFAULT '',
  `has_builder_page` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `title_id` (`title_id`),
  CONSTRAINT `roadmap_items_ibfk_1` FOREIGN KEY (`title_id`) REFERENCES `roadmap_titles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS `roadmap_items`');
    }
};

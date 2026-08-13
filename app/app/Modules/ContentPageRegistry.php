<?php

declare(strict_types=1);

namespace App\Modules;

/**
 * Static content pages (legacy about_* + organization pages). Each page
 * displays an image from the shared `img/` directory; admins can replace it.
 */
final class ContentPageRegistry
{
    /**
     * @return array<string, array{title: string, img_base: string}>
     */
    public static function pages(): array
    {
        return [
            'about-strategy-map' => ['title' => 'Strategy Map', 'img_base' => 'About Strategy Map'],
            'about-strategic-position' => ['title' => 'Strategic Position', 'img_base' => 'About Strategic Position'],
            'about-pgs-pathway' => ['title' => 'PGS Pathway', 'img_base' => 'pgs_pathway_panel_0'],
            'about-charter-statements' => ['title' => 'Charter Statements', 'img_base' => 'final_logo'],
            'about-user-access' => ['title' => 'User Access', 'img_base' => 'pgs_roles'],
            'multi-sector-governance' => ['title' => 'Multi-Sector Governance System', 'img_base' => 'Multi-Sector Governance System'],
            'office-for-strategy-management' => ['title' => 'Office for Strategy Management', 'img_base' => 'Office for Strategy Management'],
            'pgs-core-team' => ['title' => 'PGS Core Team', 'img_base' => 'PGS Core team'],
        ];
    }

    /**
     * @return array{slug: string, title: string, img_base: string}|null
     */
    public static function find(string $slug): ?array
    {
        $page = self::pages()[$slug] ?? null;

        if ($page === null) {
            return null;
        }

        return ['slug' => $slug] + $page;
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_keys(self::pages());
    }
}

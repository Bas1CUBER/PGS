<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Centralized cache invalidation using version-based keys.
 *
 * Each domain (e.g. 'dashboard', 'sector', 'gallery') has a version counter.
 * When data changes, bump the version → all cached reads for that domain
 * automatically miss and recompute.
 *
 * Domains:
 * - 'dashboard'    — dashboard stats/feeds
 * - 'sector'       — sector module data
 * - 'sector_detail' — sector detail data
 * - 'roadmap'      — roadmap tree
 * - 'scorecard'    — impact scorecard
 * - 'gallery'      — gallery albums/photos
 * - 'upload'       — upload module data
 * - 'survey'       — survey listings
 * - 'notice'       — notice listings
 * - 'notification' — notification counts/feeds
 * - 'deliverable'  — deliverable listings
 * - 'comm_plan'    — communication plan
 * - 'ops_review'   — operations review
 * - 'strat_review' — strategy review
 * - 'legacy_form'  — annex/OPCR forms
 * - 'user'         — user listings
 */
class CacheInvalidationService
{
    private const TTL = 60; // seconds — matches existing 60s pattern

    public static function version(string $domain): int
    {
        return (int) Cache::get("pgs-version:{$domain}", 0);
    }

    public static function invalidate(string $domain): void
    {
        $key = "pgs-version:{$domain}";

        if (Cache::add($key, 1, now()->addYears(10))) {
            return;
        }

        if (Cache::increment($key) !== false) {
            return;
        }

        Cache::forever($key, max(1, self::version($domain) + 1));
    }

    /**
     * Invalidate multiple domains at once.
     *
     * @param  list<string>  $domains
     */
    public static function invalidateMany(array $domains): void
    {
        foreach (array_unique($domains) as $domain) {
            self::invalidate($domain);
        }
    }

    /**
     * Get a versioned cache key.
     */
    public static function key(string $domain, string $key): string
    {
        $version = self::version($domain);

        return "pgs:{$domain}:v{$version}:{$key}";
    }

    /**
     * Remember a value with version-based key and stampede protection.
     *
     * @template TValue
     *
     * @param  \Closure(): TValue  $callback
     * @return TValue
     */
    public static function remember(string $domain, string $key, \Closure $callback, ?int $ttl = null): mixed
    {
        $ttl ??= self::TTL;
        $cacheKey = self::key($domain, $key);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $lock = Cache::lock("pgs:{$domain}:lock:{$key}", 10);
        try {
            if ((bool) $lock->get()) {
                try {
                    $cached = Cache::get($cacheKey);
                    if ($cached !== null) {
                        return $cached;
                    }

                    $value = $callback();
                    Cache::put($cacheKey, $value, $ttl);

                    return $value;
                } finally {
                    $lock->release();
                }
            }
        } catch (\Throwable) {
            return $callback();
        }

        return $callback();
    }

    /**
     * Bump versions for related domains when a model changes.
     */
    public static function onDeliverableChange(): void
    {
        self::invalidateMany(['dashboard', 'deliverable']);
    }

    public static function onUploadChange(string $module): void
    {
        // Readers cache under the bare 'upload' domain (per-slug distinction
        // lives in the key), so bumping only "upload:{$module}" would leave
        // module pages stale for the TTL.
        self::invalidateMany(['dashboard', 'upload', "upload:{$module}"]);
    }

    public static function onNoticeChange(): void
    {
        self::invalidateMany(['dashboard', 'notice']);
    }

    public static function onSectorChange(): void
    {
        self::invalidateMany(['dashboard', 'sector', 'sector_detail']);
    }

    public static function onRoadmapChange(): void
    {
        self::invalidateMany(['dashboard', 'roadmap']);
    }

    public static function onScorecardChange(): void
    {
        self::invalidateMany(['dashboard', 'scorecard']);
    }

    public static function onGalleryChange(): void
    {
        self::invalidateMany(['dashboard', 'gallery']);
    }

    public static function onSurveyChange(): void
    {
        self::invalidateMany(['dashboard', 'survey']);
    }

    public static function onNotificationChange(): void
    {
        // Per-user isolation comes from the cache key ("index:{id}",
        // "unread:{id}", "feed:{id}"), so a single domain bump invalidates
        // every affected user entry.
        self::invalidateMany(['dashboard', 'notification']);
    }

    public static function onUserChange(): void
    {
        self::invalidateMany(['dashboard', 'user']);
    }

    public static function onCommPlanChange(): void
    {
        self::invalidateMany(['dashboard', 'comm_plan']);
    }

    public static function onOpsReviewChange(): void
    {
        self::invalidateMany(['dashboard', 'ops_review']);
    }

    public static function onStratReviewChange(): void
    {
        self::invalidateMany(['dashboard', 'strat_review']);
    }

    public static function onLegacyFormChange(): void
    {
        self::invalidateMany(['dashboard', 'legacy_form']);
    }
}

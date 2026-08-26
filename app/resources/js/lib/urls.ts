/**
 * Centralised frontend route helpers.
 *
 * Ziggy `route()` is only used in auth pages; the remaining ~100 call sites
 * hard-code path strings. This module provides a single source of truth so
 * renames break loudly at build time instead of silently at runtime.
 *
 * Prefer these helpers over raw strings. For routes that take params, the
 * helper builds the path; for simple GETs it returns the constant string.
 */

const u = (value: number | string): string => [value].join('');

export const urls = {
    users: {
        index: '/users',
        create: '/users/create',
        store: '/users',
        edit: (id: number | string) => `/users/${u(id)}/edit`,
        update: (id: number | string) => `/users/${u(id)}`,
        updateAccess: (id: number | string) => `/users/${u(id)}/access`,
        toggle: (id: number | string) => `/users/${u(id)}/toggle`,
        destroy: (id: number | string) => `/users/${u(id)}`,
        import: '/users/import',
    },
    gallery: {
        index: '/gallery',
        albums: '/gallery/albums',
        album: (id: number | string) => `/gallery/albums/${u(id)}`,
        photos: (albumId: number | string) => `/gallery/albums/${u(albumId)}/photos`,
        photo: (id: number | string) => `/gallery/photos/${u(id)}`,
        file: (id: number | string) => `/gallery/photos/${u(id)}/file`,
    },
    notices: {
        index: '/notices',
        store: '/notices',
        update: (id: number | string) => `/notices/${u(id)}`,
        destroy: (id: number | string) => `/notices/${u(id)}`,
        media: (id: number | string, kind: string) => `/notices/${u(id)}/${u(kind)}`,
    },
    uploads: {
        show: (slug: string) => `/${u(slug)}/upload`,
        store: (slug: string) => `/${u(slug)}/upload`,
        status: (slug: string, id: number | string) => `/${u(slug)}/upload/${u(id)}/status`,
        destroy: (slug: string, id: number | string) => `/${u(slug)}/upload/${u(id)}`,
        templates: {
            store: (slug: string) => `/${u(slug)}/upload/templates`,
            download: (slug: string, id: number | string) => `/${u(slug)}/upload/templates/${u(id)}`,
            destroy: (slug: string, id: number | string) => `/${u(slug)}/upload/templates/${u(id)}`,
        },
    },
    sectors: {
        index: '/sectors',
        show: (slug: string) => `/sectors/${u(slug)}`,
        rows: (slug: string) => `/sectors/${u(slug)}/rows`,
        row: (slug: string, id: number | string) => `/sectors/${u(slug)}/rows/${u(id)}`,
        progress: (slug: string, id: number | string) => `/sectors/${u(slug)}/progress/${u(id)}`,
        decision: (slug: string, id: number | string) =>
            `/sectors/${u(slug)}/pending/${u(id)}/decision`,
        detailRow: (pillar: string, slug: string, id: number | string) =>
            `/sectors/${u(pillar)}/${u(slug)}/${u(id)}`,
        detailRowLock: (pillar: string, slug: string, id: number | string) =>
            `/sectors/${u(pillar)}/${u(slug)}/${u(id)}/lock`,
        detailExport: (pillar: string, slug: string) =>
            `/sectors/${u(pillar)}/${u(slug)}/export`,
    },
    sectorDetails: (pillar: string, slug: string) => `/sectors/${u(pillar)}/${u(slug)}`,
    content: {
        image: (slug: string) => `/content/${u(slug)}/image`,
        structured: (slug: string) => `/content/${u(slug)}/structured`,
    },
    scorecard: {
        index: '/impact-scorecard',
        measures: '/impact-scorecard/measures',
        measure: (id: number | string) => `/impact-scorecard/measures/${u(id)}`,
        years: '/impact-scorecard/years',
        year: (id: number | string) => `/impact-scorecard/years/${u(id)}`,
        value: (measureId: number | string, yearId: number | string) =>
            `/impact-scorecard/values/${u(measureId)}/${u(yearId)}`,
    },
    surveys: {
        index: '/surveys',
        store: '/surveys',
        update: (id: number | string) => `/surveys/${u(id)}`,
        archive: (id: number | string) => `/surveys/${u(id)}/archive`,
        destroy: (id: number | string) => `/surveys/${u(id)}`,
        done: (id: number | string) => `/surveys/${u(id)}/done`,
    },
    roadmaps: {
        index: '/roadmaps',
        titles: '/roadmaps/titles',
        title: (id: number | string) => `/roadmaps/titles/${u(id)}`,
        items: (titleId: number | string) => `/roadmaps/titles/${u(titleId)}/items`,
        item: (id: number | string) => `/roadmaps/items/${u(id)}`,
        reorder: (id: number | string) => `/roadmaps/items/${u(id)}/reorder`,
        blocks: (itemId: number | string) => `/roadmaps/items/${u(itemId)}/blocks`,
        block: (id: number | string) => `/roadmaps/blocks/${u(id)}`,
    },
    deliverables: {
        index: '/deliverables',
        create: '/deliverables/create',
        store: '/deliverables',
        edit: (id: number | string) => `/deliverables/${u(id)}/edit`,
        update: (id: number | string) => `/deliverables/${u(id)}`,
        status: (id: number | string) => `/deliverables/${u(id)}/status`,
        destroy: (id: number | string) => `/deliverables/${u(id)}`,
        download: (id: number | string) => `/deliverables/${u(id)}/download`,
        pdf: (id: number | string) => `/deliverables/${u(id)}/pdf`,
    },
    backups: {
        index: '/backups',
        create: '/backups',
        restore: (disk: string, path: string) => `/backups/${u(disk)}/${u(path)}/restore`,
        download: (disk: string, path: string) => `/backups/${u(disk)}/${u(path)}`,
        destroy: (disk: string, path: string) => `/backups/${u(disk)}/${u(path)}`,
    },
    strategyReview: {
        index: '/strategy-review',
        update: (id: number | string) => `/strategy-review/${u(id)}`,
        review: (id: number | string) => `/strategy-review/${u(id)}/review`,
        pdf: (id: number | string) => `/strategy-review/${u(id)}/pdf`,
    },
    operationsReview: {
        index: '/operations-review',
        update: (id: number | string) => `/operations-review/${u(id)}`,
        destroy: (id: number | string) => `/operations-review/${u(id)}`,
        pdf: (id: number | string) => `/operations-review/${u(id)}/pdf`,
    },
    opcr: {
        index: '/opcr',
        update: (id: number | string) => `/opcr/${u(id)}`,
        destroy: (id: number | string) => `/opcr/${u(id)}`,
    },
    communicationPlan: {
        index: '/communication-plan',
        update: (id: number | string) => `/communication-plan/${u(id)}`,
        destroy: (id: number | string) => `/communication-plan/${u(id)}`,
    },
    annex: {
        store: (slug: string) => `/annex/${u(slug)}`,
        update: (slug: string, id: number | string) => `/annex/${u(slug)}/${u(id)}`,
        destroy: (slug: string, id: number | string) => `/annex/${u(slug)}/${u(id)}`,
    },
    notifications: {
        index: '/notifications',
        readAll: '/notifications/read-all',
        read: (id: number | string) => `/notifications/${u(id)}/read`,
        feed: '/notifications/feed',
    },
    dashboard: '/dashboard',
    profile: { edit: '/profile', update: '/profile', destroy: '/profile' },
} as const;

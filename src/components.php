<?php

declare(strict_types=1);

/**
 * Small UI component helpers (design system).
 * Loaded by src/bootstrap.php. Usage in views:
 *   <?= ui_badge('Pending') ?>
 *   <?= ui_btn('Download', ['icon' => 'download', 'variant' => 'success', 'href' => $url, 'download' => true]) ?>
 *   <?= ui_page_header('Governance Culture', BASE_URL . '/governance_culture') ?>
 */

// Status -> Bootstrap badge classes
function ui_badge_classes(string $status): string
{
    return match (strtolower(trim($status))) {
        'approved', 'accomplished', 'completed', 'active', 'done', 'success' => 'bg-success',
        'pending', 'ongoing', 'in progress', 'processing', 'for approval' => 'bg-warning text-dark',
        'returned', 'rejected', 'failed', 'error', 'not accomplished/started' => 'bg-danger',
        'not started', 'not yet started', 'inactive', 'disabled' => 'bg-secondary',
        'draft' => 'bg-info text-dark',
        default => 'bg-primary',
    };
}

// Status pill
function ui_badge(string $status, string $extraClass = ''): string
{
    $label = h($status);
    return '<span class="badge ' . ui_badge_classes($status) . ' ' . $extraClass . '">' . $label . '</span>';
}

// Lucide icon
function ui_icon(string $name, int $size = 16, string $extraClass = ''): string
{
    return '<i data-lucide="' . h($name) . '" width="' . $size . '" height="' . $size
        . '" class="' . $extraClass . '"></i>';
}

// Button / link styled as button
// $opts: href, icon, variant (primary|secondary|success|danger|warning|info|light|dark|outline-primary|...),
//        size (sm|lg), target, download, confirm (SweetAlert message), extra (classes), type, onclick
function ui_btn(string $label, array $opts = []): string
{
    $variant = $opts['variant'] ?? 'primary';
    $size = $opts['size'] ?? '';
    $icon = $opts['icon'] ?? '';
    $extra = $opts['extra'] ?? '';

    $class = 'btn btn-' . $variant;
    if ($size !== '') {
        $class .= ' btn-' . $size;
    }
    if ($extra !== '') {
        $class .= ' ' . $extra;
    }

    $content = '';
    if ($icon !== '') {
        $content .= ui_icon($icon, 16, 'me-1') . ' ';
    }
    $content .= h($label);

    $attrs = 'class="' . $class . '"';
    if (!empty($opts['title'])) {
        $attrs .= ' title="' . h($opts['title']) . '"';
    }
    if (!empty($opts['id'])) {
        $attrs .= ' id="' . h($opts['id']) . '"';
    }
    if (!empty($opts['target'])) {
        $attrs .= ' target="' . h($opts['target']) . '"';
    }
    if (!empty($opts['download'])) {
        $attrs .= ' download';
    }
    if (!empty($opts['data'])) {
        foreach ($opts['data'] as $k => $v) {
            $attrs .= ' data-' . h($k) . '="' . h($v) . '"';
        }
    }
    if (!empty($opts['onclick'])) {
        $attrs .= ' onclick="' . h($opts['onclick']) . '"';
    }
    if (!empty($opts['confirm'])) {
        $attrs .= ' onclick="if(!confirm(' . json_encode($opts['confirm']) . ')){event.preventDefault();}"';
    }

    if (!empty($opts['href'])) {
        return '<a href="' . h($opts['href']) . '" ' . $attrs . '>' . $content . '</a>';
    }
    $type = $opts['type'] ?? 'button';
    return '<button type="' . h($type) . '" ' . $attrs . '>' . $content . '</button>';
}

// Page header with optional back button (matches the app's .header-wrap style)
function ui_page_header(string $title, ?string $backUrl = null, string $subtitle = ''): string
{
    $html = '<div class="header-wrap">';
    if ($backUrl !== null) {
        $html .= ui_btn('Back', ['href' => $backUrl, 'variant' => 'outline-secondary', 'size' => 'sm', 'icon' => 'arrow-left'])
            . ' ';
    }
    $html .= '<div class="header-title"><h4 class="mb-0">' . h($title) . '</h4>';
    if ($subtitle !== '') {
        $html .= '<small class="text-muted">' . h($subtitle) . '</small>';
    }
    $html .= '</div></div>';
    return $html;
}

// Flash alert box
function ui_alert(string $message, string $type = 'danger'): string
{
    return '<div class="alert alert-' . h($type) . ' alert-dismissible fade show" role="alert">'
        . h($message)
        . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
}

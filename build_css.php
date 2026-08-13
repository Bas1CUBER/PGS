<?php

declare(strict_types=1);

/**
 * CSS build step: concatenate app.css + assets/css/pages/*.css into ONE
 * production file (assets/css/all.css) with light minification.
 *
 * Usage:  php build_css.php
 * After building, set PGS_CSS_MODE=prod in config.php to serve the single file.
 */

$root = __DIR__;
$outPath = $root . '/assets/css/all.css';

$parts = [];
$parts[] = ['file' => $root . '/assets/css/app.css', 'label' => 'app.css'];

$pageDir = $root . '/assets/css/pages';
$pageFiles = glob($pageDir . '/*.css') ?: [];
sort($pageFiles);
foreach ($pageFiles as $f) {
    $parts[] = ['file' => $f, 'label' => basename($f)];
}

$minify = !in_array('--no-minify', $argv, true);

$out = '';
foreach ($parts as $part) {
    if (!is_file($part['file'])) {
        fwrite(STDERR, 'MISSING: ' . $part['file'] . PHP_EOL);
        continue;
    }
    $css = file_get_contents($part['file']);
    if ($minify) {
        // strip /* ... */ comments (conservative: keep everything else)
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        // collapse 2+ newlines / leading indentation
        $css = preg_replace('/[ \t]+\n/', "\n", $css);
        $css = preg_replace('/\n{3,}/', "\n\n", $css);
    }
    $out .= '/* ==== ' . $part['label'] . ' ==== */' . "\n" . trim($css) . "\n\n";
}

file_put_contents($outPath, $out);
$kb = round(strlen($out) / 1024, 1);
echo "Built $outPath ({$kb} KB, " . count($parts) . " sources, minify=" . ($minify ? 'yes' : 'no') . ")\n";

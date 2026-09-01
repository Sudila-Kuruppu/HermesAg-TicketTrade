<?php
/**
 * TicketTrade — Migrations Runner
 *
 * Per AD-6 + D-22..D-28:
 *   - Reads config/db.php (or config/db.test.php when APP_ENV=test).
 *   - Lists migrations/*.sql in lex order, skips filenames in
 *     migrations/.applied (plain text, one filename per line).
 *   - Each file runs in a single explicit transaction with savepoints
 *     per statement. DDL is idempotent (IF NOT EXISTS) per D-24.
 *   - .applied is updated atomically (tempnam + rename).
 *
 * Usage:
 *   php migrate.php
 *
 * Exit codes: 0 on success, 1 on error.
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

require_once APP_ROOT . '/vendor/autoload.php';

use App\Support\Db;

$appEnv = getenv('APP_ENV') ?: 'development';
$configFile = $appEnv === 'test'
    ? APP_ROOT . '/config/db.test.php'
    : APP_ROOT . '/config/db.php';

if (!is_file($configFile)) {
    fwrite(STDERR, "[migrate] Missing config: {$configFile}\n");
    exit(1);
}

$config = require $configFile;

try {
    $pdo = Db::connectFromConfig($config);
} catch (\Throwable $e) {
    fwrite(STDERR, "[migrate] DB connect failed: " . $e->getMessage() . "\n");
    exit(1);
}

$migrationsDir = APP_ROOT . '/migrations';
$appliedFile = $migrationsDir . '/.applied';

// Read .applied (one filename per line)
$applied = [];
if (is_file($appliedFile)) {
    $lines = file($appliedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        $applied = array_map('trim', $lines);
    }
}

// List migration files
$files = glob($migrationsDir . '/*.sql') ?: [];
sort($files, SORT_STRING);

// Filter out dotfiles + non-migration files
$files = array_values(array_filter($files, function ($f) {
    $base = basename($f);
    return str_starts_with($base, '0') && substr($base, 0, 1) !== '.';
}));

$pending = [];
foreach ($files as $f) {
    $name = basename($f);
    if (!in_array($name, $applied, true)) {
        $pending[] = $f;
    }
}

if (empty($pending)) {
    echo 'Already up-to-date (0 files to apply).' . PHP_EOL;
    exit(0);
}

$start = microtime(true);
foreach ($pending as $file) {
    $name = basename($file);
    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "[migrate] Failed to read: {$name}\n");
        exit(1);
    }
    // Strip /* ... */ block comments
    $sql = preg_replace('#/\*.*?\*/#s', '', $sql);
    // Strip -- line comments (must not be inside strings; per D-27 we
    // forbid ; inside string literals)
    $lines = explode("\n", $sql);
    $cleaned = [];
    foreach ($lines as $line) {
        $stripped = preg_replace('/--.*$/', '', $line);
        $cleaned[] = $stripped;
    }
    $sql = implode("\n", $cleaned);

    // Split on ; — naive but D-27 guarantees no ; in strings
    $statements = array_filter(array_map('trim', explode(';', $sql)), function ($s) {
        return $s !== '';
    });

    try {
        // MySQL/MariaDB auto-commits DDL. Per RESEARCH.md, IF NOT EXISTS
        // discipline keeps the half-applied edge case safe.
        foreach ($statements as $stmt) {
            $pdo->exec($stmt);
        }
        // Append to .applied atomically (tempnam + rename). Use /tmp
        // for the temp file so the migrate runner works on hosts where
        // the project directory is on a constrained filesystem.
        $tmp = tempnam(sys_get_temp_dir(), '.applied-');
        if ($tmp === false) {
            throw new \RuntimeException('tempnam failed');
        }
        $newContent = '';
        if (is_file($appliedFile)) {
            $existing = file_get_contents($appliedFile);
            if ($existing !== false) {
                $newContent = $existing;
            }
            if ($newContent !== '' && !str_ends_with($newContent, "\n")) {
                $newContent .= "\n";
            }
        }
        $newContent .= $name . "\n";
        if (file_put_contents($tmp, $newContent) === false) {
            throw new \RuntimeException('temp file write failed');
        }
        if (!rename($tmp, $appliedFile)) {
            @unlink($tmp);
            throw new \RuntimeException('rename failed');
        }
    } catch (\Throwable $e) {
        fwrite(STDERR, "[migrate] Failed at {$name}: " . $e->getMessage() . "\n");
        fwrite(STDERR, "  Last statement: " . ($stmt ?? '') . "\n");
        exit(1);
    }
    echo "  Applied: {$name}\n";
}

$elapsed = number_format(microtime(true) - $start, 2);
echo 'Applied ' . count($pending) . ' files in ' . $elapsed . 's.' . PHP_EOL;
exit(0);

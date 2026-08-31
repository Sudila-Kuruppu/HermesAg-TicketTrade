<?php
/**
 * PHPUnit bootstrap — defines APP_ROOT before tests run.
 *
 * Per AD-17, the project root is the directory containing composer.json.
 * Tests that load config/*.php or src/Support/* rely on APP_ROOT being
 * defined.
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once APP_ROOT . '/vendor/autoload.php';

<?php
/**
 * TicketTrade — Support\Database PDO Singleton
 *
 * Per AD-5: request-scoped PDO with ERRMODE_EXCEPTION, EMULATE_PREPARES
 * off, DEFAULT_FETCH_MODE FETCH_ASSOC, and utf8mb4 wire charset. The
 * MYSQL_ATTR_INIT_COMMAND hedge sets SET NAMES utf8mb4 even if the DSN
 * omits charset (Pitfall 7).
 *
 * connectFromConfig() is the helper the migrations runner uses so the
 * PDO attributes stay identical to the request-scoped singleton.
 */

declare(strict_types=1);

namespace App\Support;

use PDO;

class Db
{
    private static ?PDO $pdo = null;

    /**
     * Request-scoped PDO singleton.
     *
     * First call opens the connection from config/db.php; subsequent
     * calls reuse the same instance.
     */
    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            // APP_ENV=test → config/db.test.php; otherwise config/db.php.
            $configFile = (getenv('APP_ENV') === 'test')
                ? APP_ROOT . '/config/db.test.php'
                : APP_ROOT . '/config/db.php';
            $config = require $configFile;
            self::$pdo = self::connectFromConfig($config);
        }
        return self::$pdo;
    }

    /**
     * Open a fully configured PDO from a config array.
     *
     * Used by the migrations runner, the CLI, and the request-scoped
     * singleton. The PDO attributes match AD-5 verbatim.
     *
     * @param array{dsn:string,user?:?string,pass?:?string} $config
     */
    public static function connectFromConfig(array $config): PDO
    {
        $dsn = $config['dsn'];
        $pdo = new PDO(
            $dsn,
            $config['user'] ?? null,
            $config['pass'] ?? null,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
            ]
        );
        return $pdo;
    }

    /**
     * Reset the singleton. Tests use this between cases.
     */
    public static function reset(): void
    {
        self::$pdo = null;
    }
}

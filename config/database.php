<?php
/**
 * Database connection (PDO)
 * Valmier Enterprise
 *
 * On InfinityFree, replace the fallback values below with the exact
 * host / database / username / password shown in your control panel
 * (MySQL Databases section) — they look like:
 *   host: sqlXXX.infinityfree.com
 *   name: epizXXXXXXX_valmier
 *   user: epizXXXXXXX_valmier  (same as db name)
 *   pass: whatever you set when creating the database
 */

define('DB_HOST', getenv('VALMIER_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('VALMIER_DB_NAME') ?: 'valmier_db');
define('DB_USER', getenv('VALMIER_DB_USER') ?: 'valmier_app');
define('DB_PASS', getenv('VALMIER_DB_PASS') ?: 'ChangeMe2026!');

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die('Database connection failed. Please try again shortly.');
        }
    }

    return $pdo;
}

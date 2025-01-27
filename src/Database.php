<?php

declare(strict_types=1);

namespace Chat;

require_once dirname(__DIR__) . "/vendor/autoload.php";

use PDO;

class Database extends \PDO
{
    private static $conn;

    public static function getConnection(): PDO
    {
        if (!self::$conn) {
            self::$conn = new PDO('mysql:host=127.0.0.1;dbname=chat;charset=utf8', 'root', '');
            self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        return self::$conn;
    }
}
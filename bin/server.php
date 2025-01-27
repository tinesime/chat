<?php

declare(strict_types=1);

require_once dirname(__DIR__) . "/vendor/autoload.php";

use Chat\Chat;
use Chat\Database;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

$logger = new Logger('chat_app');
$logFile = __DIR__ . '/../logs/chat.log';
$logger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));

$db = Database::getConnection();

$chat = new Chat($db, $logger);

$wsServer = new WsServer($chat);
$httpServer = new HttpServer($wsServer);

$server = IoServer::factory($httpServer, 8080);
echo "WebSocket server started on port 8080\n";
$server->run();
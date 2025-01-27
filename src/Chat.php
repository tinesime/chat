<?php

declare(strict_types=1);

namespace Chat;

require_once dirname(__DIR__) . "/vendor/autoload.php";

use PDO;
use Psr\Log\LoggerInterface;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use SplObjectStorage;

class Chat implements MessageComponentInterface
{
    protected SplObjectStorage $clients;
    protected PDO $db;
    protected LoggerInterface $logger;

    public function __construct(PDO $db, LoggerInterface $logger)
    {
        $this->clients = new SplObjectStorage;
        $this->db = $db;
        $this->logger = $logger;
    }

    function onOpen(ConnectionInterface $conn): void
    {
        $this->logger->debug("New client attempting to connect.");

        $querystring = $conn->httpRequest->getUri()->getQuery();
        parse_str($querystring, $queryParams);

        if (empty($queryParams['session_id'])) {
            $this->logger->warning("No session_id provided, closing connection.");
            $conn->close();
            return;
        }

        $sessionId = $queryParams['session_id'];

        $userId = $this->getUserIdFromSession($sessionId);
        if (!$userId) {
            $this->logger->warning("Invalid session_id: $sessionId. Closing connection.");
            $conn->close();
            return;
        }

        $conn->userId = $userId;

        $this->clients->attach($conn);
        $this->logger->info("New connection: resourceId={$conn->resourceId}, userId={$userId}.");
    }

    function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);
        $this->logger->info("Connection closed: resourceId={$conn->resourceId}.");
    }

    function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $this->logger->error("Connection error: " . $e->getMessage());
        $conn->close();
    }

    function onMessage(ConnectionInterface $from, $msg): void
    {
        $userId = $from->userId;

        $data = json_decode($msg, true);

        if (!$data || !isset($data['toUserId'], $data['text'], $data['fromUserName'])) {
            $this->logger->warning("Invalid message received from server.");
            return;
        }

        $toUserId = $data['toUserId'];
        $text = $data['text'];

        $stmt = $this->db->prepare("
            INSERT INTO chat_messages (from_user_id, to_user_id, message) 
            VALUES (:f, :t, :m)
        ");

        $stmt->execute([
            ':f' => $userId,
            ':t' => $toUserId,
            ':m' => $text
        ]);

        $response = [
            'fromUserId' => $userId,
            'fromUserName' => $data['fromUserName'],
            'toUserId' => $toUserId,
            'text' => $text
        ];

        $jsonResponse = json_encode($response);

        foreach ($this->clients as $client) {
            if (isset($client->userId) && $client->userId == $toUserId) {
                $client->send($jsonResponse);
            }

            if ($client === $from) {
                $client->send($jsonResponse);
            }
        }

        $this->logger->info("User {$userId} sent a message of length " . strlen($msg));
    }

    private function getUserIdFromSession($sessionId)
    {
        $stmt = $this->db->prepare("SELECT user_id FROM user_sessions WHERE session_id = :sid");
        $stmt->execute([':sid' => $sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['user_id'] : false;
    }
}
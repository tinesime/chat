<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Chat\Database;

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit("Not logged in");
}

$currentUserId = (int)$_SESSION['user_id'];

if (!isset($_GET['user_id'])) {
    http_response_code(400);
    exit("Missing user_id");
}
$otherUserId = (int)$_GET['user_id'];

$db = Database::getConnection();

$stmt = $db->prepare("
    SELECT 
        from_user_id,
        to_user_id,
        message,
        created_at 
    FROM chat_messages
    WHERE 
      (from_user_id = :me AND to_user_id = :them)
      OR
      (from_user_id = :them AND to_user_id = :me)
    ORDER BY created_at ASC
");
$stmt->execute([
    ':me' => $currentUserId,
    ':them' => $otherUserId
]);

$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($messages);

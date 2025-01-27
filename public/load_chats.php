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
$db = Database::getConnection();

$stmt = $db->prepare("
    SELECT 
      u.id AS userId, 
      u.username, 
      MAX(cm.created_at) AS lastMessageDate
    FROM chat_messages cm
    JOIN users u 
      ON (u.id = cm.from_user_id OR u.id = cm.to_user_id)
    WHERE 
      cm.from_user_id = :me
      OR
      cm.to_user_id = :me
      AND u.id <> :me
    GROUP BY u.id, u.username
    ORDER BY lastMessageDate DESC
");
$stmt->execute([':me' => $currentUserId]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows);
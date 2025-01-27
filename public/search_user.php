<?php

declare(strict_types=1);

require_once dirname(__DIR__) . "/vendor/autoload.php";

use Chat\Database;

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit("Unauthorized access!");
}

$db = Database::getConnection();

$query = isset($_GET['query']) ? trim($_GET['query']) : "";

if ($query === '') {
    echo json_encode([]);
    exit;
}

$stmt = $db->prepare("SELECT id, username FROM users WHERE username LIKE :q LIMIT 10");
$stmt->execute([':q' => '%'.$query.'%']);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($results);

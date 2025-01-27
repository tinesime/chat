<?php

declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . "/vendor/autoload.php";

use Chat\Database;
use Chat\Auth;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $db = Database::getConnection();

    $auth = new Auth($db);
    $loginResult = $auth->login($username, $password);
    if ($loginResult['success']) {
        $_SESSION['user_id'] = $loginResult['user_id'];
        $_SESSION['username'] = $username;

        $sessionId = session_id();

        $stmt = $db->prepare("INSERT INTO user_sessions (session_id, user_id) VALUES (:sid, :uid)
                          ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), created_at = CURRENT_TIMESTAMP");
        $stmt->execute([':sid' => $sessionId, ':uid' => $loginResult['user_id']]);

        header("Location: index.php");
        exit;
    } else {
        $error = $loginResult['message'];
    }
}

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <title>Login</title>
</head>
<body>
<h2>Login</h2>
<?php if (!empty($error)): ?>
    <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>
<form method="post">
    <label>Benutzername: <input type="text" name="username" required></label><br><br>
    <label>Passwort: <input type="password" name="password" required></label><br><br>
    <button type="submit">Login</button>
</form>
</body>
</html>

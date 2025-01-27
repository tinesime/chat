<?php

declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . "/vendor/autoload.php";

use Chat\Database;
use Chat\Auth;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (strlen($username) < 3 || strlen($password) < 3) {
        $error = "Username and password must be at least 3 characters.";
    } else {
        $auth = new Auth(Database::getConnection());
        $result = $auth->register($username, $password);
        if ($result['success']) {
            header("Location: login.php");
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <title>Register</title>
</head>
<body>
<h2>Register</h2>
<?php if (!empty($error)): ?>
    <p style="color:red;"><?= htmlspecialchars($error); ?></p>
<?php endif; ?>
<form method="post">
    <label>Benutzername: <input type="text" name="username" required></label><br><br>
    <label>Passwort: <input type="password" name="password" required></label><br><br>
    <button type="submit">Register</button>
</form>
</body>
</html>

<?php
session_start();
define('DB_HOST', 'localhost');
define('DB_NAME', 'social_network');
define('DB_USER', 'phpmyadmin');
define('DB_PASS', 'root');
define('UPLOAD_DIR', 'uploads/');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

function cleanExpiredContent($pdo) {
    $pdo->exec("DELETE FROM posts WHERE expires_at < NOW()");
    $pdo->exec("DELETE FROM messages WHERE expires_at < NOW()");
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}
?>
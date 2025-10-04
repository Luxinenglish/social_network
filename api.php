<?php
require_once 'config.php';
requireLogin();
cleanExpiredContent($pdo);

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'get_posts') {
    $last_id = $_GET['last_id'] ?? 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM posts WHERE id > ? AND expires_at > NOW()");
    $stmt->execute([$last_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['posts' => $result['count'] > 0]);
}

if ($action === 'get_messages') {
    $user_id = $_GET['user'] ?? 0;
    $last_id = $_GET['last_id'] ?? 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM messages WHERE id > ? AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) AND expires_at > NOW()");
    $stmt->execute([$last_id, $_SESSION['user_id'], $user_id, $user_id, $_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['new_messages' => $result['count'] > 0]);
}
?>

<?php
require_once 'config.php';
requireLogin();
cleanExpiredContent($pdo);

// Récupérer les utilisateurs
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE id != ?");
$stmt->execute([$_SESSION['user_id']]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Envoyer un message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiver_id = $_POST['receiver_id'];
    $message = trim($_POST['message']);
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $receiver_id, $message, $expires_at]);
}

// Récupérer les messages
$receiver_id = $_GET['user'] ?? null;
if ($receiver_id) {
    $stmt = $pdo->prepare("
        SELECT m.*, u.username as sender_name,
        TIMESTAMPDIFF(SECOND, NOW(), m.expires_at) as seconds_left
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?) 
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$_SESSION['user_id'], $receiver_id, $receiver_id, $_SESSION['user_id']]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Social 24h</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .navbar h1 { font-size: 24px; }
        .navbar a { color: white; text-decoration: none; margin-left: 20px; }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; display: flex; gap: 20px; }
        .users-list { background: white; padding: 20px; border-radius: 15px; width: 300px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .users-list h2 { margin-bottom: 20px; color: #667eea; }
        .user-item { padding: 15px; margin-bottom: 10px; background: #f8f9fa; border-radius: 10px; cursor: pointer; transition: background 0.2s; }
        .user-item:hover { background: #e9ecef; }
        .chat-container { flex: 1; background: white; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; flex-direction: column; height: 600px; }
        .chat-header { padding: 20px; border-bottom: 2px solid #f0f2f5; }
        .messages-area { flex: 1; padding: 20px; overflow-y: auto; }
        .message { margin-bottom: 15px; padding: 12px 16px; border-radius: 12px; max-width: 70%; }
        .message.sent { background: #667eea; color: white; margin-left: auto; }
        .message.received { background: #e9ecef; }
        .message-time { font-size: 11px; opacity: 0.7; margin-top: 5px; }
        .message-form { padding: 20px; border-top: 2px solid #f0f2f5; display: flex; gap: 10px; }
        .message-form input { flex: 1; padding: 12px; border: 2px solid #e0e0e0; border-radius: 25px; }
        .message-form button { background: #667eea; color: white; padding: 12px 25px; border: none; border-radius: 25px; cursor: pointer; }
    </style>
</head>
<body>
<div class="navbar">
    <h1>💬 Messages</h1>
    <div>
        <a href="index.php">🏠 Accueil</a>
        <a href="logout.php">Déconnexion</a>
    </div>
</div>

<div class="container">
    <div class="users-list">
        <h2>Utilisateurs</h2>
        <?php foreach($users as $user): ?>
            <div class="user-item" onclick="location.href='messages.php?user=<?= $user['id'] ?>'">
                👤 <?= htmlspecialchars($user['username']) ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if(isset($receiver_id)): ?>
        <div class="chat-container">
            <div class="chat-header">
                <h2>💬 Conversation</h2>
            </div>
            <div class="messages-area" id="messages-area">
                <?php foreach($messages as $msg): ?>
                    <div class="message <?= $msg['sender_id'] == $_SESSION['user_id'] ? 'sent' : 'received' ?>" data-expires="<?= $msg['seconds_left'] ?>">
                        <div><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                        <div class="message-time timer"></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <form class="message-form" method="POST">
                <input type="hidden" name="receiver_id" value="<?= $receiver_id ?>">
                <input type="text" name="message" placeholder="Écrivez un message..." required>
                <button type="submit" name="send_message">Envoyer</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
    function updateTimers() {
        document.querySelectorAll('.message').forEach(msg => {
            let seconds = parseInt(msg.dataset.expires);
            if (seconds <= 0) {
                msg.remove();
                return;
            }

            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;

            const timer = msg.querySelector('.timer');
            timer.textContent = `⏱️ ${hours}h ${minutes}m ${secs}s`;

            msg.dataset.expires = seconds - 1;
        });
    }

    updateTimers();
    setInterval(updateTimers, 1000);

    // Auto-scroll et refresh temps réel
    const messagesArea = document.getElementById('messages-area');
    if (messagesArea) {
        messagesArea.scrollTop = messagesArea.scrollHeight;

        const receiverId = <?= $receiver_id ?? 'null' ?>;
        if (receiverId) {
            setInterval(() => {
                fetch(`api.php?action=get_messages&user=${receiverId}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.new_messages) {
                            location.reload();
                        }
                    });
            }, 2000);
        }
    }
</script>
</body>
</html>
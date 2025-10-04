
<?php
require_once 'config.php';
requireLogin();
cleanExpiredContent($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'post') {
        $content = trim($_POST['content']);
        $image_path = null;

        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
                $new_name = uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . $new_name);
                $image_path = UPLOAD_DIR . $new_name;
            }
        }

        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $stmt = $pdo->prepare("INSERT INTO posts (user_id, content, image_path, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $content, $image_path, $expires_at]);
    }
}

$stmt = $pdo->query("SELECT p.*, u.username, 
    TIMESTAMPDIFF(SECOND, NOW(), p.expires_at) as seconds_left 
    FROM posts p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.expires_at > NOW() 
    ORDER BY p.created_at DESC");
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social 24h - Accueil</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .navbar h1 { font-size: 24px; }
        .navbar a { color: white; text-decoration: none; margin-left: 20px; font-weight: 500; }
        .container { max-width: 800px; margin: 20px auto; padding: 0 20px; }
        .post-form { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .post-form textarea { width: 100%; padding: 15px; border: 2px solid #e0e0e0; border-radius: 10px; resize: vertical; min-height: 100px; font-family: inherit; font-size: 14px; }
        .post-form input[type="file"] { margin: 15px 0; }
        .post-form button { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px; }
        .post { background: white; padding: 25px; border-radius: 15px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .post-header { display: flex; justify-content: space-between; margin-bottom: 15px; }
        .post-author { font-weight: 600; color: #667eea; }
        .post-time { color: #999; font-size: 13px; }
        .post-content { margin: 15px 0; line-height: 1.6; }
        .post-image { width: 100%; border-radius: 10px; margin-top: 15px; }
        .timer { background: #fee; color: #c33; padding: 8px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; }
    </style>
</head>
<body>
<div class="navbar">
    <h1>📱 Social 24h</h1>
    <div>
        <span>Bonjour, <?= htmlspecialchars($_SESSION['username']) ?></span>
        <a href="messages.php">💬 Messages</a>
        <a href="logout.php">Déconnexion</a>
    </div>
</div>

<div class="container">
    <div class="post-form">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="post">
            <textarea name="content" placeholder="Quoi de neuf ? (Disparaît dans 24h)"></textarea>
            <input type="file" name="image" accept="image/*">
            <button type="submit">📤 Publier</button>
        </form>
    </div>

    <div id="posts-container">
        <?php foreach($posts as $post): ?>
            <div class="post" data-expires="<?= $post['seconds_left'] ?>">
                <div class="post-header">
                    <span class="post-author">👤 <?= htmlspecialchars($post['username']) ?></span>
                    <span class="timer"></span>
                </div>
                <?php if($post['content']): ?>
                    <div class="post-content"><?= nl2br(htmlspecialchars($post['content'])) ?></div>
                <?php endif; ?>
                <?php if($post['image_path']): ?>
                    <img src="<?= htmlspecialchars($post['image_path']) ?>" class="post-image" alt="Image">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    function updateTimers() {
        document.querySelectorAll('.post').forEach(post => {
            let seconds = parseInt(post.dataset.expires);
            if (seconds <= 0) {
                post.remove();
                return;
            }

            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;

            const timer = post.querySelector('.timer');
            timer.textContent = `⏱️ ${hours}h ${minutes}m ${secs}s`;

            post.dataset.expires = seconds - 1;
        });
    }

    updateTimers();
    setInterval(updateTimers, 1000);

    // Auto-refresh pour nouveaux posts
    setInterval(() => {
        fetch('api.php?action=get_posts')
            .then(r => r.json())
            .then(data => {
                if (data.posts && data.posts.length > 0) {
                    location.reload();
                }
            });
    }, 5000);
</script>
</body>
</html>
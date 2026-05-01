<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Получение постов от подписок
$stmt = $pdo->prepare("
    SELECT p.*, u.username, 
           (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
    FROM posts p
    JOIN users u ON p.user_id = u.id
    JOIN subscriptions s ON s.subscribed_to_id = p.user_id
    WHERE s.subscriber_id = ? AND p.visibility IN ('public', 'request')
    ORDER BY p.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$posts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Feed - Blog App</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container">
        <h2>Posts from People You Follow</h2>
        <?php foreach($posts as $post): ?>
            <div class="post">
                <h3><a href="post.php?id=<?= $post['id'] ?>"><?= htmlspecialchars($post['title']) ?></a></h3>
                <p class="meta">By <?= htmlspecialchars($post['username']) ?> | <?= $post['created_at'] ?></p>
                <p><?= substr(htmlspecialchars($post['content']), 0, 200) ?>...</p>
                <p>💬 Comments: <?= $post['comment_count'] ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
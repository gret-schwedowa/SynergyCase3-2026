<?php
session_start();
require_once '../config/database.php';

$search = isset($_GET['q']) ? $_GET['q'] : '';
$tag = isset($_GET['tag']) ? $_GET['tag'] : '';

$query = "
    SELECT DISTINCT p.*, u.username,
           (SELECT GROUP_CONCAT(t.name) FROM post_tags pt JOIN tags t ON pt.tag_id = t.id WHERE pt.post_id = p.id) as tags
    FROM posts p
    JOIN users u ON p.user_id = u.id
    WHERE p.visibility = 'public'
";

$params = [];

if($search) {
    $query .= " AND (p.title LIKE ? OR p.content LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if($tag) {
    $query .= " AND EXISTS (SELECT 1 FROM post_tags pt JOIN tags t ON pt.tag_id = t.id WHERE pt.post_id = p.id AND t.name = ?)";
    $params[] = $tag;
}

$query .= " ORDER BY p.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$posts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Поиск - <?= htmlspecialchars($search ?: $tag) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <h1>Результаты поиска: <?= htmlspecialchars($search ?: "#$tag") ?></h1>
        <a href="index.php" class="back-link">← На главную</a>
        
        <?php if(count($posts) == 0): ?>
            <div class="no-posts">По вашему запросу ничего не найдено.</div>
        <?php else: ?>
            <?php foreach($posts as $post): ?>
                <div class="post-card">
                    <h2><a href="post.php?id=<?= $post['id'] ?>"><?= htmlspecialchars($post['title']) ?></a></h2>
                    <div class="post-meta">
                        Автор: <?= htmlspecialchars($post['username']) ?> | 
                        Дата: <?= date('d.m.Y H:i', strtotime($post['created_at'])) ?>
                    </div>
                    <div class="post-excerpt">
                        <?= nl2br(htmlspecialchars(substr($post['content'], 0, 300))) ?>...
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
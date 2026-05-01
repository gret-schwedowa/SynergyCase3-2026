<?php
session_start();
require_once '../config/database.php';

// Получение публичных постов с тегами
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$tag_filter = isset($_GET['tag']) ? $_GET['tag'] : '';

if ($tag_filter) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.*, u.username,
               (SELECT GROUP_CONCAT(t.name) FROM post_tags pt JOIN tags t ON pt.tag_id = t.id WHERE pt.post_id = p.id) as tags
        FROM posts p
        JOIN users u ON p.user_id = u.id
        JOIN post_tags pt ON p.id = pt.post_id
        JOIN tags t ON pt.tag_id = t.id
        WHERE p.visibility = 'public' AND t.name = ?
        ORDER BY p.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute([$tag_filter]);
} else {
    $stmt = $pdo->prepare("
        SELECT p.*, u.username,
               (SELECT GROUP_CONCAT(t.name) FROM post_tags pt JOIN tags t ON pt.tag_id = t.id WHERE pt.post_id = p.id) as tags,
               (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.visibility = 'public'
        ORDER BY p.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute();
}
$posts = $stmt->fetchAll();

// Получение всех тегов для фильтра
$stmt = $pdo->query("SELECT name FROM tags ORDER BY name");
$all_tags = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog - Главная</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="main-content">
            <div class="posts-section">
                <h1>Публикации</h1>
                
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                
                <?php if($tag_filter): ?>
                    <div class="filter-info">
                        Фильтр по тегу: <strong><?= htmlspecialchars($tag_filter) ?></strong>
                        <a href="index.php" class="clear-filter">✕ Очистить</a>
                    </div>
                <?php endif; ?>
                
                <?php if(count($posts) == 0): ?>
                    <div class="no-posts">Публикации не найдены.</div>
                <?php else: ?>
                    <?php foreach($posts as $post): ?>
                        <div class="post-card">
                            <h2><a href="post.php?id=<?= $post['id'] ?>"><?= htmlspecialchars($post['title']) ?></a></h2>
                            <div class="post-meta">
    Автор: <strong><a href="profile.php?id=<?= $post['user_id'] ?>"><?= htmlspecialchars($post['username']) ?></a></strong> | 
    Дата: <?= date('d.m.Y H:i', strtotime($post['created_at'])) ?>
</div>
                            <div class="post-excerpt">
                                <?= nl2br(htmlspecialchars(substr($post['content'], 0, 300))) ?>...
                            </div>
                            <?php if($post['tags']): ?>
                                <div class="post-tags">
                                    Теги: 
                                    <?php 
                                    $tags = explode(',', $post['tags']);
                                    foreach($tags as $tag): 
                                    ?>
                                        <a href="index.php?tag=<?= urlencode(trim($tag)) ?>" class="tag">#<?= htmlspecialchars(trim($tag)) ?></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="post-stats">
                                💬 Комментариев: <?= $post['comment_count'] ?? 0 ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="sidebar">
                <div class="widget">
                    <h3>Популярные теги</h3>
                    <div class="tag-cloud">
                        <?php foreach($all_tags as $tag): ?>
                            <a href="index.php?tag=<?= urlencode($tag['name']) ?>" class="tag">#<?= htmlspecialchars($tag['name']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <?php if(isset($_SESSION['user_id'])): ?>
                    <div class="widget">
                        <h3>Быстрые действия</h3>
                        <ul class="quick-actions">
                            <li><a href="create_post.php">✍️ Создать пост</a></li>
                            <li><a href="profile.php">👤 Мой профиль</a></li>
                            <li><a href="feed.php">📰 Моя лента</a></li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
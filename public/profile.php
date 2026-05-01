<?php
session_start();
require_once '../config/database.php';

// Получаем ID пользователя
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Если ID не указан и пользователь авторизован - показываем свой профиль
if($user_id == 0 && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
}

if($user_id == 0) {
    die("Пользователь не указан");
}

// Получаем информацию о пользователе
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$profile_user = $stmt->fetch();

if(!$profile_user) {
    die("Пользователь не найден");
}

// Проверяем, смотрим ли мы свой профиль
$is_own_profile = (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id);

// Проверка подписки
$is_subscribed = false;
if(isset($_SESSION['user_id']) && !$is_own_profile) {
    $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE subscriber_id = ? AND subscribed_to_id = ?");
    $stmt->execute([$_SESSION['user_id'], $user_id]);
    $is_subscribed = $stmt->fetch() ? true : false;
}

// ВАЖНО: Получаем посты пользователя с учетом видимости
if($is_own_profile) {
    // Если смотрим свой профиль - видим ВСЕ посты (public, private, request)
    $stmt = $pdo->prepare("
        SELECT p.*, 
               (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
        FROM posts p 
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$user_id]);
} else {
    // Если смотрим чужой профиль - видим только public посты
    $stmt = $pdo->prepare("
        SELECT p.*, 
               (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
        FROM posts p 
        WHERE p.user_id = ? AND p.visibility = 'public'
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$user_id]);
}
$user_posts = $stmt->fetchAll();

// Подсчет статистики
// Посты (для чужого профиля считаем только публичные)
if($is_own_profile) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $posts_count = $stmt->fetchColumn();
} else {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ? AND visibility = 'public'");
    $stmt->execute([$user_id]);
    $posts_count = $stmt->fetchColumn();
}

// Подписчики
$stmt = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE subscribed_to_id = ?");
$stmt->execute([$user_id]);
$followers_count = $stmt->fetchColumn();

// Подписки
$stmt = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE subscriber_id = ?");
$stmt->execute([$user_id]);
$following_count = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($profile_user['username']) ?> - Профиль</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="profile-header">
            <div class="profile-avatar">
                <div class="avatar-placeholder">
                    <?= strtoupper(substr($profile_user['username'], 0, 1)) ?>
                </div>
            </div>
            <div class="profile-info">
                <h1><?= htmlspecialchars($profile_user['username']) ?></h1>
                <div class="profile-stats">
                    <span>📝 Постов: <?= $posts_count ?></span>
                    <span>👥 Подписчиков: <?= $followers_count ?></span>
                    <span>🔗 Подписок: <?= $following_count ?></span>
                </div>
                <?php if(isset($_SESSION['user_id']) && !$is_own_profile): ?>
                    <form method="POST" action="subscribe.php">
                        <input type="hidden" name="user_id" value="<?= $user_id ?>">
                        <input type="hidden" name="action" value="<?= $is_subscribed ? 'unsubscribe' : 'subscribe' ?>">
                        <button type="submit" class="subscribe-btn <?= $is_subscribed ? 'subscribed' : '' ?>">
                            <?= $is_subscribed ? 'Отписаться' : 'Подписаться' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="user-posts">
            <h2>
                <?= $is_own_profile ? 'Мои посты' : 'Публичные посты пользователя' ?>
            </h2>
            
            <?php if($is_own_profile): ?>
                <div class="post-filter">
                    <a href="?id=<?= $user_id ?>&filter=all" class="filter-btn">Все</a>
                    <a href="?id=<?= $user_id ?>&filter=public" class="filter-btn">Публичные</a>
                    <a href="?id=<?= $user_id ?>&filter=private" class="filter-btn">Приватные</a>
                    <a href="?id=<?= $user_id ?>&filter=request" class="filter-btn">По запросу</a>
                </div>
            <?php endif; ?>
            
            <?php if(count($user_posts) == 0): ?>
                <div class="no-posts">
                    <?= $is_own_profile ? 'У вас пока нет постов.' : 'У пользователя нет публичных постов.' ?>
                    <?php if($is_own_profile): ?>
                        <br><a href="create_post.php" class="button">Создать первый пост</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach($user_posts as $post): ?>
                    <div class="post-card">
                        <h3><a href="post.php?id=<?= $post['id'] ?>"><?= htmlspecialchars($post['title']) ?></a></h3>
                        <div class="post-meta">
                            Дата: <?= date('d.m.Y H:i', strtotime($post['created_at'])) ?>
                            <?php if($post['visibility'] == 'private'): ?>
                                <span class="badge private">🔒 Приватный</span>
                            <?php elseif($post['visibility'] == 'request'): ?>
                                <span class="badge request">📨 По запросу</span>
                            <?php elseif($post['visibility'] == 'public'): ?>
                                <span class="badge public">🌍 Публичный</span>
                            <?php endif; ?>
                        </div>
                        <div class="post-excerpt">
                            <?= nl2br(htmlspecialchars(substr($post['content'], 0, 200))) ?>...
                        </div>
                        <div class="post-stats">💬 Комментариев: <?= $post['comment_count'] ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
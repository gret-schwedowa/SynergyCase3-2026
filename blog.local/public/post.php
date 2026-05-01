<?php
session_start();
require_once '../config/database.php';

$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($post_id == 0) {
    die("Пост не найден");
}

// ОБРАБОТКА ЗАПРОСА НА ДОСТУП
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_access'])) {
    if(!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
    
    // Проверяем, не отправлял ли уже запрос
    $stmt = $pdo->prepare("SELECT * FROM post_requests WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$post_id, $_SESSION['user_id']]);
    $existing = $stmt->fetch();
    
    if(!$existing) {
        $stmt = $pdo->prepare("INSERT INTO post_requests (post_id, user_id, status) VALUES (?, ?, 'pending')");
        if($stmt->execute([$post_id, $_SESSION['user_id']])) {
            $_SESSION['success'] = "Запрос на доступ отправлен автору!";
        } else {
            $_SESSION['error'] = "Ошибка при отправке запроса";
        }
    } else {
        $_SESSION['error'] = "Вы уже отправляли запрос на этот пост (статус: {$existing['status']})";
    }
    header("Location: post.php?id=" . $post_id);
    exit();
}

// ОБРАБОТКА КОММЕНТАРИЯ
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['comment'])) {
    if(!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
    $comment = trim($_POST['comment']);
    if($comment) {
        $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$post_id, $_SESSION['user_id'], $comment]);
        $_SESSION['success'] = "Комментарий добавлен";
    }
    header("Location: post.php?id=" . $post_id);
    exit();
}

// ПОЛУЧЕНИЕ ПОСТА
$stmt = $pdo->prepare("
    SELECT p.*, u.username 
    FROM posts p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.id = ?
");
$stmt->execute([$post_id]);
$post = $stmt->fetch();

if(!$post) {
    die("Пост не найден");
}

// ПРОВЕРКА ДОСТУПА
$can_view = false;
$can_request = false;
$request_status = null;
$is_author = false;

if(isset($_SESSION['user_id'])) {
    $is_author = ($post['user_id'] == $_SESSION['user_id']);
}

// PUBLIC - видят все
if($post['visibility'] == 'public') {
    $can_view = true;
}

// PRIVATE - только автор
if($post['visibility'] == 'private' && $is_author) {
    $can_view = true;
}

// REQUEST - по запросу
if($post['visibility'] == 'request') {
    if($is_author) {
        $can_view = true; // Автор всегда видит
    } elseif(isset($_SESSION['user_id'])) {
        // Проверяем статус запроса
        $stmt = $pdo->prepare("SELECT * FROM post_requests WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$post_id, $_SESSION['user_id']]);
        $request = $stmt->fetch();
        
        if($request) {
            if($request['status'] == 'approved') {
                $can_view = true;
            } else {
                $request_status = $request['status'];
            }
        } else {
            $can_request = true;
        }
    } elseif(!isset($_SESSION['user_id'])) {
        $can_request = false;
    }
}

// Получаем комментарии
$comments = [];
if($can_view) {
    $stmt = $pdo->prepare("
        SELECT c.*, u.username 
        FROM comments c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.post_id = ? 
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$post_id]);
    $comments = $stmt->fetchAll();
}

// ВАЖНО: Получаем ВСЕ запросы для автора (не только pending)
$all_requests = [];
if($is_author && $post['visibility'] == 'request') {
    $stmt = $pdo->prepare("
        SELECT pr.*, u.username 
        FROM post_requests pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.post_id = ?
        ORDER BY pr.created_at DESC
    ");
    $stmt->execute([$post_id]);
    $all_requests = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($post['title']) ?> - Блог</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <?php if(isset($_SESSION['success'])): ?>
            <div class="success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <?php if($can_view): ?>
            <!-- ПОКАЗЫВАЕМ ПОСТ -->
            <article class="post-full">
                <h1><?= htmlspecialchars($post['title']) ?></h1>
                <div class="post-meta">
                    Автор: <?= htmlspecialchars($post['username']) ?> | 
                    Дата: <?= date('d.m.Y H:i', strtotime($post['created_at'])) ?>
                    <?php if($post['visibility'] == 'private'): ?>
                        <span class="badge private">🔒 Приватный</span>
                    <?php elseif($post['visibility'] == 'request'): ?>
                        <span class="badge request">📨 Пост по запросу</span>
                    <?php endif; ?>
                </div>
                <div class="post-content">
                    <?= nl2br(htmlspecialchars($post['content'])) ?>
                </div>
                
                <?php if($is_author): ?>
                    <div class="admin-actions">
                        <a href="edit_post.php?id=<?= $post['id'] ?>" class="button">✏️ Редактировать</a>
                        <a href="delete_post.php?id=<?= $post['id'] ?>" class="button danger" onclick="return confirm('Удалить пост?')">🗑️ Удалить</a>
                    </div>
                <?php endif; ?>
            </article>
            
            <!-- КОММЕНТАРИИ -->
            <div class="comments-section">
                <h3>Комментарии (<?= count($comments) ?>)</h3>
                
                <?php if(isset($_SESSION['user_id'])): ?>
                    <form method="POST" class="comment-form">
                        <textarea name="comment" rows="3" placeholder="Написать комментарий..." required></textarea>
                        <button type="submit">💬 Отправить</button>
                    </form>
                <?php else: ?>
                    <p><a href="login.php">Войдите</a> чтобы оставить комментарий</p>
                <?php endif; ?>
                
                <?php foreach($comments as $comment): ?>
                    <div class="comment">
                        <strong><?= htmlspecialchars($comment['username']) ?></strong>
                        <small><?= date('d.m.Y H:i', strtotime($comment['created_at'])) ?></small>
                        <p><?= nl2br(htmlspecialchars($comment['content'])) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            
        <?php elseif($can_request): ?>
            <!-- ФОРМА ЗАПРОСА ДОСТУПА -->
            <div class="access-request-form">
                <h2>🔒 Доступ по запросу</h2>
                <p>Этот пост доступен только после одобрения автора.</p>
                <p>Автор: <strong><?= htmlspecialchars($post['username']) ?></strong></p>
                
                <form method="POST">
                    <input type="hidden" name="request_access" value="1">
                    <button type="submit" class="request-btn">📨 Запросить доступ у автора</button>
                </form>
            </div>
            
        <?php elseif($request_status == 'pending'): ?>
            <div class="access-pending">
                <h2>⏳ Запрос отправлен</h2>
                <p>Ваш запрос на доступ отправлен автору. Когда автор одобрит его, вы сможете увидеть содержимое поста.</p>
            </div>
            
        <?php elseif($request_status == 'denied'): ?>
            <div class="access-denied">
                <h2>🚫 Доступ отклонен</h2>
                <p>Автор отклонил ваш запрос на доступ к этому посту.</p>
            </div>
            
        <?php else: ?>
            <div class="access-denied">
                <h2>🚫 Нет доступа</h2>
                <p>У вас нет прав для просмотра этого поста.</p>
                <?php if(!isset($_SESSION['user_id'])): ?>
                    <p><a href="login.php">Войдите</a> в систему, чтобы отправить запрос автору.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- ОТОБРАЖЕНИЕ ЗАПРОСОВ ДЛЯ АВТОРА (ВИДНО ТОЛЬКО АВТОРУ) -->
        <?php if($is_author && $post['visibility'] == 'request'): ?>
            <div class="pending-requests">
                <h3>📬 Управление запросами на доступ</h3>
                <?php if(count($all_requests) == 0): ?>
                    <p>Нет запросов на доступ к этому посту.</p>
                <?php else: ?>
                    <?php foreach($all_requests as $req): ?>
                        <div class="request-item">
                            <div class="request-info">
                                <strong><?= htmlspecialchars($req['username']) ?></strong>
                                <span class="request-status status-<?= $req['status'] ?>">
                                    <?php
                                    if($req['status'] == 'pending') echo '⏳ Ожидает';
                                    elseif($req['status'] == 'approved') echo '✅ Одобрен';
                                    else echo '❌ Отклонен';
                                    ?>
                                </span>
                                <small>Запрошено: <?= date('d.m.Y H:i', strtotime($req['created_at'])) ?></small>
                            </div>
                            <?php if($req['status'] == 'pending'): ?>
                                <div class="request-actions">
                                    <form method="POST" action="approve_request.php" style="display: inline;">
                                        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                        <input type="hidden" name="post_id" value="<?= $post_id ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="approve-btn">✅ Одобрить</button>
                                    </form>
                                    <form method="POST" action="approve_request.php" style="display: inline;">
                                        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                        <input type="hidden" name="post_id" value="<?= $post_id ?>">
                                        <input type="hidden" name="action" value="deny">
                                        <button type="submit" class="deny-btn">❌ Отклонить</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
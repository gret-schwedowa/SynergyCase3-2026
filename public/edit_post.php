<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

$post_id = $_GET['id'];

// Проверка прав
$stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? AND user_id = ?");
$stmt->execute([$post_id, $_SESSION['user_id']]);
$post = $stmt->fetch();

if(!$post) {
    die("Пост не найден или у вас нет прав на его редактирование");
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $visibility = $_POST['visibility'];
    
    $stmt = $pdo->prepare("UPDATE posts SET title = ?, content = ?, visibility = ? WHERE id = ?");
    $stmt->execute([$title, $content, $visibility, $post_id]);
    
    // Обновление тегов
    $stmt = $pdo->prepare("DELETE FROM post_tags WHERE post_id = ?");
    $stmt->execute([$post_id]);
    
    $tags = explode(',', $_POST['tags']);
    foreach($tags as $tag_name) {
        $tag_name = trim($tag_name);
        if($tag_name) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO tags (name) VALUES (?)");
            $stmt->execute([$tag_name]);
            $tag_id = $pdo->lastInsertId();
            if(!$tag_id) {
                $stmt = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
                $stmt->execute([$tag_name]);
                $tag_id = $stmt->fetchColumn();
            }
            $stmt = $pdo->prepare("INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)");
            $stmt->execute([$post_id, $tag_id]);
        }
    }
    
    $_SESSION['success'] = "Пост успешно обновлен";
    header("Location: post.php?id=" . $post_id);
    exit();
}

// Получение текущих тегов
$stmt = $pdo->prepare("
    SELECT GROUP_CONCAT(t.name) as tags 
    FROM post_tags pt 
    JOIN tags t ON pt.tag_id = t.id 
    WHERE pt.post_id = ?
");
$stmt->execute([$post_id]);
$current_tags = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактирование поста</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <h1>Редактирование поста</h1>
        <form method="POST">
            <input type="text" name="title" value="<?= htmlspecialchars($post['title']) ?>" required>
            <textarea name="content" rows="10" required><?= htmlspecialchars($post['content']) ?></textarea>
            
            <select name="visibility">
                <option value="public" <?= $post['visibility'] == 'public' ? 'selected' : '' ?>>Публичный</option>
                <option value="private" <?= $post['visibility'] == 'private' ? 'selected' : '' ?>>Приватный</option>
                <option value="request" <?= $post['visibility'] == 'request' ? 'selected' : '' ?>>По запросу</option>
            </select>
            
            <input type="text" name="tags" value="<?= htmlspecialchars($current_tags) ?>" placeholder="Теги (через запятую)">
            
            <button type="submit">Сохранить изменения</button>
            <a href="post.php?id=<?= $post_id ?>" class="button">Отмена</a>
        </form>
    </div>
</body>
</html>
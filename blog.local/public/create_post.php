<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $visibility = $_POST['visibility'];
    $tags = explode(',', $_POST['tags']);
    
    // Создание поста
    $stmt = $pdo->prepare("INSERT INTO posts (user_id, title, content, visibility) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $title, $content, $visibility]);
    $post_id = $pdo->lastInsertId();
    
    // Добавление тегов
    foreach($tags as $tag_name) {
        $tag_name = trim($tag_name);
        if($tag_name) {
            // Вставка или получение тега
            $stmt = $pdo->prepare("INSERT IGNORE INTO tags (name) VALUES (?)");
            $stmt->execute([$tag_name]);
            $tag_id = $pdo->lastInsertId();
            if(!$tag_id) {
                $stmt = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
                $stmt->execute([$tag_name]);
                $tag_id = $stmt->fetchColumn();
            }
            // Связь поста с тегом
            $stmt = $pdo->prepare("INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)");
            $stmt->execute([$post_id, $tag_id]);
        }
    }
    
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Post - Blog App</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container">
        <h2>Create New Post</h2>
        <form method="POST">
            <input type="text" name="title" placeholder="Post Title" required>
            <textarea name="content" rows="10" placeholder="Write your post content here..." required></textarea>
            
            <select name="visibility">
                <option value="public">Public (Everyone can see)</option>
                <option value="private">Private (Only you)</option>
                <option value="request">By Request (Others need to request)</option>
            </select>
            
            <input type="text" name="tags" placeholder="Tags (comma-separated, e.g., php, programming, web)">
            
            <button type="submit">Publish Post</button>
        </form>
    </div>
</body>
</html>
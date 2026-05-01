<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $post_id = $_POST['post_id'];
    
    $stmt = $pdo->prepare("INSERT INTO post_requests (post_id, user_id, status) VALUES (?, ?, 'pending')");
    $stmt->execute([$post_id, $_SESSION['user_id']]);
    
    $_SESSION['success'] = "Запрос на доступ отправлен автору";
    header("Location: post.php?id=" . $post_id);
    exit();
}
?>
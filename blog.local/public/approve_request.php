<?php
session_start();
require_once '../config/database.php';

// Проверка авторизации
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
    $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if($request_id == 0 || $post_id == 0 || !in_array($action, ['approve', 'deny'])) {
        $_SESSION['error'] = "Неверные параметры запроса";
        header("Location: post.php?id=" . $post_id);
        exit();
    }
    
    // Проверяем, что текущий пользователь - автор поста
    $stmt = $pdo->prepare("
        SELECT p.user_id 
        FROM posts p 
        WHERE p.id = ?
    ");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();
    
    if(!$post || $post['user_id'] != $_SESSION['user_id']) {
        $_SESSION['error'] = "У вас нет прав для обработки этого запроса";
        header("Location: post.php?id=" . $post_id);
        exit();
    }
    
    // Обновляем статус запроса
    $new_status = ($action == 'approve') ? 'approved' : 'denied';
    $stmt = $pdo->prepare("
        UPDATE post_requests 
        SET status = ? 
        WHERE id = ? AND post_id = ?
    ");
    
    if($stmt->execute([$new_status, $request_id, $post_id])) {
        $_SESSION['success'] = ($action == 'approve') 
            ? "✅ Доступ предоставлен пользователю" 
            : "❌ Доступ отклонен";
    } else {
        $_SESSION['error'] = "Ошибка при обработке запроса";
    }
    
    header("Location: post.php?id=" . $post_id);
    exit();
} else {
    header("Location: index.php");
    exit();
}
?>
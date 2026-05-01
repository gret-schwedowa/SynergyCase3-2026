<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_POST['user_id'];
    $action = $_POST['action'];
    
    if($action == 'subscribe') {
        $stmt = $pdo->prepare("INSERT INTO subscriptions (subscriber_id, subscribed_to_id) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], $user_id]);
        $_SESSION['success'] = "Вы подписались на пользователя";
    } elseif($action == 'unsubscribe') {
        $stmt = $pdo->prepare("DELETE FROM subscriptions WHERE subscriber_id = ? AND subscribed_to_id = ?");
        $stmt->execute([$_SESSION['user_id'], $user_id]);
        $_SESSION['success'] = "Вы отписались от пользователя";
    }
    
    header("Location: profile.php?id=" . $user_id);
    exit();
}
?>
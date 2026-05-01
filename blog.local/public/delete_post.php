<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

$post_id = $_GET['id'];

$stmt = $pdo->prepare("DELETE FROM posts WHERE id = ? AND user_id = ?");
$stmt->execute([$post_id, $_SESSION['user_id']]);

if($stmt->rowCount() > 0) {
    $_SESSION['success'] = "Пост успешно удален";
} else {
    $_SESSION['error'] = "Не удалось удалить пост";
}

header("Location: index.php");
exit();
?>
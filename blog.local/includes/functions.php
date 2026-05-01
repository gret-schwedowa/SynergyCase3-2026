<?php
/**
 * Базовые вспомогательные функции для блога
 */

/**
 * Проверка авторизации пользователя
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Получение ID текущего пользователя
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Получение имени текущего пользователя
 */
function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}

/**
 * Перенаправление на страницу
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Отображение сообщения об успехе
 */
function setSuccess($message) {
    $_SESSION['success'] = $message;
}

/**
 * Отображение сообщения об ошибке
 */
function setError($message) {
    $_SESSION['error'] = $message;
}

/**
 * Получение и очистка сообщения об успехе
 */
function getSuccess() {
    if (isset($_SESSION['success'])) {
        $message = $_SESSION['success'];
        unset($_SESSION['success']);
        return $message;
    }
    return null;
}

/**
 * Получение и очистка сообщения об ошибке
 */
function getError() {
    if (isset($_SESSION['error'])) {
        $message = $_SESSION['error'];
        unset($_SESSION['error']);
        return $message;
    }
    return null;
}

/**
 * Безопасное экранирование данных для вывода в HTML
 */
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Обрезка текста до определенной длины
 */
function truncate($text, $length = 200) {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    $text = substr($text, 0, $length);
    $last_space = strrpos($text, ' ');
    
    if ($last_space !== false) {
        $text = substr($text, 0, $last_space);
    }
    
    return $text . '...';
}

/**
 * Форматирование даты
 */
function formatDate($datetime) {
    $timestamp = strtotime($datetime);
    return date('d.m.Y H:i', $timestamp);
}

/**
 * Получение постов пользователя
 */
function getUserPosts($pdo, $user_id, $limit = null) {
    $sql = "SELECT * FROM posts WHERE user_id = ? ORDER BY created_at DESC";
    
    if ($limit) {
        $sql .= " LIMIT " . (int)$limit;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

/**
 * Получение всех тегов поста
 */
function getPostTags($pdo, $post_id) {
    $stmt = $pdo->prepare("
        SELECT t.name 
        FROM tags t
        JOIN post_tags pt ON t.id = pt.tag_id
        WHERE pt.post_id = ?
    ");
    $stmt->execute([$post_id]);
    return $stmt->fetchAll();
}

/**
 * Проверка, подписан ли пользователь
 */
function isSubscribed($pdo, $subscriber_id, $subscribed_to_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM subscriptions 
        WHERE subscriber_id = ? AND subscribed_to_id = ?
    ");
    $stmt->execute([$subscriber_id, $subscribed_to_id]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Получение количества комментариев к посту
 */
function getCommentCount($pdo, $post_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE post_id = ?");
    $stmt->execute([$post_id]);
    return $stmt->fetchColumn();
}

/**
 * Получение последних публичных постов
 */
function getRecentPosts($pdo, $limit = 10) {
    $stmt = $pdo->prepare("
        SELECT p.*, u.username 
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.visibility = 'public'
        ORDER BY p.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/**
 * Валидация email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Генерация случайной строки (для CSRF токенов)
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length));
}
?>
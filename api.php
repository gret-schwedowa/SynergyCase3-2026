<?php
require_once 'config.php';

$request = $_SERVER['REQUEST_METHOD'];
$path = isset($_GET['route']) ? explode('/', trim($_GET['route'], '/')) : [];

// ========== АУТЕНТИФИКАЦИЯ ==========
function authUser($pdo) {
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
    if (!$token) return null;
    
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ========== РЕГИСТРАЦИЯ ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['route']) && $_GET['route'] === 'register') {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'] ?? '';
    $email = $data['email'] ?? '';
    $password = password_hash($data['password'] ?? '', PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$username, $email, $password]);
        echo json_encode(['success' => true, 'message' => 'User created']);
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Username or email already exists']);
    }
    exit;
}

// ========== ВХОД ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['route']) && $_GET['route'] === 'login') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        echo json_encode(['success' => true, 'token' => $user['id'], 'username' => $user['username']]);
    } else {
        echo json_encode(['error' => 'Invalid credentials']);
    }
    exit;
}

// ========== ПРОВЕРКА ТОКЕНА ДЛЯ ВСЕХ ОСТАЛЬНЫХ ЗАПРОСОВ ==========
$user = authUser($pdo);
if (!$user && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ========== СОЗДАНИЕ ПОСТА ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['route']) && $_GET['route'] === 'posts') {
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("INSERT INTO posts (title, content, author_id, tags, visibility) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['title'],
        $data['content'],
        $user['id'],
        implode(',', $data['tags'] ?? []),
        $data['visibility'] ?? 'public'
    ]);
    echo json_encode(['success' => true, 'post_id' => $pdo->lastInsertId()]);
    exit;
}

// ========== ПОЛУЧИТЬ МОИ ПОСТЫ ==========
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['route']) && $_GET['route'] === 'my-posts') {
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE author_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($posts as &$post) {
        $post['tags'] = $post['tags'] ? explode(',', $post['tags']) : [];
    }
    echo json_encode($posts);
    exit;
}

// ========== ЛЕНТА ПОДПИСОК ==========
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['route']) && $_GET['route'] === 'feed') {
    $stmt = $pdo->prepare("
        SELECT p.*, u.username FROM posts p
        JOIN subscriptions s ON p.author_id = s.following_id
        JOIN users u ON p.author_id = u.id
        WHERE s.follower_id = ? AND p.visibility = 'public'
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($posts as &$post) {
        $post['tags'] = $post['tags'] ? explode(',', $post['tags']) : [];
    }
    echo json_encode($posts);
    exit;
}

// ========== ВСЕ ПУБЛИЧНЫЕ ПОСТЫ ==========
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['route']) && $_GET['route'] === 'public-posts') {
    $tag = isset($_GET['tag']) ? $_GET['tag'] : null;
    if ($tag) {
        $stmt = $pdo->prepare("SELECT p.*, u.username FROM posts p JOIN users u ON p.author_id = u.id WHERE p.visibility = 'public' AND FIND_IN_SET(?, p.tags) ORDER BY p.created_at DESC");
        $stmt->execute([$tag]);
    } else {
        $stmt = $pdo->prepare("SELECT p.*, u.username FROM posts p JOIN users u ON p.author_id = u.id WHERE p.visibility = 'public' ORDER BY p.created_at DESC");
        $stmt->execute();
    }
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($posts as &$post) {
        $post['tags'] = $post['tags'] ? explode(',', $post['tags']) : [];
    }
    echo json_encode($posts);
    exit;
}

// ========== РЕДАКТИРОВАНИЕ ПОСТА ==========
if ($_SERVER['REQUEST_METHOD'] === 'PUT' && preg_match('/posts\/(\d+)/', $_GET['route'], $matches)) {
    $postId = $matches[1];
    $data = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $pdo->prepare("UPDATE posts SET title = ?, content = ?, updated_at = NOW() WHERE id = ? AND author_id = ?");
    $stmt->execute([$data['title'], $data['content'], $postId, $user['id']]);
    echo json_encode(['success' => true]);
    exit;
}

// ========== УДАЛЕНИЕ ПОСТА ==========
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && preg_match('/posts\/(\d+)/', $_GET['route'], $matches)) {
    $postId = $matches[1];
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ? AND author_id = ?");
    $stmt->execute([$postId, $user['id']]);
    echo json_encode(['success' => true]);
    exit;
}

// ========== ПОДПИСКА ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('/subscribe\/(\d+)/', $_GET['route'], $matches)) {
    $followingId = $matches[1];
    try {
        $stmt = $pdo->prepare("INSERT INTO subscriptions (follower_id, following_id) VALUES (?, ?)");
        $stmt->execute([$user['id'], $followingId]);
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Already subscribed']);
    }
    exit;
}

// ========== ОТПИСКА ==========
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && preg_match('/subscribe\/(\d+)/', $_GET['route'], $matches)) {
    $followingId = $matches[1];
    $stmt = $pdo->prepare("DELETE FROM subscriptions WHERE follower_id = ? AND following_id = ?");
    $stmt->execute([$user['id'], $followingId]);
    echo json_encode(['success' => true]);
    exit;
}

// ========== КОММЕНТАРИИ (ПОЛУЧИТЬ) ==========
if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('/comments\/(\d+)/', $_GET['route'], $matches)) {
    $postId = $matches[1];
    $stmt = $pdo->prepare("SELECT c.*, u.username FROM comments c JOIN users u ON c.author_id = u.id WHERE c.post_id = ? ORDER BY c.created_at ASC");
    $stmt->execute([$postId]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ========== КОММЕНТАРИИ (ДОБАВИТЬ) ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('/comments\/(\d+)/', $_GET['route'], $matches)) {
    $postId = $matches[1];
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("INSERT INTO comments (post_id, author_id, text) VALUES (?, ?, ?)");
    $stmt->execute([$postId, $user['id'], $data['text']]);
    echo json_encode(['success' => true]);
    exit;
}

// ========== ЗАПРОС ДОСТУПА К СКРЫТОМУ ПОСТУ ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('/request-access\/(\d+)/', $_GET['route'], $matches)) {
    $postId = $matches[1];
    try {
        $stmt = $pdo->prepare("INSERT INTO post_access_requests (post_id, user_id) VALUES (?, ?)");
        $stmt->execute([$postId, $user['id']]);
        echo json_encode(['success' => true, 'message' => 'Access requested']);
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Request already sent']);
    }
    exit;
}

// ========== ПОЛУЧИТЬ ДОСТУПНЫЕ СКРЫТЫЕ ПОСТЫ ==========
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['route']) && $_GET['route'] === 'accessible-request-posts') {
    $stmt = $pdo->prepare("
        SELECT p.*, u.username FROM posts p
        JOIN users u ON p.author_id = u.id
        LEFT JOIN post_access_requests r ON p.id = r.post_id AND r.user_id = ?
        WHERE p.visibility = 'request_only' AND (p.author_id = ? OR r.status = 'approved')
    ");
    $stmt->execute([$user['id'], $user['id']]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($posts as &$post) {
        $post['tags'] = $post['tags'] ? explode(',', $post['tags']) : [];
    }
    echo json_encode($posts);
    exit;
}

echo json_encode(['error' => 'Route not found']);
?>

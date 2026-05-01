<header>
    <nav>
        <div class="logo">
            <a href="index.php">📝 BlogApp</a>
        </div>
        <div class="nav-links">
            <a href="index.php">Главная</a>
            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="feed.php">Моя лента</a>
                <a href="create_post.php">Новый пост</a>
                <a href="profile.php">Профиль</a>
                <span class="welcome">Привет, <?= htmlspecialchars($_SESSION['username']) ?>!</span>
                <a href="logout.php">Выйти</a>
            <?php else: ?>
                <a href="login.php">Вход</a>
                <a href="register.php">Регистрация</a>
            <?php endif; ?>
        </div>
    </nav>
</header>
// Удаление поста с подтверждением
function confirmDelete(postId) {
    if(confirm('Вы уверены, что хотите удалить этот пост?')) {
        window.location.href = 'delete_post.php?id=' + postId;
    }
}

// Сортировка постов
function sortPosts(sortBy) {
    const posts = document.querySelectorAll('.post-card');
    const postsArray = Array.from(posts);
    
    postsArray.sort((a, b) => {
        if(sortBy === 'date') {
            const dateA = new Date(a.querySelector('.post-date')?.dataset.date);
            const dateB = new Date(b.querySelector('.post-date')?.dataset.date);
            return dateB - dateA;
        } else if(sortBy === 'title') {
            const titleA = a.querySelector('h3 a')?.innerText || '';
            const titleB = b.querySelector('h3 a')?.innerText || '';
            return titleA.localeCompare(titleB);
        }
        return 0;
    });
    
    const container = document.querySelector('.posts-section');
    postsArray.forEach(post => container.appendChild(post));
}

// Фильтрация по тегам
function filterByTag(tag) {
    const posts = document.querySelectorAll('.post-card');
    posts.forEach(post => {
        const tags = post.querySelector('.post-tags');
        if(tags && tags.innerText.includes(tag)) {
            post.style.display = 'block';
        } else {
            post.style.display = 'none';
        }
    });
}

// Автоматическое обновление комментариев
function refreshComments(postId) {
    fetch('get_comments.php?id=' + postId)
        .then(response => response.json())
        .then(data => {
            const commentsContainer = document.querySelector('.comments-list');
            if(commentsContainer) {
                commentsContainer.innerHTML = data.html;
            }
        });
}

// Loading состояние для форм
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const button = this.querySelector('button[type="submit"]');
        if(button) {
            button.disabled = true;
            button.textContent = 'Загрузка...';
        }
    });
});
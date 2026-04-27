// public/script.js

const API = '';
let token = localStorage.getItem('token');
let currentPostId = null;
let currentEditPostId = null;

// ========== ИНИЦИАЛИЗАЦИЯ ==========
if (!token && window.location.pathname.includes('dashboard.html')) {
    alert('Вы не авторизованы. Перенаправление на главную.');
    window.location.href = 'index.html';
}

// ========== HELPER: авторизованные запросы ==========
async function authFetch(url, options = {}) {
    const headers = {
        'Content-Type': 'application/json',
        'Authorization': token,
        ...options.headers
    };
    const res = await fetch(API + url, { ...options, headers });
    if (res.status === 401) {
        localStorage.clear();
        window.location.href = 'index.html';
    }
    return res;
}

// ========== ПОСТЫ ==========
// Создание поста
async function createPost() {
    const title = document.getElementById('postTitle')?.value;
    const content = document.getElementById('postContent')?.value;
    const tagsRaw = document.getElementById('postTags')?.value || '';
    const tags = tagsRaw.split(',').map(t => t.trim()).filter(t => t);
    const visibility = document.getElementById('postVisibility')?.value || 'public';

    if (!title || !content) {
        alert('Заголовок и содержание не могут быть пустыми');
        return;
    }

    const res = await authFetch('/api/posts', {
        method: 'POST',
        body: JSON.stringify({ title, content, tags, visibility })
    });
    if (res.ok) {
        alert('Пост создан');
        if (document.getElementById('postTitle')) document.getElementById('postTitle').value = '';
        if (document.getElementById('postContent')) document.getElementById('postContent').value = '';
        if (document.getElementById('postTags')) document.getElementById('postTags').value = '';
        loadMyPosts();
        loadPublicPosts();
        loadFeed();
    } else {
        const err = await res.json();
        alert('Ошибка: ' + (err.error || 'неизвестная'));
    }
}

// Загрузка моих постов
async function loadMyPosts() {
    const container = document.getElementById('myPosts');
    if (!container) return;
    const res = await authFetch('/api/posts/my');
    const posts = await res.json();
    if (!posts.length) {
        container.innerHTML = '<p>У вас пока нет постов. Создайте первый!</p>';
        return;
    }
    container.innerHTML = posts.map(post => `
        <div class="post" data-post-id="${post._id}">
            <h3>${escapeHtml(post.title)}</h3>
            <p>${escapeHtml(post.content.length > 150 ? post.content.substring(0,150)+'...' : post.content)}</p>
            <div class="tags">🏷️ ${(post.tags || []).join(', ') || 'без тегов'} | 👁️ ${post.visibility}</div>
            <div class="actions">
                <button class="warning" onclick="openEditModal('${post._id}')">✏️ Редактировать</button>
                <button class="danger" onclick="deletePost('${post._id}')">🗑️ Удалить</button>
                <button onclick="showComments('${post._id}')">💬 Комментарии</button>
            </div>
        </div>
    `).join('');
}

// Открыть модальное окно редактирования
function openEditModal(postId) {
    currentEditPostId = postId;
    // Найти пост в DOM (или запросить с сервера, но проще подгрузить из уже загруженных)
    const postDiv = document.querySelector(`.post[data-post-id="${postId}"]`);
    if (postDiv) {
        const titleEl = postDiv.querySelector('h3');
        const contentEl = postDiv.querySelector('p');
        if (titleEl && contentEl) {
            document.getElementById('editTitle').value = titleEl.innerText;
            document.getElementById('editContent').value = contentEl.innerText;
        }
    }
    document.getElementById('editModal').style.display = 'block';
}

async function saveEditedPost() {
    const newTitle = document.getElementById('editTitle').value;
    const newContent = document.getElementById('editContent').value;
    if (!newTitle || !newContent) {
        alert('Заголовок и содержание не могут быть пустыми');
        return;
    }
    const res = await authFetch(`/api/posts/${currentEditPostId}`, {
        method: 'PUT',
        body: JSON.stringify({ title: newTitle, content: newContent })
    });
    if (res.ok) {
        alert('Пост обновлён');
        closeEditModal();
        loadMyPosts();
        loadPublicPosts();
        loadFeed();
    } else {
        alert('Ошибка при редактировании');
    }
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
    currentEditPostId = null;
}

// Удаление поста
async function deletePost(postId) {
    if (!confirm('Удалить пост навсегда?')) return;
    const res = await authFetch(`/api/posts/${postId}`, { method: 'DELETE' });
    if (res.ok) {
        alert('Удалено');
        loadMyPosts();
        loadPublicPosts();
        loadFeed();
    } else {
        alert('Ошибка удаления');
    }
}

// Лента подписок
async function loadFeed() {
    const container = document.getElementById('feed');
    if (!container) return;
    const res = await authFetch('/api/posts/feed');
    const posts = await res.json();
    if (!posts.length) {
        container.innerHTML = '<p>Подпишитесь на кого-нибудь, чтобы видеть посты тут.</p>';
        return;
    }
    container.innerHTML = posts.map(post => `
        <div class="post">
            <h3>${escapeHtml(post.title)}</h3>
            <p><strong>${escapeHtml(post.author?.username || 'unknown')}</strong> | ${new Date(post.createdAt).toLocaleDateString()}</p>
            <p>${escapeHtml(post.content.length > 200 ? post.content.substring(0,200)+'...' : post.content)}</p>
            <div class="tags">🏷️ ${(post.tags || []).join(', ')}</div>
            <button onclick="showComments('${post._id}')">💬 Комментарии</button>
        </div>
    `).join('');
}

// Все публичные посты + фильтр по тегу
let globalTag = '';
async function loadPublicPosts() {
    const container = document.getElementById('publicPosts');
    if (!container) return;
    let url = '/api/posts/public';
    if (globalTag) url = `/api/posts/tags/${encodeURIComponent(globalTag)}`;
    const res = await fetch(url);
    const posts = await res.json();
    if (!posts.length) {
        container.innerHTML = '<p>Нет публичных постов</p>';
        return;
    }
    container.innerHTML = posts.map(post => `
        <div class="post">
            <h3>${escapeHtml(post.title)}</h3>
            <p><strong>${escapeHtml(post.author?.username || 'anon')}</strong> | ${new Date(post.createdAt).toLocaleDateString()}</p>
            <p>${escapeHtml(post.content.length > 200 ? post.content.substring(0,200)+'...' : post.content)}</p>
            <div class="tags">🏷️ ${(post.tags || []).join(', ')}</div>
            <button onclick="subscribeTo('${post.author?._id}')">➕ Подписаться</button>
            <button onclick="showComments('${post._id}')">💬 Комментарии</button>
        </div>
    `).join('');
}

function filterGlobalByTag() {
    globalTag = document.getElementById('globalTagFilter')?.value.trim() || '';
    loadPublicPosts();
}

// Поиск по тегам
async function searchByTag() {
    const tag = document.getElementById('tagSearch')?.value.trim();
    if (!tag) return;
    const res = await fetch(`/api/posts/tags/${encodeURIComponent(tag)}`);
    const posts = await res.json();
    const container = document.getElementById('taggedPosts');
    if (!container) return;
    if (!posts.length) {
        container.innerHTML = '<p>Постов с таким тегом нет</p>';
        return;
    }
    container.innerHTML = posts.map(post => `
        <div class="post">
            <h3>${escapeHtml(post.title)}</h3>
            <p>${escapeHtml(post.content.length > 150 ? post.content.substring(0,150)+'...' : post.content)}</p>
            <div class="tags">🏷️ ${(post.tags || []).join(', ')}</div>
            <button onclick="showComments('${post._id}')">Комментарии</button>
        </div>
    `).join('');
}

// ========== ПОДПИСКИ ==========
async function subscribeTo(userId) {
    if (!userId) return;
    const res = await authFetch(`/api/subscribe/${userId}`, { method: 'POST' });
    if (res.ok) alert('Подписка оформлена');
    else {
        const err = await res.json();
        alert('Ошибка: ' + (err.error || 'возможно, уже подписаны'));
    }
}

// ========== КОММЕНТАРИИ ==========
async function showComments(postId) {
    currentPostId = postId;
    const modal = document.getElementById('commentsModal');
    if (!modal) return;
    modal.style.display = 'block';
    const res = await fetch(`/api/comments/${postId}`);
    const comments = await res.json();
    const container = document.getElementById('commentsList');
    if (!comments.length) container.innerHTML = '<p>Нет комментариев. Будьте первым!</p>';
    else {
        container.innerHTML = comments.map(c => `
            <div class="comment"><strong>${escapeHtml(c.author?.username || 'user')}</strong>: ${escapeHtml(c.text)} <small>${new Date(c.createdAt).toLocaleString()}</small></div>
        `).join('');
    }
}

async function submitComment() {
    const text = document.getElementById('commentText')?.value;
    if (!text) return alert('Введите текст комментария');
    const res = await authFetch(`/api/comments/${currentPostId}`, {
        method: 'POST',
        body: JSON.stringify({ text })
    });
    if (res.ok) {
        alert('Комментарий добавлен');
        if (document.getElementById('commentText')) document.getElementById('commentText').value = '';
        showComments(currentPostId);
    } else {
        alert('Ошибка отправки комментария');
    }
}

function closeComments() {
    const modal = document.getElementById('commentsModal');
    if (modal) modal.style.display = 'none';
    currentPostId = null;
}

// ========== ВСПОМОГАТЕЛЬНЫЕ ==========
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

function logout() {
    localStorage.clear();
    window.location.href = 'index.html';
}

// ========== ЗАГРУЗКА ПРИ СТАРТЕ ==========
document.addEventListener('DOMContentLoaded', () => {
    if (window.location.pathname.includes('dashboard.html')) {
        loadMyPosts();
        loadFeed();
        loadPublicPosts();
    }
});

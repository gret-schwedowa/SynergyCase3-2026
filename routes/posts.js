const router = require('express').Router();
const auth = require('../middleware/auth');
const Post = require('../models/Post');
const User = require('../models/User');

// Создание поста
router.post('/', auth, async (req, res) => {
  try {
    const { title, content, tags, visibility } = req.body;
    const post = new Post({
      title,
      content,
      author: req.user._id,
      tags: tags || [],
      visibility: visibility || 'public'
    });
    await post.save();
    res.status(201).json(post);
  } catch (error) {
    res.status(400).json({ error: error.message });
  }
});

// Получить посты текущего пользователя
router.get('/my', auth, async (req, res) => {
  try {
    const posts = await Post.find({ author: req.user._id }).sort({ createdAt: -1 });
    res.json(posts);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Получение постов для ленты (по подпискам)
router.get('/feed', auth, async (req, res) => {
  try {
    const user = await User.findById(req.user._id).populate('following');
    const followingIds = user.following.map(u => u._id);
    const posts = await Post.find({ 
      author: { $in: followingIds },
      visibility: 'public'
    }).populate('author', 'username').sort({ createdAt: -1 });
    res.json(posts);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Все публичные посты
router.get('/public', async (req, res) => {
  try {
    const posts = await Post.find({ visibility: 'public' }).populate('author', 'username').sort({ createdAt: -1 });
    res.json(posts);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Посты с фильтром по тегам
router.get('/tags/:tag', async (req, res) => {
  try {
    const posts = await Post.find({ tags: req.params.tag, visibility: 'public' }).populate('author', 'username');
    res.json(posts);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Пост "только по запросу" – запрос доступа
router.post('/:id/request-access', auth, async (req, res) => {
  try {
    const post = await Post.findById(req.params.id);
    if (post.visibility !== 'request-only') 
      return res.status(400).json({ error: 'Post does not require access' });
    if (!post.requestAccess.includes(req.user._id)) {
      post.requestAccess.push(req.user._id);
      await post.save();
    }
    res.json({ message: 'Access requested' });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Редактирование поста
router.put('/:id', auth, async (req, res) => {
  try {
    const post = await Post.findById(req.params.id);
    if (post.author.toString() !== req.user._id) 
      return res.status(403).json({ error: 'Not your post' });
    const { title, content, tags, visibility } = req.body;
    post.title = title || post.title;
    post.content = content || post.content;
    post.tags = tags || post.tags;
    post.visibility = visibility || post.visibility;
    post.updatedAt = Date.now();
    await post.save();
    res.json(post);
  } catch (error) {
    res.status(400).json({ error: error.message });
  }
});

// Удаление поста
router.delete('/:id', auth, async (req, res) => {
  try {
    const post = await Post.findById(req.params.id);
    if (post.author.toString() !== req.user._id) 
      return res.status(403).json({ error: 'Not your post' });
    await post.deleteOne();
    res.json({ message: 'Post deleted' });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

module.exports = router;

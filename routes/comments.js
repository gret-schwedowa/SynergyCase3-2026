const router = require('express').Router();
const auth = require('../middleware/auth');
const Comment = require('../models/Comment');

// Добавить комментарий
router.post('/:postId', auth, async (req, res) => {
  try {
    const comment = new Comment({
      text: req.body.text,
      author: req.user._id,
      post: req.params.postId
    });
    await comment.save();
    res.status(201).json(comment);
  } catch (error) {
    res.status(400).json({ error: error.message });
  }
});

// Получить комментарии к посту
router.get('/:postId', async (req, res) => {
  try {
    const comments = await Comment.find({ post: req.params.postId }).populate('author', 'username');
    res.json(comments);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

module.exports = router;

const router = require('express').Router();
const auth = require('../middleware/auth');
const User = require('../models/User');

// Подписаться на пользователя
router.post('/:userId', auth, async (req, res) => {
  try {
    const targetUser = await User.findById(req.params.userId);
    const currentUser = await User.findById(req.user._id);
    
    if (targetUser.followers.includes(req.user._id)) {
      return res.status(400).json({ error: 'Already subscribed' });
    }
    
    targetUser.followers.push(req.user._id);
    currentUser.following.push(req.params.userId);
    await targetUser.save();
    await currentUser.save();
    res.json({ message: 'Subscribed' });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Отписаться
router.delete('/:userId', auth, async (req, res) => {
  try {
    const targetUser = await User.findById(req.params.userId);
    const currentUser = await User.findById(req.user._id);
    
    targetUser.followers = targetUser.followers.filter(id => id.toString() !== req.user._id);
    currentUser.following = currentUser.following.filter(id => id.toString() !== req.params.userId);
    await targetUser.save();
    await currentUser.save();
    res.json({ message: 'Unsubscribed' });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

module.exports = router;

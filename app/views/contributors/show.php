<div class="page-head contributor-head">
  <img class="contributor-head__avatar" src="<?= ofx_h(ofx_avatar_url($user['avatar_url'])) ?>" alt="">
  <div>
    <h1><?= ofx_h($user['name'] ?: $user['login']) ?></h1>
    <a href="https://github.com/<?= ofx_h($user['login']) ?>" target="_blank" rel="noopener">@<?= ofx_h($user['login']) ?></a>
  </div>
</div>

<div class="addon-grid">
  <?php foreach ($addons as $addon): ?>
    <?php ofx_addon_partial($addon); ?>
  <?php endforeach; ?>
</div>

<div class="page-head">
  <h1>Contributors</h1>
</div>

<div class="contributor-grid">
  <?php foreach ($users as $user): ?>
    <a class="contributor-card" href="/contributors/<?= ofx_h(rawurlencode($user['login'])) ?>">
      <img class="contributor-card__avatar" src="<?= ofx_h(ofx_avatar_url($user['avatar_url'])) ?>" alt="" loading="lazy">
      <span class="contributor-card__login"><?= ofx_h($user['login']) ?></span>
      <span class="contributor-card__count"><?= (int)$user['repo_count'] ?> addon<?= ((int)$user['repo_count'] === 1 ? '' : 's') ?></span>
    </a>
  <?php endforeach; ?>
</div>

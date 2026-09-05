<div class="page-head contributor-head">
  <img class="contributor-head__avatar" src="<?= ofx_h(ofx_avatar_url($user['avatar_url'], 64)) ?>" alt="">
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

<?php if (!empty($forks)): ?>
  <div class="page-head" id="forks"><h2>Forks</h2></div>
  <p class="page-intro">
    Forks of <?= ofx_h($user['login']) ?>&rsquo;s addons that have been pushed to more recently than the addon itself.
  </p>
  <div class="fork-list">
    <?php foreach ($forks as $fork): ?>
      <a class="fork-card" href="https://github.com/<?= ofx_h($fork['full_name'] ?? '') ?>" target="_blank" rel="noopener">
        <img class="fork-card__avatar" src="<?= ofx_h(ofx_avatar_url($fork['owner_avatar_url'] ?? null)) ?>" alt="" loading="lazy">
        <div class="fork-card__info">
          <span class="fork-card__name"><?= ofx_h($fork['full_name'] ?? '') ?></span>
          <span class="fork-card__meta">
            fork of <?= ofx_h($fork['parent_full_name'] ?? '') ?>
            &middot; &#9733; <?= (int)($fork['stargazers_count'] ?? 0) ?>
            &middot; pushed <?= ofx_h(ofx_time_ago($fork['pushed_at'] ?? null)) ?>
          </span>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

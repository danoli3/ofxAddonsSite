<?php
$categories = !empty($addon['categories']) ? explode('||', $addon['categories']) : [];
?>
<a href="/categories" class="addon-detail__back"
   onclick="if (window.history.length > 1) { history.back(); return false; }">&larr; Back</a>

<div class="page-head contributor-head">
  <img class="contributor-head__avatar" src="<?= ofx_h(ofx_avatar_url($addon['user_avatar_url'] ?? null)) ?>" alt="">
  <div>
    <h1><?= ofx_h($addon['name']) ?></h1>
    <a href="https://github.com/<?= ofx_h($addon['full_name']) ?>" target="_blank" rel="noopener">
      github.com/<?= ofx_h($addon['full_name']) ?>
    </a>
    <?php if (!empty($addon['user_login'])): ?>
      &middot; <a href="/contributors/<?= ofx_h(rawurlencode($addon['user_login'])) ?>">@<?= ofx_h($addon['user_login']) ?></a>
    <?php endif; ?>
  </div>
</div>

<div class="addon-detail__tags">
  <?php if (!empty($addon['archived'])): ?>
    <span class="tag tag--archived" title="Owner has archived this repo on Github">Archived</span>
  <?php endif; ?>
  <?php if (!empty($addon['has_releases'])): ?>
    <a class="tag tag--releases" href="https://github.com/<?= ofx_h($addon['full_name']) ?>/releases" target="_blank" rel="noopener">Releases</a>
  <?php endif; ?>
  <?php foreach ($categories as $cat): ?>
    <span class="tag"><?= ofx_h($cat) ?></span>
  <?php endforeach; ?>
</div>

<p class="addon-detail__desc"><?= ofx_h($addon['description'] ?: 'No description.') ?></p>

<div class="addon-detail__meta">
  <span title="Stars">&#9733; <?= (int)($addon['stargazers_count'] ?? 0) ?></span>
  <span>Last commit <?= ofx_h(ofx_time_ago($addon['pushed_at'] ?? null)) ?></span>
</div>

<?php if (!empty($newerForks)): ?>
  <div class="page-head" id="forks"><h2>More recently updated forks</h2></div>
  <p class="page-intro">
    These forks have been pushed to more recently than <?= ofx_h($addon['full_name']) ?> itself &mdash;
    worth checking if you're hitting issues with the original.
  </p>
  <div class="fork-list">
    <?php foreach ($newerForks as $fork): ?>
      <a class="fork-card" href="https://github.com/<?= ofx_h($fork['full_name'] ?? '') ?>" target="_blank" rel="noopener">
        <img class="fork-card__avatar" src="<?= ofx_h(ofx_avatar_url($fork['owner_avatar_url'] ?? null)) ?>" alt="" loading="lazy">
        <div class="fork-card__info">
          <span class="fork-card__name"><?= ofx_h($fork['full_name'] ?? '') ?></span>
          <span class="fork-card__meta">
            by @<?= ofx_h($fork['owner_login'] ?? '') ?>
            &middot; &#9733; <?= (int)($fork['stargazers_count'] ?? 0) ?>
            &middot; pushed <?= ofx_h(ofx_time_ago($fork['pushed_at'] ?? null)) ?>
          </span>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="page-head"><h2>README</h2></div>
<?php if ($readme): ?>
  <div class="addon-detail__readme"><?= ofx_render_markdown_lite($readme) ?></div>
<?php else: ?>
  <p class="empty-state">Couldn't load a README for this repo.</p>
<?php endif; ?>

<?php

$categories = !empty($addon['categories']) ? explode('||', $addon['categories']) : [];
$newerForkCount = 0;
if (!empty($addon['newer_forks'])) {
    $decodedForks = json_decode($addon['newer_forks'], true);
    $newerForkCount = is_array($decodedForks) ? count($decodedForks) : 0;
}
?>
<article class="addon-card" data-name="<?= ofx_h(strtolower($addon['name'] ?? '')) ?>" data-desc="<?= ofx_h(strtolower($addon['description'] ?? '')) ?>">
  <?php if ((!empty($addon['has_thumbnail']) || !empty($addon['thumbnail_url_override'])) && !empty($addon['full_name'])): ?>
    <a href="https://github.com/<?= ofx_h($addon['full_name']) ?>" target="_blank" rel="noopener">
      <img class="addon-card__thumb"
           src="<?= ofx_h(ofx_thumbnail_url($addon['full_name'], $addon['thumbnail_url_override'] ?? null)) ?>"
           alt="" loading="lazy" onerror="this.closest('a').remove()">
    </a>
  <?php endif; ?>
  <div class="addon-card__head">
    <img class="addon-card__avatar" src="<?= ofx_h(ofx_avatar_url($addon['user_avatar_url'] ?? null)) ?>" alt="" loading="lazy">
    <div class="addon-card__title">
      <a class="addon-card__name" href="https://github.com/<?= ofx_h($addon['full_name'] ?? '') ?>" target="_blank" rel="noopener">
        <?= ofx_h($addon['name'] ?? '') ?>
      </a>
      <?php if (!empty($addon['user_login'])): ?>
        <a class="addon-card__owner" href="/contributors/<?= ofx_h(rawurlencode($addon['user_login'])) ?>">
          @<?= ofx_h($addon['user_login']) ?>
        </a>
      <?php endif; ?>
      <?php if (($addon['type'] ?? null) === 'Addon'): ?>
        <a class="addon-card__more" href="<?= ofx_h(ofx_addon_url($addon['full_name'])) ?>">More info &rarr;</a>
      <?php endif; ?>
    </div>
    <?php if (!empty($addon['featured'])): ?>
      <span class="tag tag--featured" title="Featured in this category">★ Featured</span>
    <?php endif; ?>
    <?php if (!empty($addon['archived'])): ?>
      <span class="tag tag--archived" title="Owner has archived this repo on Github">Archived</span>
    <?php endif; ?>
    <?php if (!empty($addon['has_releases'])): ?>
      <a class="tag tag--releases" href="https://github.com/<?= ofx_h($addon['full_name'] ?? '') ?>/releases"
         target="_blank" rel="noopener" title="Has tagged Github releases">Releases</a>
    <?php endif; ?>
  </div>

  <p class="addon-card__desc"><?= ofx_h($addon['description'] ?: 'No description.') ?></p>

  <?php if (!empty($categories)): ?>
    <div class="addon-card__tags">
      <?php foreach ($categories as $cat): ?>
        <span class="tag"><?= ofx_h($cat) ?></span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="addon-card__meta">
    <span class="addon-card__stats">
      <span class="addon-card__stars" title="Stars">
        <svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" aria-hidden="true">
          <path d="M8 .25a.75.75 0 0 1 .673.418l1.882 3.815 4.21.612a.75.75 0 0 1 .416 1.279l-3.046 2.97.719 4.192a.75.75 0 0 1-1.088.791L8 12.347l-3.766 1.98a.75.75 0 0 1-1.088-.79l.72-4.194L.818 6.374a.75.75 0 0 1 .416-1.28l4.21-.611L7.327.668A.75.75 0 0 1 8 .25Z"/>
        </svg>
        <?= (int)($addon['stargazers_count'] ?? 0) ?>
      </span>
      <?php if ($newerForkCount > 0): ?>
        <a class="addon-card__forks" href="<?= ofx_h(ofx_addon_url($addon['full_name'])) ?>#forks"
           title="<?= $newerForkCount ?> fork<?= $newerForkCount === 1 ? '' : 's' ?> more active than this addon">
          <svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" aria-hidden="true">
            <path d="M5 3.25a1.75 1.75 0 1 1 3.5 0 1.75 1.75 0 0 1-3.5 0Zm5.75 0a1.75 1.75 0 1 1 3.5 0 1.75 1.75 0 0 1-3.5 0ZM6.5 5.75c.276 0 .5.224.5.5v1.774c.996.284 1.719 1.207 1.719 2.298v.478h.219a.5.5 0 0 1 0 1H7.5a.5.5 0 0 1 0-1h.219v-.478c0-1.09.723-2.014 1.719-2.298V6.25a.5.5 0 0 1 1 0v1.774c.996.284 1.719 1.207 1.719 2.298v.478H9.5a1.75 1.75 0 0 0-1.75 1.75v.5a1.75 1.75 0 0 0 1.75 1.75h.5a1.75 1.75 0 0 0 1.75-1.75"/>
          </svg>
          <?= $newerForkCount ?>
        </a>
      <?php endif; ?>
    </span>
    <span class="addon-card__updated">Updated <?= ofx_h(ofx_time_ago($addon['pushed_at'] ?? null)) ?></span>
  </div>
</article>

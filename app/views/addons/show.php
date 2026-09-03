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
  <a href="https://github.com/<?= ofx_h($addon['full_name']) ?>/forks" target="_blank" rel="noopener" title="Forks">
    <svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" aria-hidden="true">
      <path d="M5 3.25a1.75 1.75 0 1 1 3.5 0 1.75 1.75 0 0 1-3.5 0Zm5.75 0a1.75 1.75 0 1 1 3.5 0 1.75 1.75 0 0 1-3.5 0ZM6.5 5.75c.276 0 .5.224.5.5v1.774c.996.284 1.719 1.207 1.719 2.298v.478h.219a.5.5 0 0 1 0 1H7.5a.5.5 0 0 1 0-1h.219v-.478c0-1.09.723-2.014 1.719-2.298V6.25a.5.5 0 0 1 1 0v1.774c.996.284 1.719 1.207 1.719 2.298v.478H9.5a1.75 1.75 0 0 0-1.75 1.75v.5a1.75 1.75 0 0 0 1.75 1.75h.5a1.75 1.75 0 0 0 1.75-1.75"/>
    </svg>
    <?= (int)($addon['forks_count'] ?? 0) ?>
  </a>
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

<?php if (!empty($aheadBranches)): ?>
  <div class="page-head" id="branches"><h2>Branches ahead of <?= ofx_h($addon['default_branch'] ?: 'the default branch') ?></h2></div>
  <p class="page-intro">
    These branches have commits not yet on <?= ofx_h($addon['default_branch'] ?: 'the default branch') ?> &mdash;
    could be unmerged fixes or work in progress worth a look.
  </p>
  <div class="fork-list">
    <?php foreach ($aheadBranches as $branch): ?>
      <a class="fork-card" href="https://github.com/<?= ofx_h($addon['full_name']) ?>/compare/<?= ofx_h($addon['default_branch'] ?? '') ?>...<?= ofx_h($branch['name'] ?? '') ?>" target="_blank" rel="noopener">
        <div class="fork-card__info">
          <span class="fork-card__name"><?= ofx_h($branch['name'] ?? '') ?></span>
          <span class="fork-card__meta">
            <?= (int)($branch['ahead_by'] ?? 0) ?> commit<?= (int)($branch['ahead_by'] ?? 0) === 1 ? '' : 's' ?> ahead
            &middot; last commit <?= ofx_h(ofx_time_ago($branch['last_commit_at'] ?? null)) ?>
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

<div class="page-head" id="get"><h2>Get this addon</h2></div>
<div class="addon-detail__get">
  <div class="copy-field">
    <span class="copy-field__label">HTTPS</span>
    <input type="text" class="copy-field__input" id="clone-https" readonly
           value="https://github.com/<?= ofx_h($addon['full_name']) ?>.git">
    <button type="button" class="copy-field__btn" data-copy-target="clone-https">Copy</button>
  </div>
  <div class="copy-field">
    <span class="copy-field__label">SSH</span>
    <input type="text" class="copy-field__input" id="clone-ssh" readonly
           value="git@github.com:<?= ofx_h($addon['full_name']) ?>.git">
    <button type="button" class="copy-field__btn" data-copy-target="clone-ssh">Copy</button>
  </div>
  <div class="addon-detail__get-links">
    <a href="https://github.com/<?= ofx_h($addon['full_name']) ?>" target="_blank" rel="noopener">View on GitHub</a>
    <?php if (!empty($addon['default_branch'])): ?>
      &middot; <a href="https://github.com/<?= ofx_h($addon['full_name']) ?>/archive/refs/heads/<?= ofx_h($addon['default_branch']) ?>.zip">Download ZIP</a>
    <?php endif; ?>
    <?php if (!empty($addon['has_releases'])): ?>
      &middot; <a href="https://github.com/<?= ofx_h($addon['full_name']) ?>/releases" target="_blank" rel="noopener">All releases</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($latestRelease): ?>
  <div class="page-head"><h2>Latest release</h2></div>
  <div class="release-card">
    <div class="release-card__head">
      <a class="release-card__tag" href="<?= ofx_h($latestRelease['html_url'] ?: 'https://github.com/' . $addon['full_name'] . '/releases') ?>" target="_blank" rel="noopener">
        <?= ofx_h($latestRelease['tag_name']) ?>
      </a>
      <?php if (!empty($latestRelease['name']) && $latestRelease['name'] !== $latestRelease['tag_name']): ?>
        <span class="release-card__name"><?= ofx_h($latestRelease['name']) ?></span>
      <?php endif; ?>
      <span class="release-card__date">published <?= ofx_h(ofx_time_ago($latestRelease['published_at'] ?? null)) ?></span>
    </div>
    <?php if (!empty($latestRelease['body'])): ?>
      <div class="release-card__notes"><?= ofx_render_markdown_lite(mb_substr($latestRelease['body'], 0, 2000)) ?></div>
    <?php endif; ?>
  </div>
<?php endif; ?>

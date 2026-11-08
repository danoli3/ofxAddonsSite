<?php
/** @var array $groups */
?>
<?php foreach ($groups as $nameKey => $members): ?>
  <div class="page-head"><h2><?= ofx_h($members[0]['name']) ?></h2></div>
  <div class="dupe-group">
    <?php foreach ($members as $i => $repo): ?>
      <div class="dupe-item<?= $i === 0 ? ' dupe-item--original' : '' ?>" data-repo-id="<?= (int)$repo['id'] ?>">
        <div class="dupe-item__info">
          <a href="https://github.com/<?= ofx_h($repo['full_name']) ?>" target="_blank" rel="noopener">
            <?= ofx_h($repo['full_name']) ?>
          </a>
          <span class="dupe-item__meta">
            created <?= ofx_h(ofx_time_ago($repo['created_at'] ?? null)) ?>
            &middot; updated <?= ofx_h(ofx_time_ago($repo['pushed_at'] ?? null)) ?>
            &middot; &#9733; <?= (int)($repo['stargazers_count'] ?? 0) ?> stars
            &middot; <?= (int)($repo['forks_count'] ?? 0) ?> forks
            <?= $i === 0 ? '&middot; presumed original' : '' ?>
          </span>
        </div>
        <?php if (!empty($repo['description'])): ?>
          <p class="dupe-item__description"><?= ofx_h($repo['description']) ?></p>
        <?php endif; ?>
        <?php if (!empty($repo['confirmed_fork_of'])): ?>
          <span class="tag tag--curated">Confirmed fork of <?= ofx_h($members[0]['full_name']) ?></span>
          <?php if (!empty($repo['fork_hidden_by_admin'])): ?>
            <span class="tag tag--archived">Hidden from public</span>
          <?php endif; ?>
          <button type="button" class="dupe-item__unconfirm" data-repo-id="<?= (int)$repo['id'] ?>">Undo</button>
        <?php elseif (!empty($repo['confirmed_unique'])): ?>
          <span class="tag tag--curated">Not a duplicate</span>
          <button type="button" class="dupe-item__unconfirm-unique" data-repo-id="<?= (int)$repo['id'] ?>">Undo</button>
        <?php else: ?>
          <?php if ($i > 0): ?>
            <button type="button" class="dupe-item__confirm" data-repo-id="<?= (int)$repo['id'] ?>"
                    data-parent-id="<?= (int)$members[0]['id'] ?>" data-hide="0">
              Confirm fork of original
            </button>
            <button type="button" class="dupe-item__confirm" data-repo-id="<?= (int)$repo['id'] ?>"
                    data-parent-id="<?= (int)$members[0]['id'] ?>" data-hide="1">
              Confirm + hide from public
            </button>
          <?php endif; ?>
          <button type="button" class="dupe-item__confirm-unique" data-repo-id="<?= (int)$repo['id'] ?>"
                  title="These are unrelated addons that just happen to share a name">
            Not a duplicate
          </button>
        <?php endif; ?>
        <span class="dupe-item__status"></span>
        <?php if (array_key_exists('readme_preview', $repo)): ?>
          <section class="dupe-item__readme-preview" aria-label="README preview for <?= ofx_h($repo['full_name']) ?>">
            <div class="dupe-item__readme-heading">
              <strong>README preview</strong>
              <a href="https://github.com/<?= ofx_h($repo['full_name']) ?>#readme" target="_blank" rel="noopener noreferrer">Full README &nearr;</a>
            </div>
            <?php if (!empty($repo['readme_preview'])): ?>
              <div class="addon-detail__readme dupe-item__readme-body"><?= ofx_render_markdown_lite($repo['readme_preview'], $repo['full_name'], $repo['default_branch'] ?? null) ?></div>
              <?php if (!empty($repo['readme_truncated'])): ?>
                <p class="dupe-item__readme-note">Showing the beginning of the README. Open the full README for more.</p>
              <?php endif; ?>
            <?php else: ?>
              <p class="dupe-item__readme-note">README unavailable. Open the repository on Github to check.</p>
            <?php endif; ?>
          </section>
        <?php endif; ?>
        <?php if (!empty($repo['readme_tail'])): ?>
          <details class="dupe-item__readme"><summary>README ending</summary>&hellip;<?= ofx_h($repo['readme_tail']) ?></details>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>

<div class="page-head">
  <h1><?= ofx_h($category['name']) ?></h1>
  <div class="filter-wrap">
    <input type="text" class="filter-box" id="addon-filter" placeholder="Filter addons&hellip;">
    <span class="spinner search-spinner" aria-hidden="true"></span>
  </div>
</div>

<div id="search-results" class="addon-grid" hidden></div>

<div id="filterable-content">
  <?php if (empty($addons)): ?>
    <p class="empty-state">No addons in this category yet.</p>
  <?php endif; ?>

  <div class="addon-grid" data-has-more="<?= $hasMore ? '1' : '0' ?>" data-next-url="<?= ofx_h($nextUrl) ?>">
    <?php foreach ($addons as $addon): ?>
      <?php ofx_category_addon_partial($addon, (int)$category['id'], $isAdmin); ?>
    <?php endforeach; ?>
  </div>
  <div class="grid-sentinel"></div>
  <div class="grid-loading" hidden>
    <span class="spinner"></span> Loading more&hellip;
  </div>
  <p class="grid-end" hidden>You&rsquo;ve reached the end.</p>
</div>

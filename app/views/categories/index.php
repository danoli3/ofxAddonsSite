<div class="hero">
  <h1>Discover openFrameworks addons</h1>
  <p>The central place to find, browse and categorize the openFrameworks addon ecosystem.</p>
  <div class="filter-wrap filter-wrap--hero">
    <input type="text" class="filter-box filter-box--hero" id="addon-filter" placeholder="Search addons&hellip;">
    <span class="spinner search-spinner" aria-hidden="true"></span>
  </div>
</div>

<div id="search-results" class="addon-grid" hidden></div>

<div id="filterable-content">
  <?php if (empty($categories)): ?>
    <p class="empty-state">No categorized addons yet.</p>
  <?php endif; ?>

  <?php foreach ($categories as $category): ?>
    <?php $all = $addonsByCategory[$category['id']] ?? []; ?>
    <section class="category-section" id="category-<?= (int)$category['id'] ?>">
      <h2 class="category-section__title">
        <a href="<?= ofx_h(ofx_category_url($category)) ?>"><?= ofx_h($category['name']) ?></a>
        <span class="count"><?= count($all) ?></span>
        <?php if (count($all) > OFX_CATEGORY_PREVIEW_SIZE): ?>
          <a class="view-all" href="<?= ofx_h(ofx_category_url($category)) ?>">View all &rarr;</a>
        <?php endif; ?>
      </h2>
      <div class="addon-grid">
        <?php foreach (array_slice($all, 0, OFX_CATEGORY_PREVIEW_SIZE) as $addon): ?>
          <?php ofx_addon_partial($addon); ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
</div>

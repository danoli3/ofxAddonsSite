<div class="hero">
  <a class="of-badge" href="/about-openframeworks" title="What is openFrameworks?">
    <span class="of-badge__logo" aria-hidden="true"></span>
    Built for openFrameworks
  </a>
  <h1>Discover openFrameworks addons</h1>
  <p>The central place to find, browse and categorize the openFrameworks addon ecosystem.</p>
  <div class="filter-wrap filter-wrap--hero">
    <input type="text" class="filter-box filter-box--hero" id="addon-filter" placeholder="Search addons&hellip;">
    <span class="spinner search-spinner" aria-hidden="true"></span>
  </div>
  <?php if (!empty($categories)): ?>
    <!-- jumps to the matching #category-{id} section further down this
         same page, rather than navigating to /categories/{slug} - the
         category name link in each section heading already covers that -->
    <nav class="category-jump" aria-label="Jump to category">
      <?php foreach ($categories as $category): ?>
        <a class="category-jump__link" href="#category-<?= (int)$category['id'] ?>"><?= ofx_h($category['name']) ?></a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>
</div>

<div id="search-results" class="addon-grid" hidden></div>

<div id="filterable-content">
  <?php if (empty($categories)): ?>
    <p class="empty-state">No categorized addons yet.</p>
  <?php endif; ?>

  <?php foreach ($categories as $category): ?>
    <?php
      $all = $addonsByCategory[$category['id']] ?? [];
      $preview = $previewByCategory[$category['id']] ?? array_slice($all, 0, OFX_CATEGORY_PREVIEW_SIZE);
    ?>
    <section class="category-section" id="category-<?= (int)$category['id'] ?>">
      <h2 class="category-section__title">
        <a href="<?= ofx_h(ofx_category_url($category)) ?>"><?= ofx_h($category['name']) ?></a>
        <span class="count"><?= count($all) ?></span>
        <?php if (count($all) > OFX_CATEGORY_PREVIEW_SIZE): ?>
          <a class="view-all" href="<?= ofx_h(ofx_category_url($category)) ?>">View all &rarr;</a>
        <?php endif; ?>
      </h2>
      <div class="addon-grid">
        <?php foreach ($preview as $addon): ?>
          <?php ofx_addon_partial($addon); ?>
        <?php endforeach; ?>
        <?php if (count($all) > OFX_CATEGORY_PREVIEW_SIZE): ?>
          <!-- addon-card--view-all, not a real addon-card - excluded from
               the #addon-filter matching loop in site.js (it has no
               data-name/data-desc), so it never breaks that filter or
               gets miscounted as a "visible" match -->
          <a class="addon-card addon-card--view-all" href="<?= ofx_h(ofx_category_url($category)) ?>">
            <span class="addon-card--view-all__count"><?= count($all) - OFX_CATEGORY_PREVIEW_SIZE ?> more in <?= ofx_h($category['name']) ?></span>
            <span class="addon-card--view-all__title">View all <?= ofx_h($category['name']) ?></span>
            <span class="addon-card--view-all__btn">View all &rarr;</span>
          </a>
        <?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>
</div>

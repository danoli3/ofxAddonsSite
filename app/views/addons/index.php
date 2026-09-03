<div class="page-head">
  <h1>All Addons</h1>
  <input type="text" class="filter-box" id="addon-filter" placeholder="Filter addons&hellip;">
</div>

<div class="sort-tabs">
  <a href="/addons" class="<?= $sort === null ? 'active' : '' ?>">Name</a>
  <a href="/addons?sort=freshest" class="<?= $sort === 'freshest' ? 'active' : '' ?>">Freshest</a>
  <a href="/addons?sort=popular" class="<?= $sort === 'popular' ? 'active' : '' ?>">Popular</a>
</div>

<div id="search-results" class="addon-grid" hidden></div>

<div id="filterable-content">
  <?php ofx_addon_grid($addons, $hasMore, $nextUrl); ?>
</div>

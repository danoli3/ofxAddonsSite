<div class="page-head">
  <h1>openFrameworks <?= ofx_h($version) ?></h1>
  <div class="filter-wrap">
    <input type="text" class="filter-box" id="addon-filter" placeholder="Filter addons&hellip;">
    <span class="spinner search-spinner" aria-hidden="true"></span>
  </div>
</div>
<p class="page-intro">
  Addons often still work on newer openFrameworks releases than the version shown here &mdash; this
  reflects when an addon was last touched, not a hard compatibility ceiling.
  <a href="/versions">&larr; All versions</a>
</p>

<div id="search-results" class="addon-grid" hidden></div>

<div id="filterable-content">
  <?php if (empty($addons)): ?>
    <p class="empty-state">No addons for this version yet.</p>
  <?php endif; ?>

  <?php ofx_addon_grid($addons, $hasMore, $nextUrl); ?>
</div>

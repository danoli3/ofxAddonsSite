<div class="page-head">
  <h1>Browse</h1>
  <div class="filter-wrap">
    <input type="text" class="filter-box" id="browse-search" placeholder="Filter addons&hellip;">
  </div>
</div>
<p class="page-intro">
  Every addon at once - sorted, filtered and switched between tiles/table right here in the browser, instead of
  paging through categories. Inspired by
  <a href="https://daandelange.github.io/ofxAddonsJs/" target="_blank" rel="noopener">daandelange's ofxAddonsJs browser</a>.
</p>

<div class="browse-toolbar">
  <div class="browse-toolbar__group">
    <label for="browse-sort">Sort</label>
    <select id="browse-sort">
      <option value="name-asc">Name (A&ndash;Z)</option>
      <option value="stars-desc">Stars (most)</option>
      <option value="forks-desc">Forks (most)</option>
      <option value="pushed_at-desc">Updated (newest)</option>
      <option value="created_at-desc">Created (newest)</option>
    </select>
  </div>
  <div class="browse-toolbar__group">
    <label for="browse-category">Category</label>
    <select id="browse-category">
      <option value="">All categories</option>
    </select>
  </div>
  <div class="browse-toolbar__group view-toggle" role="group" aria-label="View">
    <button type="button" class="view-toggle__btn" data-view="tiles">Tiles</button>
    <button type="button" class="view-toggle__btn" data-view="table">Table</button>
  </div>
</div>

<p class="browse-status" id="browse-status">Loading&hellip;</p>

<div id="browse-tiles" class="addon-grid" hidden></div>

<div class="table-scroll" id="browse-table-wrap" hidden>
  <table class="admin-table browse-table">
    <thead>
      <tr>
        <th>Name</th>
        <th>Description</th>
        <th>Author</th>
        <th>Categories</th>
        <th>OF</th>
        <th>Stars</th>
        <th>Forks</th>
        <th>Updated</th>
        <th>Created</th>
        <th>Flags</th>
      </tr>
    </thead>
    <tbody id="browse-table-body"></tbody>
  </table>
</div>

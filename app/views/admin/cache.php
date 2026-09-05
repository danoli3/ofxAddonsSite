<?php
/** @var array $meta */
$labels = [
    'sitemap.xml' => 'Sitemap (XML)',
    'sitemap.json' => 'Sitemap (JSON)',
    'banned.json' => 'Banned addons feed',
    'addon-repos.json' => 'Addon repos feed',
    'categories-addons.json' => 'Categories homepage',
    'addons-name.json' => 'All Addons',
    'addons-freshest.json' => 'Freshest',
    'addons-popular.json' => 'Popular',
];
?>
<div class="page-head">
  <h1>Cache</h1>
</div>
<p class="page-intro">
  Pages/feeds cached to a file instead of re-querying the database on every request - regenerated
  automatically right after a crawl sync, or on demand below. <a href="/admin/repos">&larr; Back to admin</a>
</p>

<div class="admin-toolbar">
  <div class="admin-toolbar__group">
    <button type="button" id="admin-regenerate-caches">Regenerate all now</button>
    <span id="admin-regenerate-caches-status" class="admin-row__status"></span>
  </div>
</div>

<div class="table-scroll">
<table class="admin-table">
  <thead>
    <tr>
      <th>Page / feed</th>
      <th>Last generated</th>
      <th>Generation time</th>
      <th>Size</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($labels as $key => $label): ?>
      <?php $m = $meta[$key] ?? null; ?>
      <tr>
        <td><?= ofx_h($label) ?></td>
        <td><?= $m ? ofx_h(ofx_time_ago($m['generated_at'])) : '<em>never</em>' ?></td>
        <td><?= $m ? ofx_h(number_format((float)$m['duration_ms'], 1)) . ' ms' : '&mdash;' ?></td>
        <td><?= $m ? ofx_h(ofx_format_bytes((int)$m['bytes'])) : '&mdash;' ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

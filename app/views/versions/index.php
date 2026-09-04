<div class="page-head">
  <h1>Browse by openFrameworks version</h1>
</div>
<p class="page-intro">
  Guessed from each addon's last commit date against openFrameworks' real release history, unless a
  version has been read directly from the addon's README.
</p>

<?php if (empty($versions)): ?>
  <p class="empty-state">No versioned addons yet.</p>
<?php endif; ?>

<?php foreach ($versions as $version): ?>
  <?php $all = $addonsByVersion[$version] ?? []; ?>
  <section class="category-section">
    <h2 class="category-section__title">
      <a href="<?= ofx_h(ofx_version_url($version)) ?>">openFrameworks <?= ofx_h($version) ?></a>
      <span class="count"><?= count($all) ?></span>
      <?php if (count($all) > OFX_VERSION_PREVIEW_SIZE): ?>
        <a class="view-all" href="<?= ofx_h(ofx_version_url($version)) ?>">View all &rarr;</a>
      <?php endif; ?>
    </h2>
    <div class="addon-grid">
      <?php foreach (array_slice($all, 0, OFX_VERSION_PREVIEW_SIZE) as $addon): ?>
        <?php ofx_addon_partial($addon); ?>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>

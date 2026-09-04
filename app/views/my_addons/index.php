<?php
/** @var array $visibleRepos */
/** @var array $hiddenRepos */
/** @var array $bannedRepos */
/** @var array $categories */
/** @var array $repoCategoryIds */
/** @var array $publicAddons */
$allRepoCount = count($visibleRepos) + count($hiddenRepos) + count($bannedRepos);
?>
<div class="page-head">
  <h1>My Addons</h1>
  <a class="my-addons__edit-btn" href="#edit">Edit Addons &rarr;</a>
</div>

<?php if (!empty($publicAddons)): ?>
  <div class="addon-grid">
    <?php foreach ($publicAddons as $addon): ?>
      <?php ofx_addon_partial($addon); ?>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <p class="empty-state">
    Nothing of yours is publicly listed yet &mdash; categorize an addon below and it'll show up here exactly
    as visitors see it.
  </p>
<?php endif; ?>

<hr class="section-divider">

<div class="page-head" id="edit">
  <h1>Edit Addons</h1>
</div>
<p class="page-intro">
  Repos of yours the crawler has found. Categorize them, write your own description, hide one from public
  listings, or point at a custom thumbnail/GIF &mdash; changes here are yours; a crawl sync will never
  overwrite a description you've saved.
  <?php if ($allRepoCount === 0): ?>
    New to this? Read the <a href="/pages/howto">How To</a> guide for what makes a repo show up here.
  <?php else: ?>
    <a href="/pages/howto">How To</a>
  <?php endif; ?>
</p>

<?php if ($allRepoCount === 0): ?>
  <p class="empty-state">
    Nothing found under your Github account yet. If you've just published an addon, the crawler runs daily
    and should pick it up soon &mdash; make sure the repo name starts with <code>ofx</code>.
  </p>
<?php else: ?>
  <div class="table-scroll">
  <table class="admin-table" id="my-addons-table" data-endpoint="/my/addons">
    <thead>
      <tr>
        <th>Repo</th>
        <th>Description</th>
        <th>Categories</th>
        <th>Thumbnail URL</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($visibleRepos as $repo): ?>
        <?php ofx_my_addon_row_partial($repo, $categories, $repoCategoryIds[$repo['id']] ?? []); ?>
      <?php endforeach; ?>

      <?php if (!empty($hiddenRepos)): ?>
        <tr class="my-addons-divider">
          <td colspan="5">Hidden from public (<?= count($hiddenRepos) ?>)</td>
        </tr>
        <?php foreach ($hiddenRepos as $repo): ?>
          <?php ofx_my_addon_row_partial($repo, $categories, $repoCategoryIds[$repo['id']] ?? []); ?>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if (!empty($bannedRepos)): ?>
        <tr class="my-addons-divider my-addons-divider--banned">
          <td colspan="5">Banned (<?= count($bannedRepos) ?>)</td>
        </tr>
        <?php foreach ($bannedRepos as $repo): ?>
          <?php ofx_my_addon_row_partial($repo, $categories, $repoCategoryIds[$repo['id']] ?? [], true); ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>

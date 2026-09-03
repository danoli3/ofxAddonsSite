<div class="page-head">
  <h1>My Addons</h1>
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

<div class="page-head">
  <h1>Edit Addons</h1>
</div>
<p class="page-intro">
  Repos of yours the crawler has found. Categorize them, write your own description, hide one from public
  listings, or point at a custom thumbnail/GIF &mdash; changes here are yours; a crawl sync will never
  overwrite a description you've saved.
</p>

<?php if (empty($repos)): ?>
  <p class="empty-state">
    Nothing found under your Github account yet. If you've just published an addon, the crawler runs daily
    and should pick it up soon &mdash; make sure the repo name starts with <code>ofx</code>.
  </p>
<?php endif; ?>

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
    <?php foreach ($repos as $repo): ?>
      <tr class="admin-row" data-repo-id="<?= (int)$repo['id'] ?>" data-repo-name="<?= ofx_h($repo['name'] ?? $repo['full_name'] ?? '') ?>">
        <td>
          <a href="https://github.com/<?= ofx_h($repo['full_name']) ?>" target="_blank" rel="noopener">
            <?= ofx_h($repo['name']) ?>
          </a>
          <div class="admin-row__owner"><?= ofx_h($repo['type']) ?></div>
          <?php if ($repo['type'] === 'Addon'): ?>
            <a class="addon-card__more" href="/addons/<?= (int)$repo['id'] ?>">More info &rarr;</a>
          <?php endif; ?>
          <?php if (!empty($repo['hidden_by_owner'])): ?>
            <span class="tag tag--archived">Hidden from public</span>
          <?php endif; ?>
          <?php if ($repo['type'] === 'NonAddon'): ?>
            <?php if (!empty($repo['ban_appealed'])): ?>
              <span class="tag tag--curated">Ban appealed</span>
            <?php else: ?>
              <button type="button" class="my-addon-row__appeal-ban" data-repo-id="<?= (int)$repo['id'] ?>">Appeal ban</button>
            <?php endif; ?>
          <?php endif; ?>
        </td>
        <td class="admin-row__desc-cell">
          <textarea class="admin-row__desc" rows="5"
                    maxlength="<?= OFX_DESCRIPTION_MAX_LENGTH ?>"><?= ofx_h($repo['description'] ?? '') ?></textarea>
          <input type="hidden" class="admin-row__desc-generated" value="<?= !empty($repo['description_generated']) ? '1' : '0' ?>">
          <div class="admin-row__desc-meta">
            <span class="admin-row__char-count"></span>
            <?php if (!empty($repo['description_curated'])): ?>
              <span class="tag tag--curated" title="Saved - a crawl sync won't overwrite this">
                <?= !empty($repo['description_generated']) ? 'AI-generated' : 'Curated' ?>
              </span>
            <?php endif; ?>
            <?php if (empty($repo['description'])): ?>
              <button type="button" class="admin-row__generate-desc" title="Generate a description from the repo's README">
                &#10024; Generate
              </button>
            <?php endif; ?>
          </div>
        </td>
        <td>
          <?php ofx_category_picker($categories, $repoCategoryIds[$repo['id']] ?? []); ?>
        </td>
        <?php
          $detectedThumbnail = $repo['thumbnail_url_override']
              ?: (!empty($repo['has_thumbnail']) ? ofx_thumbnail_url($repo['full_name']) : '');
        ?>
        <td>
          <input type="url" class="my-addon-row__thumbnail" placeholder="https://.../image.png or .gif"
                 value="<?= ofx_h($detectedThumbnail) ?>">
          <label class="my-addon-row__hidden-label">
            <input type="checkbox" class="my-addon-row__hidden" <?= !empty($repo['hidden_by_owner']) ? 'checked' : '' ?>>
            Hide from public listings
          </label>
        </td>
        <td class="admin-row__actions">
          <button type="button" class="admin-row__save">Save</button>
          <span class="admin-row__status"></span>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

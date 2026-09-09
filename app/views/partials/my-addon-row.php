<?php
/** @var array $repo */
/** @var array $categories */
/** @var array $selectedCategoryIds */
/** @var bool $isBanned */
?>
<tr class="admin-row<?= $isBanned ? ' admin-row--banned' : '' ?>" data-repo-id="<?= (int)$repo['id'] ?>" data-repo-name="<?= ofx_h($repo['name'] ?? $repo['full_name'] ?? '') ?>">
  <td>
    <a href="https://github.com/<?= ofx_h($repo['full_name']) ?>" target="_blank" rel="noopener">
      <?= ofx_h($repo['name']) ?>
    </a>
    <div class="admin-row__owner"><?= ofx_h($repo['type']) ?></div>
    <?php if ($repo['type'] === 'Addon'): ?>
      <a class="addon-card__more" href="<?= ofx_h(ofx_addon_url($repo['full_name'])) ?>">More info &rarr;</a>
    <?php endif; ?>
    <?php if (!empty($repo['hidden_by_owner'])): ?>
      <span class="tag tag--archived">Hidden from public</span>
    <?php endif; ?>
    <?php if (in_array($repo['type'], OFX_REVIEWABLE_TYPES, true)): ?>
      <?php if (!empty($repo['ban_appealed'])): ?>
        <span class="tag tag--curated">Review requested</span>
      <?php else: ?>
        <button type="button" class="my-addon-row__appeal-ban" data-repo-id="<?= (int)$repo['id'] ?>">Ask for Admin Review</button>
      <?php endif; ?>
    <?php endif; ?>
  </td>
  <td class="admin-row__desc-cell">
    <div class="admin-row__desc-inner">
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
        <?php else: ?>
          <button type="button" class="admin-row__generate-desc" title="Add another AI-generated detail from the repo's README, appended to what's already there">
            &#10024; Generate more
          </button>
        <?php endif; ?>
      </div>
    </div>
  </td>
  <td>
    <?php ofx_category_picker($categories, $selectedCategoryIds); ?>
  </td>
  <?php
    // pre-fill with whatever's actually showing live for this addon when
    // there's no owner override yet - the crawler-detected
    // ofxaddons_thumbnail.png convention, or an admin-generated AI
    // thumbnail (ai_thumbnail_generated_at) - same priority order
    // ofx_addon_thumbnail_url() already uses on the public card/detail
    // page, so this field doesn't start blank (or miss a generated image
    // entirely) just because it duplicated only part of that logic
    $detectedThumbnail = ofx_addon_thumbnail_url($repo) ?? '';
  ?>
  <td>
    <img class="my-addon-row__thumbnail-preview" src="<?= ofx_h($detectedThumbnail) ?>" alt=""
         loading="lazy" <?= $detectedThumbnail === '' ? 'hidden' : '' ?>
         onerror="this.hidden = true" onload="this.hidden = false">
    <input type="url" class="my-addon-row__thumbnail" placeholder="https://.../image.png or .gif"
           value="<?= ofx_h($detectedThumbnail) ?>">
    <label class="my-addon-row__hidden-label">
      <input type="checkbox" class="my-addon-row__hidden" <?= !empty($repo['hidden_by_owner']) ? 'checked' : '' ?>>
      Hide from public listings
    </label>
  </td>
  <td class="admin-row__actions">
    <div class="admin-row__actions-inner">
      <button type="button" class="admin-row__save">Save</button>
      <span class="admin-row__status"></span>
    </div>
  </td>
</tr>
<?php $myOfVersion = ofx_addon_of_version($repo); ?>
<tr class="admin-row__subrow">
  <td colspan="5">
    <div class="version-picker version-picker--readonly">
      <span class="version-picker__label">OF version</span>
      <?php if ($myOfVersion): ?>
        <span class="tag tag--version<?= $myOfVersion['curated'] ? '' : ' tag--version-guess' ?>"
              title="<?= $myOfVersion['curated'] ? 'Confirmed by an admin, or by AI from this repo\'s README' : 'Guessed from the last commit date - not confirmed' ?>">
          OF <?= ofx_h($myOfVersion['version']) ?>
        </span>
      <?php else: ?>
        <span class="version-picker__unknown">no commits to guess from yet</span>
      <?php endif; ?>
    </div>
  </td>
</tr>

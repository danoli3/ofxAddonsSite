<tr class="admin-row" data-repo-id="<?= (int)$repo['id'] ?>" data-repo-name="<?= ofx_h($repo['name'] ?? $repo['full_name'] ?? '') ?>">
  <td>
    <a href="https://github.com/<?= ofx_h($repo['full_name']) ?>" target="_blank" rel="noopener">
      <?= ofx_h($repo['name']) ?>
    </a>
    <div class="admin-row__owner"><?= ofx_h($repo['user_login'] ?? '') ?></div>
    <a class="admin-row__url" href="https://github.com/<?= ofx_h($repo['full_name']) ?>" target="_blank" rel="noopener">
      github.com/<?= ofx_h($repo['full_name']) ?>
    </a>
    <div class="admin-row__updated">Last commit <?= ofx_h(ofx_time_ago($repo['pushed_at'] ?? null)) ?></div>
    <?php if ($repo['type'] === 'Addon'): ?>
      <a class="addon-card__more" href="<?= ofx_h(ofx_addon_url($repo['full_name'])) ?>">More info &rarr;</a>
    <?php endif; ?>
  </td>
  <td class="admin-row__desc-cell">
    <textarea class="admin-row__desc" rows="5"
              maxlength="<?= OFX_DESCRIPTION_MAX_LENGTH ?>"><?= ofx_h($repo['description'] ?? '') ?></textarea>
    <input type="hidden" class="admin-row__desc-generated" value="<?= !empty($repo['description_generated']) ? '1' : '0' ?>">
    <div class="admin-row__desc-meta">
      <span class="admin-row__char-count"></span>
      <?php if (!empty($repo['description_curated'])): ?>
        <span class="tag tag--curated" title="Saved by an admin - a crawl sync won't overwrite this">
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
      <!-- Github's own Copilot repo-overview chat is a UI-only feature with
           no public API to call it from here - this just opens the repo
           page so an admin can click Github's Copilot icon there themselves
           (useful when there's no README for the AI Generate button above
           to work from) and paste the result into the box on the left -->
      <a class="admin-row__ask-copilot" href="https://github.com/<?= ofx_h($repo['full_name']) ?>" target="_blank" rel="noopener"
         title="Opens the repo on Github - use Github's own Copilot icon there for an overview, then paste it in">
        Ask Copilot &rarr;
      </a>
    </div>
  </td>
  <td>
    <select class="admin-row__type">
      <?php foreach (OFX_REPO_TYPES as $type): ?>
        <option value="<?= ofx_h($type) ?>" <?= $repo['type'] === $type ? 'selected' : '' ?>>
          <?= ofx_h($type) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </td>
  <td>
    <?php ofx_category_picker($categories, $selectedCategoryIds); ?>
  </td>
  <td class="admin-row__actions">
    <button type="button" class="admin-row__save">Save</button>
    <button type="button" class="admin-row__ban" title="Not really an openFrameworks addon">Ban</button>
    <?php if (!empty($showDismissRequest) && !empty($repo['ban_appealed'])): ?>
      <button type="button" class="admin-row__dismiss-appeal" title="Classification stands - clear the review request">
        Dismiss request
      </button>
    <?php endif; ?>
    <span class="admin-row__status"></span>
  </td>
</tr>

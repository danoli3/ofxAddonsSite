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
    <?php if (!empty($repo['ai_triage_notes'])): ?>
      <div class="admin-row__ai-note" title="Free-text note from the AI triage submission - shown to reviewers only, never applied to anything. Sticks around after the suggestion is confirmed/denied, until a newer submission replaces it.">
        &#129302; <?= ofx_h($repo['ai_triage_notes']) ?>
      </div>
    <?php endif; ?>
    <?php if ($repo['type'] === 'Addon'): ?>
      <a class="addon-card__more" href="<?= ofx_h(ofx_addon_url($repo['full_name'])) ?>">More info &rarr;</a>
    <?php endif; ?>
    <?php $rowThumb = ofx_addon_thumbnail_url($repo); ?>
    <?php if ($rowThumb): ?>
      <img class="admin-row__thumb" src="<?= ofx_h($rowThumb) ?>" alt="" loading="lazy" onerror="this.hidden = true">
    <?php endif; ?>
    <!-- shown even when a thumbnail already exists - some repos' own
         ofxaddons_thumbnail.png is just the generic ofxAddonTemplate
         example image, not anything specific to that addon, so an admin
         needs a way to regenerate over it, not just fill in blanks -->
    <button type="button" class="admin-row__generate-thumb" data-repo-id="<?= (int)$repo['id'] ?>"
            title="Generate a 270x70 thumbnail with AI, from this repo's name/description/README">
      &#10024; <?= $rowThumb ? 'Regenerate Img' : 'Generate Img' ?>
    </button>
    <div class="admin-row__console" hidden>
      <span class="spinner"></span>
      <span class="admin-row__console-text"></span>
    </div>
  </td>
  <td class="admin-row__desc-cell">
    <div class="admin-row__desc-inner">
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
      <div class="admin-row__console" hidden>
        <span class="spinner"></span>
        <span class="admin-row__console-text"></span>
      </div>
    </div>
  </td>
  <td>
    <select class="admin-row__type">
      <?php foreach (OFX_REPO_TYPES as $type): ?>
        <option value="<?= ofx_h($type) ?>" <?= $repo['type'] === $type ? 'selected' : '' ?>>
          <?= $type === 'NonAddon' ? 'Banned' : ofx_h($type) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </td>
  <td>
    <?php ofx_category_picker($categories, $selectedCategoryIds); ?>
    <?php if (!empty($repo['categories_ai_curated'])): ?>
      <span class="tag tag--curated" title="These categories were AI-assigned and admin-confirmed via the review screen">AI Curated</span>
    <?php endif; ?>
  </td>
  <td class="admin-row__actions">
    <div class="admin-row__actions-inner">
      <button type="button" class="admin-row__save">Save</button>
      <button type="button" class="admin-row__ban" title="Not really an openFrameworks addon">Ban</button>
      <?php if (in_array($repo['type'], OFX_AI_TRIAGE_TYPES, true)): ?>
        <button type="button" class="admin-row__triage-flag<?= !empty($repo['ai_triage_priority_at']) ? ' is-flagged' : '' ?>"
                data-repo-id="<?= (int)$repo['id'] ?>"
                title="<?= !empty($repo['ai_triage_priority_at']) ? 'Flagged - click to unflag' : 'Jump this to the front of the next AI triage batch' ?>">
          &#128681; <?= !empty($repo['ai_triage_priority_at']) ? 'Flagged for triage' : 'Flag for triage' ?>
        </button>
      <?php endif; ?>
      <?php if (!empty($showDismissRequest) && !empty($repo['ban_appealed'])): ?>
        <button type="button" class="admin-row__dismiss-appeal" title="Classification stands - clear the review request">
          Dismiss request
        </button>
      <?php endif; ?>
      <span class="admin-row__status"></span>
    </div>
  </td>
</tr>
<tr class="admin-row__subrow">
  <td colspan="5">
    <?php ofx_version_picker($repo); ?>
  </td>
</tr>

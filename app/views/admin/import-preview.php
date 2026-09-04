<?php
/** @var array $diffs */
/** @var string $filename */
/** @var string|null $formAction */
$formAction = $formAction ?? '/admin/import/confirm';
?>
<div class="page-head">
  <h1>Review import</h1>
</div>
<p class="page-intro">
  Parsed <?= count($diffs) ?> entr<?= count($diffs) === 1 ? 'y' : 'ies' ?> from <?= ofx_h($filename) ?>.
  Nothing has been saved yet - review each row below, uncheck any you don't want applied, then confirm.
  <a href="/admin/repos">&larr; Back to admin</a>
</p>

<?php if (empty($diffs)): ?>
  <p class="empty-state">Nothing to review.</p>
<?php else: ?>
  <form method="post" action="<?= ofx_h($formAction) ?>">
    <input type="hidden" name="_csrf" value="<?= ofx_h(ofx_csrf_token()) ?>">
    <div class="import-diff-list">
      <?php foreach ($diffs as $d): ?>
        <?php $hasChanges = $d['found'] && (!empty($d['added_categories']) || !empty($d['removed_categories']) || $d['version_changed'] || !empty($d['type_changed'])); ?>
        <?php $typeConfirmed = $d['found'] && !empty($d['proposed_type']) && empty($d['type_changed']); ?>
        <div class="import-diff-row<?= !$d['found'] ? ' import-diff-row--missing' : '' ?><?= ($d['found'] && !$hasChanges) ? ' import-diff-row--nochange' : '' ?>">
          <?php if ($d['found']): ?>
            <input type="checkbox" class="import-diff-row__check" name="confirm[]" value="<?= (int)$d['index'] ?>" checked>
            <input type="hidden" name="entry_data[<?= (int)$d['index'] ?>]" value="<?= ofx_h($d['entry_json']) ?>">
          <?php endif; ?>
          <div class="import-diff-row__body">
            <a href="https://github.com/<?= ofx_h($d['full_name']) ?>" target="_blank" rel="noopener">
              <?= ofx_h($d['full_name']) ?>
            </a>
            <?php if (!$d['found']): ?>
              <span class="tag tag--archived">Not found in this database - will be skipped</span>
            <?php else: ?>
              <?php if (!$hasChanges && !$typeConfirmed): ?>
                <span class="tag">No changes</span>
              <?php endif; ?>
              <?php if (!empty($d['proposed_type'])): ?>
                <?php $typeLabel = fn($t) => $t === 'NonAddon' ? 'Banned' : $t; ?>
                <div class="import-diff-row__version">
                  <?php if (!empty($d['type_changed'])): ?>
                    Type: <?= ofx_h($typeLabel($d['current_type'])) ?> &rarr; <strong><?= ofx_h($typeLabel($d['proposed_type'])) ?></strong>
                  <?php else: ?>
                    Type: <strong><?= ofx_h($typeLabel($d['proposed_type'])) ?></strong>
                    <span class="import-diff-row__confirmed">(prior decision, unchanged)</span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
              <div class="import-diff-row__cats">
                <?php foreach ($d['unchanged_categories'] as $c): ?>
                  <span class="tag"><?= ofx_h($c) ?></span>
                <?php endforeach; ?>
                <?php foreach ($d['added_categories'] as $c): ?>
                  <span class="tag import-diff-tag--added">+ <?= ofx_h($c) ?></span>
                <?php endforeach; ?>
                <?php foreach ($d['removed_categories'] as $c): ?>
                  <span class="tag import-diff-tag--removed">&minus; <?= ofx_h($c) ?></span>
                <?php endforeach; ?>
              </div>
              <?php if ($d['version_changed']): ?>
                <div class="import-diff-row__version">
                  OF version:
                  <?php if ($d['current_version']): ?>
                    <?= ofx_h($d['current_version']) ?>
                  <?php else: ?>
                    <em>none</em>
                  <?php endif; ?>
                  &rarr; <strong><?= ofx_h($d['proposed_version']) ?></strong>
                </div>
              <?php endif; ?>
              <?php if (!empty($d['notes'])): ?>
                <div class="import-diff-row__notes">&ldquo;<?= ofx_h($d['notes']) ?>&rdquo;</div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="submit" class="import-diff-confirm-all">Confirm selected &amp; save to database</button>
  </form>
<?php endif; ?>

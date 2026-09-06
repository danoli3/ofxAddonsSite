<?php
/** @var array $repo */
$vpConfirmed = !empty($repo['of_version_curated']) ? $repo['of_version'] : null;
$vpGuess = ofx_infer_of_version($repo['pushed_at'] ?? null);
?>
<div class="version-picker">
  <span class="version-picker__label">OF version</span>
  <?php foreach (OFX_VERSIONS as $v): ?>
    <?php
      $vpVersion = $v['version'];
      $vpIsConfirmed = $vpConfirmed === $vpVersion;
      $vpIsGuessed = !$vpConfirmed && $vpGuess === $vpVersion;
    ?>
    <button type="button"
            class="version-chip<?= $vpIsConfirmed ? ' is-confirmed' : '' ?><?= $vpIsGuessed ? ' is-guessed' : '' ?>"
            data-repo-id="<?= (int)$repo['id'] ?>" data-version="<?= ofx_h($vpVersion) ?>"
            title="<?= $vpIsConfirmed
                ? 'Confirmed by an admin/AI-from-readme - click again to clear and fall back to guessing'
                : ($vpIsGuessed ? 'Guessed from the last commit date, not confirmed - click to confirm' : 'Click to confirm this is the version it targets') ?>">
      <?= ofx_h($vpVersion) ?>
    </button>
  <?php endforeach; ?>
  <?php if (!$vpConfirmed && !$vpGuess): ?>
    <span class="version-picker__unknown">no commits to guess from yet</span>
  <?php endif; ?>
</div>

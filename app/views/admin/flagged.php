<?php /** @var array $repos */ ?>
<div class="page-head">
  <h1>Flagged</h1>
</div>
<p class="page-intro">
  Repos <a href="/admin/security">the security scan</a> matched - a script/markup injection attempt aimed at a
  visitor's browser, or a prompt-injection attempt aimed at whatever reads the repo's README on a human's
  behalf (the local model behind the AI triage API). Auto-quarantined to Banned at detection time unless
  already a confirmed Addon. <strong>Unflag</strong> if this was a false positive - it clears the flag only,
  not the type. <strong>Unflag &amp; Ban</strong> if the detection was right and this one wasn't already
  Banned - sets it Banned and clears the flag together.
  <a href="/admin/repos">&larr; Back to admin</a>
</p>

<?php if (empty($repos)): ?>
  <p class="empty-state">Nothing currently flagged.</p>
<?php else: ?>
  <div class="table-scroll">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Repo</th>
        <th>Type</th>
        <th>Reason</th>
        <th>Flagged</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($repos as $repo): ?>
        <tr id="flagged-row-<?= (int)$repo['id'] ?>">
          <td><a href="https://github.com/<?= ofx_h($repo['full_name']) ?>" target="_blank" rel="noopener"><?= ofx_h($repo['full_name']) ?></a></td>
          <td><span class="tag tag--fail"><?= ofx_h($repo['type'] === 'NonAddon' ? 'Banned' : $repo['type']) ?></span></td>
          <td><?= ofx_h($repo['security_flag_reason'] ?? '') ?></td>
          <td><?= $repo['security_flagged_at'] ? ofx_h(ofx_time_ago($repo['security_flagged_at'])) : '&mdash;' ?></td>
          <td class="admin-row__actions-inner">
            <button type="button" class="admin-unflag-btn" data-id="<?= (int)$repo['id'] ?>">Unflag</button>
            <?php if ($repo['type'] !== 'NonAddon'): ?>
              <button type="button" class="admin-unflag-ban-btn" data-id="<?= (int)$repo['id'] ?>"
                      title="The detection was right - set this Banned and clear the flag together">
                Unflag &amp; Ban
              </button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>

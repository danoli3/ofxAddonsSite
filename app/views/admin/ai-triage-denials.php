<?php /** @var array $denials */ ?>
<div class="page-head">
  <h1>AI triage denials</h1>
</div>
<p class="page-intro">
  Suggestions denied on <a href="/admin/ai-triage/review">the AI triage review screen</a>, with the note handed
  back to the model as feedback on its next batch (see "denied_feedback" on <code>GET /api/triage/batch</code>).
  Read-only - there's nothing to undo here, the repo itself was never changed by a denial.
  <a href="/admin/repos">&larr; Back to admin</a>
</p>

<?php if (empty($denials)): ?>
  <p class="empty-state">Nothing denied yet.</p>
<?php else: ?>
  <div class="table-scroll">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Repo</th>
        <th>AI suggested</th>
        <th>Admin's note</th>
        <th>Denied</th>
        <th>By</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($denials as $d): ?>
        <?php $entry = json_decode((string)($d['entry_json'] ?? ''), true); ?>
        <tr>
          <td><a href="https://github.com/<?= ofx_h($d['full_name']) ?>" target="_blank" rel="noopener"><?= ofx_h($d['full_name']) ?></a></td>
          <td>
            <?php if (is_array($entry) && !empty($entry['type'])): ?>
              <span class="tag"><?= ofx_h($entry['type'] === 'NonAddon' ? 'Banned' : $entry['type']) ?></span>
            <?php endif; ?>
            <?php if (is_array($entry) && !empty($entry['categories'])): ?>
              <?php foreach ((array)$entry['categories'] as $c): ?>
                <span class="tag"><?= ofx_h($c) ?></span>
              <?php endforeach; ?>
            <?php endif; ?>
          </td>
          <td><?= !empty($d['reason']) ? ofx_h($d['reason']) : '<span class="empty-state">&mdash;</span>' ?></td>
          <td><?= ofx_h(ofx_time_ago($d['denied_at'])) ?></td>
          <td><?= !empty($d['denied_by_login']) ? ofx_h($d['denied_by_login']) : '&mdash;' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>

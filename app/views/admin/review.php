<div class="page-head">
  <h1>Review requests</h1>
</div>
<p class="page-intro">
  Addons an owner has asked for a manual look at &mdash; banned (NonAddon) or auto-classified as Spam by the
  sync pipeline for lacking any recognizable addon structure. Reclassify and Save, or Dismiss if the
  existing classification stands.
  <a href="/admin/repos">&larr; Back to admin</a>
</p>

<?php if (empty($repos)): ?>
  <p class="empty-state">No pending review requests.</p>
<?php else: ?>
  <div class="table-scroll">
  <table class="admin-table" data-endpoint="/admin/repos">
    <thead>
      <tr>
        <th>Repo</th>
        <th>Description</th>
        <th>Type</th>
        <th>Categories</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($repos as $repo): ?>
        <?php ofx_admin_row_partial($repo, $categories, $repoCategoryIds[$repo['id']] ?? [], true); ?>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>

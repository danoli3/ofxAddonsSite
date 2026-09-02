<div class="admin-row__categories">
  <?php foreach ($categories as $category): ?>
    <?php $selected = in_array((int)$category['id'], $selectedCategoryIds, true); ?>
    <button type="button" class="category-chip<?= $selected ? ' is-selected' : '' ?>" data-category-id="<?= (int)$category['id'] ?>">
      <?= ofx_h($category['name']) ?>
    </button>
  <?php endforeach; ?>
</div>

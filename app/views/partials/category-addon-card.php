<div class="addon-card-wrap<?= !empty($addon['featured']) ? ' is-featured' : '' ?>">
  <?php if ($isAdmin): ?>
    <button type="button" class="feature-toggle" data-repo-id="<?= (int)$addon['id'] ?>"
            data-category-id="<?= $categoryId ?>" data-featured="<?= !empty($addon['featured']) ? '1' : '0' ?>">
      <?= !empty($addon['featured']) ? '★ Featured' : '☆ Feature' ?>
    </button>
  <?php endif; ?>
  <?php ofx_addon_partial($addon); ?>
</div>

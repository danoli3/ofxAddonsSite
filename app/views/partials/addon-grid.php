<div class="addon-grid" data-has-more="<?= $hasMore ? '1' : '0' ?>" data-next-url="<?= ofx_h($nextUrl) ?>">
  <?php foreach ($addons as $addon): ?>
    <?php ofx_addon_partial($addon); ?>
  <?php endforeach; ?>
</div>
<div class="grid-sentinel"></div>
<div class="grid-loading" hidden>
  <span class="spinner"></span> Loading more&hellip;
</div>
<p class="grid-end" hidden>You&rsquo;ve reached the end.</p>

<?php

$user = ofx_current_user();
$flash = ofx_flash_get();
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= ofx_h($title ?? 'ofxAddons') ?> · ofxAddons</title>
  <meta name="description" content="The central place to discover openFrameworks addons.">
  <link rel="icon" href="/app/assets/img/ofxlogo-small.png">
  <link rel="stylesheet" href="<?= ofx_h(ofx_asset_url('/app/assets/css/site.css')) ?>">
</head>
<body>
  <header class="site-header">
    <div class="wrap">
      <a class="brand" href="/categories">
        <img src="/app/assets/img/ofxlogo-small.png" alt="">
        ofxAddons
      </a>
      <nav class="site-nav">
        <a href="/categories">Categories</a>
        <a href="/addons">All Addons</a>
        <a href="/freshest">Freshest</a>
        <a href="/popular">Popular</a>
        <a href="/unsorted">Unsorted</a>
        <a href="/contributors">Contributors</a>
        <?php if ($user): ?>
          <a href="/my/addons">My Addons</a>
          <?php if (!empty($user['admin'])): ?>
            <a href="/admin/repos">Admin</a>
          <?php endif; ?>
          <a href="/logout">Sign out (<?= ofx_h($user['login']) ?>)</a>
        <?php else: ?>
          <a href="/auth/github">Sign in with GitHub</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <?php if ($flash): ?>
    <div class="flash"><div class="wrap"><?= ofx_h($flash) ?></div></div>
  <?php endif; ?>

  <main class="wrap"><?= $content ?></main>

  <footer class="site-footer">
    <div class="wrap">
      <p>ofxAddons &mdash; the central place to discover
        <a href="https://openframeworks.cc" target="_blank" rel="noopener">openFrameworks</a> addons.
        <a href="/pages/howto">How To</a></p>
    </div>
  </footer>

  <script src="/app/assets/js/vendor/jquery-3.7.1.min.js"></script>
  <script src="<?= ofx_h(ofx_asset_url('/app/assets/js/site.js')) ?>"></script>
</body>
</html>

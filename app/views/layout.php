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
  <meta name="csrf-token" content="<?= ofx_h(ofx_csrf_token()) ?>">
  <link rel="icon" href="/app/assets/img/ofxlogo-small.png">
  <link rel="stylesheet" href="<?= ofx_h(ofx_asset_url('/app/assets/css/site.min.css')) ?>">
</head>
<body id="top">
  <!-- the anchor target is <body>, not .site-header - the header is
       position: sticky and already visually pinned at the viewport top
       at any scroll position, so the browser treats it as "already in
       view" and never actually scrolls when #top points at it -->
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
        <a href="/versions">Versions</a>
        <a href="/contributors">Contributors</a>
        <?php if ($user): ?>
          <a href="/my/addons">My Addons</a>
          <?php if (!empty($user['admin'])): ?>
            <a href="/admin/repos">Admin</a>
          <?php endif; ?>
          <a href="/logout">Sign out (<?= ofx_h($user['login']) ?>)</a>
        <?php else: ?>
          <!-- shown only while logged out - once signed in, My Addons
               links out to the same guide (see my_addons/index.php) so
               it isn't competing for space in the nav bar too -->
          <a href="/pages/howto">How To</a>
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
      <p><a href="#top" class="site-footer__brand" title="Back to top">ofxAddons</a> &mdash; the central place to discover
        <a href="https://openframeworks.cc" target="_blank" rel="noopener">openFrameworks</a> addons.
        <a href="/pages/howto">How To</a>
        &middot; <a href="/about-openframeworks">About openFrameworks</a>
        &middot; <a href="/history">History &amp; Credits</a>
        &middot; <a href="/sitemap">Sitemap</a></p>
      <p class="site-footer__credit">
        <a class="of-badge of-badge--footer" href="/about-openframeworks">
          <span class="of-badge__logo" aria-hidden="true"></span>
          openFrameworks
        </a>
        is an independent, community-maintained toolkit &mdash; ofxAddons is a fan-run directory and isn&rsquo;t
        officially affiliated with the openFrameworks project.
      </p>
    </div>
  </footer>

  <script src="/app/assets/js/vendor/jquery-3.7.1.min.js"></script>
  <script src="<?= ofx_h(ofx_asset_url('/app/assets/js/site.min.js')) ?>"></script>
</body>
</html>

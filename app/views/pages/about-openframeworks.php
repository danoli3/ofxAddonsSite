<?php /** @var array $commits */ ?>
<div class="page-head">
  <h1>About openFrameworks</h1>
</div>

<div class="prose">

<div class="about-of__hero-logo">
  <span class="of-badge__logo" aria-hidden="true"></span>
</div>

<h2>What is it?</h2>
<p><a href="https://openframeworks.cc" target="_blank" rel="noopener">openFrameworks</a> is an open-source, C++ toolkit for creative coding &mdash; the kind of code artists, designers and students reach for to build interactive installations, generative visuals, sound-reactive work, physical computing projects, and other real-time, graphics-heavy software. It's free, cross-platform (macOS, Windows, Linux, iOS, Android and Raspberry Pi), and has been maintained by a volunteer community since 2005. The core toolkit itself lives at <a href="https://github.com/openframeworks/openFrameworks" target="_blank" rel="noopener">github.com/openframeworks/openFrameworks</a>.</p>

<h2>What it gives you</h2>
<p>Rather than reinvent the basics, openFrameworks bundles together well-established libraries for the things creative coding projects need most, behind one consistent, approachable C++ API. You get a window and a render loop in a few lines, then build up from there. Some of what's included:</p>
<ul class="about-of__libs">
  <li><strong>OpenGL / GLFW / GLEW</strong> &mdash; windowing and the graphics pipeline</li>
  <li><strong>rtAudio</strong> &mdash; cross-platform realtime audio input/output</li>
  <li><strong>FreeType</strong> &mdash; font loading and text rendering</li>
  <li><strong>FreeImage</strong> &mdash; loading/saving common image formats</li>
  <li><strong>assimp</strong> &mdash; importing 3D model formats (obj, fbx, dae, and more)</li>
  <li><strong>Poco</strong> &mdash; networking, filesystem, and threading utilities</li>
  <li><strong>Cairo</strong> &mdash; 2D vector graphics rendering</li>
  <li><strong>curl / OpenSSL</strong> &mdash; HTTP(S) requests</li>
  <li><strong>glm</strong> &mdash; vector/matrix math for graphics</li>
  <li><strong>tess2</strong> &mdash; polygon tessellation and triangulation</li>
  <li><strong>RapidJSON</strong> &mdash; JSON parsing</li>
</ul>

<?php if (!empty($commits)): ?>
  <h2>Recent changes</h2>
  <p>The latest commits to the core toolkit's default branch:</p>
  <ul class="about-of__commits">
    <?php foreach ($commits as $c): ?>
      <li>
        <a href="<?= ofx_h($c['url'] ?? 'https://github.com/openframeworks/openFrameworks') ?>" target="_blank" rel="noopener">
          <code><?= ofx_h($c['sha']) ?></code> <?= ofx_h($c['message']) ?>
        </a>
        <?php if (!empty($c['date'])): ?>
          <span class="about-of__commit-date"><?= ofx_h(ofx_time_ago($c['date'])) ?></span>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <p><a href="https://github.com/openframeworks/openFrameworks/commits/master" target="_blank" rel="noopener">See the full commit history &rarr;</a></p>
<?php endif; ?>

<h2>Where addons fit in</h2>
<p>The core toolkit stays intentionally small. Anything more specialized &mdash; support for a particular camera or sensor, a physics engine, a computer-vision pipeline, a GUI library &mdash; lives instead in an <strong>addon</strong>: a self-contained folder of code that drops into an openFrameworks project and extends it. Addon names conventionally start with <code>ofx</code> (e.g. <code>ofxKinect</code>, <code>ofxBox2d</code>).</p>
<p>That convention is exactly what this site is built around: <a href="/categories">ofxAddons</a> crawls GitHub for <code>ofx</code>-prefixed repositories and organizes them into a browsable, searchable directory, so finding the addon you need doesn't mean guessing at GitHub search terms.</p>

<h2>Getting started</h2>
<p>The official <a href="https://openframeworks.cc/download/" target="_blank" rel="noopener">download page</a> has setup instructions and IDE-specific project generators for every supported platform, and the community-written <a href="https://openframeworks.cc/ofBook/" target="_blank" rel="noopener">ofBook</a> is a solid next step once you're up and running. From there, <a href="/pages/howto">our own How To page</a> covers installing and managing addons specifically.</p>

<h2>A note on this site</h2>
<p>ofxAddons is an independent, fan-run project. It isn't part of the official openFrameworks toolkit and isn't operated or endorsed by the openFrameworks project or its maintainers &mdash; it's simply a directory built on top of the public GitHub metadata for <code>ofx</code>-prefixed repositories, to make that ecosystem easier to browse.</p>

</div>

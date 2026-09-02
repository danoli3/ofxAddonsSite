<div class="page-head">
  <h1>How To</h1>
</div>

<div class="prose">

<h2>What's an openFrameworks addon?</h2>
<p>An addon is code that extends <a href="https://openframeworks.cc" target="_blank" rel="noopener">openFrameworks</a>, usually one of two ways: wrapping an external library so it's easy to use from OF (e.g. a Kinect or MIDI wrapper), or packaging up a reusable piece of your own OF code so other people don't have to solve the same problem twice.</p>

<h2>Installing an addon</h2>
<p>Every addon on this site links straight to its GitHub repo. Clone it into your OF install's <code>addons/</code> folder:</p>
<pre>cd openFrameworks/addons/
git clone https://github.com/owner/ofxSomeAddon</pre>
<p>Open the project files in the addon's <code>example</code> folder (not every addon has one) and build. If it doesn't build cleanly, check the addon's own README for dependencies &mdash; being listed here isn't a guarantee it works, it just means the crawler found it.</p>

<h2>Addon folder structure</h2>
<p>For your addon to work smoothly when someone drops it into their <code>addons/</code> folder, follow the standard layout:</p>
<pre>ofxMyAddon/
  src/
    ofxMyAddon.h
    ofxMyAddon.cpp
  libs/              (only if wrapping an external library)
  example/
    src/
      main.cpp
      ofApp.h
      ofApp.cpp</pre>
<p>Multiple examples are fine &mdash; just prefix each folder with <code>example-</code>, e.g. <code>example-basic</code>, <code>example-advanced</code>.</p>

<h2>Getting listed</h2>
<p>There's no submission form &mdash; push your addon to GitHub with an <code>ofx</code> prefix in the repo name and the crawler picks it up automatically (it runs every few hours). From there it shows up under <a href="/unsorted">Unsorted</a> until someone categorizes it.</p>

<h2>Adding a thumbnail</h2>
<p>Addons can show a small header image on their card. Add a <strong>270&times;70px</strong> PNG named <code>ofxaddons_thumbnail.png</code> to the root of your repo, and it'll show up automatically on the next crawl.</p>

<h2>Is my addon "done"?</h2>
<ul>
  <li>Does the description/README explain what it does?</li>
  <li>Do you list which platforms you support?</li>
  <li>Is there at least one working example?</li>
  <li>Does it build against a recent OF release?</li>
  <li>Is the license clear, and does it credit any code you built on?</li>
</ul>

<h2>How this site finds addons</h2>
<p>A crawler searches GitHub for repos matching the <code>ofx</code> prefix, checks each one's folder structure and file listing, and stores what it finds. Nothing here is manually reviewed for quality &mdash; categorization (sorting an addon into GUI, Graphics, Sound, etc.) is a manual pass an admin does afterward, and repos that turn out to have nothing to do with openFrameworks (same name prefix by coincidence) get filtered out from there.</p>

</div>

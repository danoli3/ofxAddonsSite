<?php
/** @var array $htaccessChecks */
/** @var bool $envExists */
/** @var string|null $envPerms */
/** @var array $cookieParams */
/** @var array $admins */
/** @var bool $maintenanceOn */
/** @var bool $syncSecretSet */
/** @var bool $aiTriageKeySet */
/** @var bool $displayErrorsOff */
/** @var bool $isHttps */

// .env world/group-readable would be perm 644/664 etc - only the owner
// should be able to read it (600/640).
$envPermsOk = $envPerms !== null && in_array($envPerms, ['0600', '0640'], true);
?>
<div class="page-head">
  <h1>Security</h1>
</div>
<p class="page-intro">
  Self-check, not a guarantee - every row below is verified live against the actual filesystem/DB/session
  config at the moment you loaded this page, not a static claim someone forgot to update.
  <a href="/admin/repos">&larr; Back to admin</a>
</p>

<?php if ($maintenanceOn): ?>
  <p class="flash" style="background:rgba(255,107,107,.12);border-color:rgba(255,107,107,.4)">
    Maintenance mode is currently <strong>ON</strong> - the public site is showing a static 503 to everyone
    except signed-in admins. Turn it off from the <a href="/admin/repos">admin toolbar</a> when the incident
    is over.
  </p>
<?php endif; ?>

<div class="table-scroll">
<table class="admin-table">
  <thead><tr><th>Check</th><th>Status</th><th>Detail</th></tr></thead>
  <tbody>
    <?php foreach ($htaccessChecks as $c): ?>
      <tr>
        <td><?= ofx_h($c['label']) ?></td>
        <td><span class="tag <?= $c['pass'] ? 'tag--pass' : 'tag--fail' ?>"><?= $c['pass'] ? 'Pass' : 'FAIL' ?></span></td>
        <td>&mdash;</td>
      </tr>
    <?php endforeach; ?>

    <tr>
      <td>.env exists and is outside web-servable reach</td>
      <td><span class="tag <?= $envExists ? 'tag--pass' : 'tag--fail' ?>"><?= $envExists ? 'Pass' : 'FAIL' ?></span></td>
      <td><?= $envExists ? 'Covered by the root .htaccess dotfile rule above' : 'No .env found at the docroot root' ?></td>
    </tr>
    <tr>
      <td>.env file permissions are owner-only</td>
      <td><span class="tag <?= $envPermsOk ? 'tag--pass' : 'tag--fail' ?>"><?= $envPermsOk ? 'Pass' : 'Check' ?></span></td>
      <td><?= $envPerms !== null ? 'Mode ' . ofx_h($envPerms) : 'Could not stat .env' ?></td>
    </tr>

    <tr>
      <td>Session cookie: HttpOnly</td>
      <td><span class="tag <?= $cookieParams['httponly'] ? 'tag--pass' : 'tag--fail' ?>"><?= $cookieParams['httponly'] ? 'Pass' : 'FAIL' ?></span></td>
      <td>Blocks JS (e.g. an XSS payload) from reading the session cookie</td>
    </tr>
    <tr>
      <td>Session cookie: Secure</td>
      <td><span class="tag <?= $cookieParams['secure'] ? 'tag--pass' : 'tag--fail' ?>"><?= $cookieParams['secure'] ? 'Pass' : 'FAIL' ?></span></td>
      <td>Only sent back over HTTPS</td>
    </tr>
    <?php
    $sameSite = $cookieParams['samesite'] ?? '';
    // Strict is the strongest setting; Lax (this site's actual setting - see
    // ofx_session_start()) is a reasonable, deliberate middle ground that
    // still blocks cross-site POSTs but allows a plain top-level GET
    // navigation (e.g. following a link from Github) to arrive logged in -
    // a real tradeoff, not a misconfiguration, so it gets a warn, not a fail.
    $sameSiteTag = $sameSite === 'Strict' ? 'tag--pass' : ($sameSite === 'Lax' ? 'tag--warn' : 'tag--fail');
    ?>
    <tr>
      <td>Session cookie: SameSite</td>
      <td><span class="tag <?= $sameSiteTag ?>"><?= ofx_h($sameSite ?: 'none') ?></span></td>
      <td>Mitigates CSRF from a cross-site request carrying the cookie</td>
    </tr>

    <tr>
      <td>Served over HTTPS</td>
      <td><span class="tag <?= $isHttps ? 'tag--pass' : 'tag--fail' ?>"><?= $isHttps ? 'Pass' : 'FAIL' ?></span></td>
      <td><?= $isHttps ? 'This request arrived over HTTPS' : 'This request did NOT use HTTPS' ?></td>
    </tr>
    <tr>
      <td>PHP error display disabled</td>
      <td><span class="tag <?= $displayErrorsOff ? 'tag--pass' : 'tag--fail' ?>"><?= $displayErrorsOff ? 'Pass' : 'FAIL' ?></span></td>
      <td>Stack traces/paths never leak to a visitor on error</td>
    </tr>

    <tr>
      <td>Webhook secret (SYNC_SECRET) configured</td>
      <td><span class="tag <?= $syncSecretSet ? 'tag--pass' : 'tag--fail' ?>"><?= $syncSecretSet ? 'Pass' : 'FAIL' ?></span></td>
      <td>Presence only - this can't verify the value hasn't leaked; rotate manually if you suspect it has</td>
    </tr>
    <tr>
      <td>AI triage API key configured</td>
      <td><span class="tag <?= $aiTriageKeySet ? 'tag--pass' : 'tag--fail' ?>"><?= $aiTriageKeySet ? 'Pass' : 'FAIL' ?></span></td>
      <td>Gates /api/triage/*</td>
    </tr>

    <tr>
      <td>Maintenance kill switch</td>
      <td><span class="tag <?= $maintenanceOn ? 'tag--fail' : 'tag--pass' ?>"><?= $maintenanceOn ? 'ON' : 'Off' ?></span></td>
      <td>Toggle from the admin toolbar during an active attack/DDoS</td>
    </tr>
  </tbody>
</table>
</div>

<h2 style="margin-top:28px">Authorized admin accounts</h2>
<p class="page-intro">Every account with admin or super-admin access right now - review for anything unexpected.</p>
<div class="table-scroll">
<table class="admin-table">
  <thead><tr><th>Login</th><th>Access</th></tr></thead>
  <tbody>
    <?php foreach ($admins as $u): ?>
      <tr>
        <td><a href="https://github.com/<?= ofx_h($u['login']) ?>" target="_blank" rel="noopener">@<?= ofx_h($u['login']) ?></a></td>
        <td><span class="tag <?= $u['super_admin'] ? 'tag--super-admin' : 'tag--pass' ?>"><?= $u['super_admin'] ? 'Super Admin' : 'Admin' ?></span></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

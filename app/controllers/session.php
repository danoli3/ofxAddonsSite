<?php
declare(strict_types=1);

function ofx_session_new(): void
{
    ofx_redirect(ofx_github_authorize_url());
}

function ofx_session_create(): void
{
    ofx_session_start();
    $code = $_GET['code'] ?? null;
    $state = $_GET['state'] ?? null;

    if (!$code || !$state || !hash_equals($_SESSION['oauth_state'] ?? '', (string)$state)) {
        $_SESSION['flash'] = 'Login failed — invalid request.';
        ofx_redirect('/categories');
        return;
    }
    unset($_SESSION['oauth_state']);

    $githubUser = ofx_github_exchange_code((string)$code);
    if (!$githubUser) {
        $_SESSION['flash'] = 'Login failed — could not reach GitHub.';
        ofx_redirect('/categories');
        return;
    }

    $user = ofx_login_or_create_user($githubUser);

    // new session id on every login - stops session fixation (an
    // attacker handing a victim a pre-chosen session id before they
    // authenticate, then reusing that same id afterward)
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['flash'] = 'Signed in!';

    ofx_redirect('/categories');
}

function ofx_session_destroy(): void
{
    ofx_session_start();
    unset($_SESSION['user_id']);
    $_SESSION['flash'] = 'Signed out!';
    ofx_redirect('/categories');
}

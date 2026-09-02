<?php
declare(strict_types=1);

function ofx_log_admin_action(PDO $pdo, ?int $userId, string $action, ?int $repoId, ?string $details = null): void
{
    try {
        $pdo->prepare(
            'INSERT INTO admin_logs (user_id, action, repo_id, details, created_at) VALUES (?, ?, ?, ?, NOW())'
        )->execute([$userId, $action, $repoId, $details]);
    } catch (Throwable $e) {

    }
}

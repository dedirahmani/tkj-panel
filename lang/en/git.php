<?php

return [
    'title' => 'Git Deploy',
    'subtitle' => 'Connect a GitHub repo, auto-pull on push via webhook, and run build commands.',
    'repo_url' => 'Repository URL',
    'repo_hint' => 'Public repo: https://github.com/user/repo.git · private repo: git@github.com:user/repo.git (uses the deploy key below).',
    'branch' => 'Branch',
    'build_command' => 'Build / deploy commands',
    'build_hint' => 'Runs after pull, e.g. composer install --no-dev && php artisan migrate --force',
    'webhook' => 'Webhook URL',
    'webhook_hint' => 'Add this as a GitHub webhook (Push events) to auto-deploy.',
    'deploy_now' => 'Deploy now',
    'deploy_queued' => 'Deploy queued to the agent.',
    'last_status' => 'Last status',
    'last_deployed' => 'Last deployed',
    'not_configured' => 'Not configured yet.',
    'configure' => 'Git Deploy',
    'output' => 'Last deploy output',
    'deploy_key' => 'Deploy key (private repo)',
    'deploy_key_hint' => 'Add this public key on GitHub: Settings → Deploy keys → Add deploy key (read access is enough). Then click "Deploy now".',
    'deploy_key_pending' => 'Waiting for the agent to create the deploy key… reload the page in a moment.',
    'status' => [
        'queued' => 'queued', 'running' => 'running', 'success' => 'success', 'failed' => 'failed',
    ],
];

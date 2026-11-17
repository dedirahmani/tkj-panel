<?php

return [
    'title' => 'Dashboard',

    // Server status
    'status_heading' => 'Server status',
    'agent' => 'Agent',
    'agent_ok' => 'Processing the queue. Last job finished :time.',
    'agent_unknown' => 'No job has finished yet, so the agent cannot be confirmed working. Status appears after the first job, for example when you create a site.',
    'agent_stalled' => ':count job(s) waiting more than :minutes minutes without being picked up by the agent.',
    'agent_stalled_hint' => 'Check the agent service on the server:',
    'agent_running' => ':count job(s) running.',
    'resources' => 'Resources',
    'metrics_measured' => 'Measured :time.',
    'metrics_stale' => 'This data is old; metric collection may have stopped.',
    'metrics_none' => 'No metrics from the server yet.',
    'open_monitoring' => 'Open Monitoring',
    'cpu' => 'CPU',
    'ram' => 'RAM',
    'disk' => 'Disk',

    // Needs attention
    'attention_heading' => 'Needs attention',
    'attention_empty' => 'Nothing needs attention.',
    'attention_empty_hint' => 'Jobs that failed in the last 24 hours and SSL certificates that failed or expire within :days days will show up here.',
    'failed_job' => 'Failed job',
    'cert_expiring' => 'SSL expires :time',
    'cert_failed' => 'SSL issuance failed',
    'cert_expired' => 'SSL expired',
    'view_jobs' => 'View job queue',
    'view_ssl' => 'Open SSL',

    // Summary
    'summary_heading' => 'Summary',
    'count_sites' => 'Sites',
    'count_databases' => 'Databases',
    'count_users' => 'Managed users',
    'count_jobs_pending' => 'Jobs waiting',

    // Package
    'package_heading' => 'Package limits',
    'no_package' => 'This account has no package yet.',
    'quota_disk' => 'Disk',
    'quota_domains' => 'Domains',
    'quota_subdomains' => 'Subdomains',
    'quota_databases' => 'Databases',
    'quota_cronjobs' => 'Cron jobs',
    'quota_bandwidth' => 'Bandwidth / month',

    // Activity
    'recent_activity' => 'Recent activity',
    'no_activity' => 'No activity recorded yet. Changes made in the panel will show up here.',
    'view_audit' => 'Open audit log',
];

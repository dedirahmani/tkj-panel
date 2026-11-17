<?php

return [
    'title' => 'Sites',
    'subtitle' => 'Domains, subdomains and their web/PHP configuration. Changes are applied by the agent.',
    'create' => 'New site',
    'create_title' => 'Create site',
    'edit_title' => 'Site settings',

    'domain' => 'Domain',
    'domain_hint' => 'e.g. situs.sch.id (without http://). Document root is generated automatically.',
    'kind' => 'Type',
    'kind_domain' => 'domain',
    'kind_subdomain' => 'subdomain',
    'owner' => 'Owner',
    'owner_no_linux' => 'The selected owner has no Linux user yet.',

    'php_version' => 'PHP version',
    'web_backend' => 'Web server',
    'aliases' => 'Domain aliases',
    'aliases_hint' => 'Optional. Separate multiple domains with a comma or space.',
    'force_https' => 'Force HTTPS',
    'document_root' => 'Document root',

    'redirect' => 'Redirect',
    'redirect_type' => 'Redirect type',
    'redirect_none' => 'No redirect',
    'redirect_target' => 'Redirect target',
    'redirect_target_hint' => 'Destination domain/URL for the redirect.',

    'status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'maintenance' => 'Maintenance',
    ],

    'general' => 'General',
    'settings' => 'Settings',
    'activate' => 'Activate',
    'deactivate' => 'Deactivate',

    'empty' => 'No sites yet. Create your first one.',
    'created' => 'Site :domain created. The agent will provision the vhost.',
    'deleted' => 'Site deleted.',
    'status_changed' => 'Site status changed to :status.',
    'quota_exceeded' => 'Quota reached: maximum :max :kind for this package.',
    'confirm_delete' => 'Delete this site and its vhost? This cannot be undone.',

    // Validation
    'domain_reserved' => 'This domain is reserved and cannot be used.',
    'alias_equals_domain' => 'An alias cannot be the same as the primary domain.',
    'alias_taken' => 'The alias :alias is already used by another site.',

    // Show page
    'view' => 'View',
    'overview' => 'Overview',
    'set_status' => 'Set status',
    'aliases_none' => 'No aliases',
    'redirect_summary' => ':type to :target',
    'danger_zone' => 'Danger zone',
    'delete_site' => 'Delete site',
    'delete_site_hint' => 'Removes the site and queues vhost deletion on the server.',
    'edit_settings' => 'Edit settings',

    // php.ini editor
    'php_ini' => 'php.ini (per site)',
    'php_ini_intro' => 'Override PHP directives for this site\'s FPM pool. Leave blank to use the pool default.',
    'php_ini_saved' => 'php.ini updated. The agent will apply it and reload PHP-FPM.',
    'php_ini_default' => 'default: :value',
    'ini' => [
        'memory_limit' => 'Memory limit',
        'upload_max_filesize' => 'Max upload size',
        'post_max_size' => 'Max POST size',
        'max_execution_time' => 'Max execution time (s)',
        'max_input_time' => 'Max input time (s)',
        'max_input_vars' => 'Max input vars',
        'display_errors' => 'Display errors',
    ],
    'recent_jobs' => 'Recent agent activity',
    'recent_jobs_hint' => 'Site changes are applied on the server by the agent. If one fails (e.g. the PHP version is not installed), the message shows here.',
];

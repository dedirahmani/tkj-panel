<?php

return [
    'title' => 'SSL Certificates',
    'subtitle' => 'Let\'s Encrypt via acme.sh (HTTP-01 / DNS-01) or upload your own certificate.',
    'request' => 'Request certificate',
    'request_title' => 'Request Let\'s Encrypt certificate',
    'upload' => 'Upload certificate',
    'upload_title' => 'Upload custom certificate',

    'site' => 'Site',
    'method' => 'Validation method',
    'method_http' => 'HTTP-01 (default)',
    'method_dns' => 'DNS-01 (Cloudflare)',
    'wildcard' => 'Wildcard certificate (*.domain)',
    'wildcard_hint' => 'Covers all subdomains. Requires DNS-01 via Cloudflare.',
    'dns_note' => 'DNS-01 needs a Cloudflare API token (configured in Settings — coming in M8).',

    'primary_domain' => 'Primary domain',
    'domains' => 'Domains',
    'type' => 'Type',
    'type_single' => 'Single',
    'type_wildcard' => 'Wildcard',
    'type_custom' => 'Custom',
    'expires' => 'Expires',
    'never' => '—',
    'auto_renew' => 'Auto-renew',
    'renew' => 'Renew now',

    'certificate' => 'Certificate (PEM)',
    'certificate_hint' => 'Paste the full certificate chain in PEM format.',
    'private_key' => 'Private key (PEM)',
    'private_key_hint' => 'The private key is forwarded to the agent and never stored in the panel.',

    'status' => [
        'pending' => 'Pending',
        'issued' => 'Issued',
        'failed' => 'Failed',
        'expired' => 'Expired',
    ],

    'empty' => 'No certificates yet.',
    'requested' => 'Certificate requested for :domain. The agent will run acme.sh.',
    'uploaded' => 'Custom certificate uploaded.',
    'renew_requested' => 'Renewal requested.',
    'deleted' => 'Certificate record removed.',
    'confirm_delete' => 'Remove this certificate record?',
];

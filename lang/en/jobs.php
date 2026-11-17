<?php

return [
    'title' => 'Job Queue',
    'subtitle' => 'Whitelisted operations dispatched from the panel to the root agent (tkjpanel-agentd).',
    'operation' => 'Operation',
    'requester' => 'Requested by',
    'target' => 'Target',
    'created' => 'Created',
    'empty' => 'No jobs have been queued yet. Jobs appear here when the panel asks the agent to do something, such as creating a site.',
    'explainer' => 'The panel never runs as root. It only writes structured jobs here; the Go agent polls, validates against the operation whitelist, and executes.',
    'status' => [
        'pending' => 'Pending',
        'running' => 'Running',
        'success' => 'Success',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ],
];

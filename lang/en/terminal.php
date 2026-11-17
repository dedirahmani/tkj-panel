<?php

return [
    'title' => 'Web Terminal',
    'subtitle' => 'An in-browser shell for the selected user, bridged to a PTY by the agent.',
    'owner' => 'User',
    'connecting' => 'Connecting...',
    'disconnected' => 'Disconnected',
    'agent_required_title' => 'Agent connection required',
    'agent_required_body' => 'The web terminal streams a real shell from tkjpanel-agentd on the Linux host. It is not available in local/Windows development.',
    'note' => 'Sessions run as the user\'s Linux account and are recorded in the audit log.',
    'connected' => 'Connected',
    'reconnect' => 'Reconnect',
    'session_ended' => '[session ended]',
    'token_failed' => 'Could not get a session token from the panel.',
];

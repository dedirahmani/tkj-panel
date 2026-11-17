<?php

return [
    'title' => 'Cron Jobs',
    'subtitle' => 'Scheduled tasks run under each user\'s Linux account. The agent writes the crontab.',
    'create' => 'New cron job',
    'create_title' => 'Create cron job',

    'expression' => 'Schedule (cron expression)',
    'expression_hint' => 'Five fields: minute hour day month weekday. Pick a preset or enter your own.',
    'command' => 'Command',
    'command_hint' => 'Runs as the owner\'s Linux user, e.g. php /home/user/site/artisan schedule:run',
    'comment' => 'Comment',
    'owner' => 'Owner',
    'owner_no_linux' => 'The selected owner has no Linux user yet.',
    'schedule' => 'Schedule',
    'preset' => 'Preset',
    'custom' => 'Custom',
    'status' => 'Status',
    'activate' => 'Activate',
    'deactivate' => 'Deactivate',
    'next_run' => 'Next run',

    'empty' => 'No cron jobs yet.',
    'created' => 'Cron job created. The crontab has been queued to the agent.',
    'deleted' => 'Cron job deleted.',
    'quota_exceeded' => 'Quota reached: maximum :max cron jobs for this package.',
    'invalid_expression' => 'That is not a valid cron expression.',
    'confirm_delete' => 'Delete this cron job?',

    'presets' => [
        'every_minute' => 'Every minute',
        'every_5_minutes' => 'Every 5 minutes',
        'hourly' => 'Hourly',
        'daily' => 'Daily (midnight)',
        'weekly' => 'Weekly (Sun)',
        'monthly' => 'Monthly (1st)',
    ],
];

<?php

return [
    'title' => 'SFTP Accounts',
    'subtitle' => 'System users chrooted to a directory inside the owner\'s home (OpenSSH, no extra FTP daemon).',
    'create' => 'New SFTP account',
    'create_title' => 'Create SFTP account',

    'name' => 'Account name',
    'name_hint' => 'Lowercase letters, numbers, dash/underscore. Prefixed with the owner\'s username.',
    'subdir' => 'Chroot subdirectory',
    'subdir_hint' => 'Path inside the owner\'s home the account is locked to. Defaults to the account name.',
    'owner' => 'Owner',
    'owner_no_linux' => 'The selected owner has no Linux user yet.',
    'password' => 'Password',
    'password_hint' => 'Leave blank to auto-generate a strong password.',
    'username' => 'Username',
    'home' => 'Chroot home',
    'reset_password' => 'Reset password',

    'empty' => 'No SFTP accounts yet. An SFTP account gives file access locked to one folder in the owner\'s home.',
    'created' => 'SFTP account :name created.',
    'deleted' => 'SFTP account deleted.',
    'password_reset' => 'Password reset. Copy the new password now.',
    'quota_exceeded' => 'Quota reached: maximum :max SFTP accounts for this package.',
    'name_taken' => 'An SFTP account with this name already exists.',
    'name_too_long' => 'SFTP account name is too long (max 32 characters including the user prefix).',
    'confirm_delete' => 'Delete this SFTP account?',
    'confirm_reset' => 'Generate a new password for this SFTP account?',

    'credentials_title' => 'SFTP credentials',
    'credentials_warning' => 'Copy these now. The password will not be shown again.',
    'copy' => 'Copy',
    'copied' => 'Copied',
];

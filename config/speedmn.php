<?php

return [
    'discord_url' => env('DISCORD_INVITE_URL', '#community'),
    'audit_retention_days' => (int) env('ADMIN_AUDIT_RETENTION_DAYS', 365),
    'backup_retention_days' => (int) env('DB_BACKUP_RETENTION_DAYS', 14),
];

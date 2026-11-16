<?php

return [
    'backup_path' => env('BACKUP_PATH', storage_path('app/backups')),
    'backup_keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),
];

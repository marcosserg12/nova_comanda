<?php

return [
    'backup_path' => env('BACKUP_PATH', storage_path('app/backups')),
    'backup_keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),

    /** Minutes after which a kitchen card shows its waiting time in orange. */
    'kitchen_warn_minutes' => 15,

    /** Minutes after which a kitchen card turns pink with a red time badge. */
    'kitchen_late_minutes' => 25,
];

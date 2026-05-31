<?php


return [
    /*
    |--------------------------------------------------------------------------
    | Notification Model
    |--------------------------------------------------------------------------
    |
    | This is the model that will be used to store notifications. It must
    | use the HasResolvableNotifications trait and have a resolved_at column in the database.
    |
    */

    'notification_model' => Splitstack\Nudge\Models\Notification::class,
    // 'notification_model' => Splitstack\Nudge\Models\TenantAwareNotification::class, // Plug-and-play support for spatie/laravel-multitenancy


    /*
    |--------------------------------------------------------------------------
    | Queued Listeners
    |--------------------------------------------------------------------------
    | By default, the listeners that resolve notifications on action execution
    | are not queued. If you want to queue them, set this to true.
    |
    */
    'queued_listeners' => false,
];
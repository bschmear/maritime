<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Immediate owner "new boat show lead" email
    |--------------------------------------------------------------------------
    |
    | Sends boat-show-lead-submitted to the event's Notify users (recipients.user_ids),
    | or the central account owner when that list is empty. Visitor templated message:
    | SendBoatShowEventFollowUpJob (boat_show_event_followup + event delay).
    |
    | Set BOAT_SHOW_IMMEDIATE_OWNER_LEAD_NOTIFICATION=false to turn off the
    | instant owner alert.
    |
    */

    'send_immediate_owner_lead_notification' => (bool) env('BOAT_SHOW_IMMEDIATE_OWNER_LEAD_NOTIFICATION', true),

];

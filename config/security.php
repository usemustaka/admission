<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Two-factor authentication for staff
    |--------------------------------------------------------------------------
    |
    | When on, staff who may open the admin area must confirm two-factor
    | authentication first. A value saved on the settings page overrides this.
    |
    */

    'require_two_factor_staff' => (bool) env('REQUIRE_TWO_FACTOR_STAFF', false),

    /*
    |--------------------------------------------------------------------------
    | Email verification
    |--------------------------------------------------------------------------
    |
    | When on, an account must verify its email before using the app. A value
    | saved on the settings page overrides this.
    |
    */

    'require_email_verification' => (bool) env('REQUIRE_EMAIL_VERIFICATION', true),

];

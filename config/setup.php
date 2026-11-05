<?php

return [

    /*
    |--------------------------------------------------------------------------
    | First-run setup
    |--------------------------------------------------------------------------
    |
    | Until the setup page has been completed (or a super admin exists), every
    | page redirects to it. Turn `enforce` off to disable the redirect, which
    | the test suite does. When `token` is set, whoever runs the setup has to
    | type it in, so a stranger who finds a fresh install first cannot take it.
    |
    */

    'enforce' => env('SETUP_ENFORCE', true),

    'token' => env('SETUP_TOKEN'),

];

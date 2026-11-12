<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Progressive Web App
    |--------------------------------------------------------------------------
    |
    | One installable application serves the public website and all three
    | signed-in portals. Bump PWA_VERSION for every production release so an
    | installed client can detect the new release and offer an update.
    |
    */

    'version' => env('PWA_VERSION', '1.3.3'),

    // What the installed app is called: the install dialog, the launcher label
    // and the Apple/Android home-screen tile all read these two values.
    'app_name' => env('PWA_APP_NAME', 'DRMS'),

    'short_name' => env('PWA_SHORT_NAME', 'DRMS'),

    'description' => env(
        'PWA_DESCRIPTION',
        'Dream Roots Millennium School student, teacher and administration portal.'
    ),

    'theme_color' => env('PWA_THEME_COLOR', '#2563eb'),

    'background_color' => env('PWA_BACKGROUND_COLOR', '#f4f7fc'),

    /*
    | The installed app opens the sign-in screen. Laravel then redirects an
    | already signed-in user to the correct role workspace, so there is one
    | application identity but separate student, teacher and admin homes.
    */

    'start_route' => env('PWA_START_ROUTE', 'login'),

];

<?php

// One name for the installed application: config/pwa.php holds it, so renaming
// the app never leaves a stale label in the install instructions.
$app = (string) config('pwa.short_name', 'DRMS');

return [
    'install_app' => 'Install App',
    'install_title' => 'Install '.$app,
    'install_copy' => 'Add '.$app.' to your device for faster access.',
    'ios_hint' => 'Tap Share, then choose "Add to Home Screen".',
    'sheet_title' => 'Install on this device',
    'sheet_intro' => 'Follow the steps for your device.',
    'android_title' => 'Android (Chrome)',
    'android_steps' => [
        'Open the Chrome menu (⋮).',
        'Choose "Install app" or "Add to Home screen".',
        'Confirm, then open '.$app.' from your home screen.',
    ],
    'ios_title' => 'iPhone or iPad (Safari)',
    'ios_steps' => [
        'Tap the Share button in Safari.',
        'Scroll down and choose "Add to Home Screen".',
        'Tap "Add", then open '.$app.' from your home screen.',
    ],
    'desktop_title' => 'Desktop (Chrome, Edge or Safari)',
    'desktop_steps' => [
        'Open the install icon in the address bar, or the browser menu.',
        'Choose "Install '.$app.'"; in Safari choose File > Add to Dock.',
        'Confirm to finish installing.',
    ],
    'unsupported_title' => 'This browser cannot install the app',
    'unsupported_steps' => [
        'Open this page in Chrome, Edge or Safari.',
        'Use "Install app" or "Add to Home Screen" from the browser menu.',
        'If installation is unavailable, keep using the website normally.',
    ],
    'got_it' => 'Got it',
    'close_instructions' => 'Close instructions',
    'already_installed' => $app.' is already installed.',
];

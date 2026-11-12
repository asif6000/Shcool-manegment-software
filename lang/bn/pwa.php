<?php

// একই নাম সব জায়গায়: config/pwa.php থেকে আসে, তাই অ্যাপের নাম বদলালে ইনস্টল
// নির্দেশনায় পুরনো নাম থেকে যাবে না।
$app = (string) config('pwa.short_name', 'DRMS');

return [
    'install_app' => 'অ্যাপ ইনস্টল করুন',
    'install_title' => $app.' ইনস্টল করুন',
    'install_copy' => 'দ্রুত ব্যবহারের জন্য '.$app.' আপনার ডিভাইসে যোগ করুন।',
    'ios_hint' => 'Share বাটনে চাপ দিন, তারপর "Add to Home Screen" বেছে নিন।',
    'sheet_title' => 'এই ডিভাইসে ইনস্টল করুন',
    'sheet_intro' => 'আপনার ডিভাইস অনুযায়ী নিচের ধাপগুলো অনুসরণ করুন।',
    'android_title' => 'Android (Chrome)',
    'android_steps' => [
        'Chrome-এর মেনু (⋮) খুলুন।',
        '"Install app" অথবা "Add to Home screen" বেছে নিন।',
        'নিশ্চিত করুন, তারপর হোম স্ক্রিন থেকে '.$app.' খুলুন।',
    ],
    'ios_title' => 'iPhone বা iPad (Safari)',
    'ios_steps' => [
        'Safari-এর Share বাটনে চাপ দিন।',
        'নিচে স্ক্রল করে "Add to Home Screen" বেছে নিন।',
        '"Add" চাপুন, তারপর হোম স্ক্রিন থেকে অ্যাপটি খুলুন।',
    ],
    'desktop_title' => 'ডেস্কটপ (Chrome, Edge বা Safari)',
    'desktop_steps' => [
        'অ্যাড্রেস বারের ইনস্টল আইকন অথবা ব্রাউজার মেনু খুলুন।',
        '"Install '.$app.'" বেছে নিন; Safari-তে File > Add to Dock বেছে নিন।',
        'নিশ্চিত করে ইনস্টল সম্পন্ন করুন।',
    ],
    'unsupported_title' => 'এই ব্রাউজার থেকে অ্যাপ ইনস্টল করা যাবে না',
    'unsupported_steps' => [
        'Chrome, Edge অথবা Safari-তে এই পেজটি খুলুন।',
        'ব্রাউজার মেনু থেকে "Install app" অথবা "Add to Home Screen" বেছে নিন।',
        'ইনস্টল না হলে ওয়েবসাইটটি স্বাভাবিকভাবে ব্যবহার চালিয়ে যান।',
    ],
    'got_it' => 'বুঝেছি',
    'close_instructions' => 'নির্দেশনা বন্ধ করুন',
    'already_installed' => $app.' ইতিমধ্যে ইনস্টল করা আছে।',
];

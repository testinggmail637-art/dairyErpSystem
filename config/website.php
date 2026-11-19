<?php

/*
| Public website (Phase 7A). Defaults below are SAMPLE content until launch (DECISIONS #204);
| the owner's real values go into farms.settings.website (editable from admin in task 7A.6).
*/
return [
    // Farm the public site shows; empty = first active farm.
    'farm_id' => env('WEBSITE_FARM_ID'),

    'defaults' => [
        'site_name' => ['hi' => 'यशाध्य ऑर्गेनिक्स', 'en' => 'Yashadhya Organics'],
        'tagline' => ['hi' => 'शुद्धता परंपरा की', 'en' => 'Purity, the desi way'],
        'phone' => '+91 90000 00000',
        'whatsapp' => '+91 90000 00000',
        'email' => 'hello@example.com',
        'address' => [
            'hi' => 'यशाध्य डेयरी फार्म, गाँव —, ज़िला —, राज्य — 000000',
            'en' => 'Yashadhya Dairy Farm, Village —, District —, State — 000000',
        ],
        'timings' => ['hi' => 'रोज़ सुबह 6 से शाम 8 बजे', 'en' => 'Daily, 6 AM to 8 PM'],
        'delivery_areas' => ['hi' => 'शहर क्षेत्र 1 · क्षेत्र 2 · क्षेत्र 3', 'en' => 'City area 1 · Area 2 · Area 3'],
        'map_url' => 'https://maps.google.com',
        'fssai' => '00000000000000',
        'social' => [
            'instagram' => 'https://instagram.com',
            'facebook' => 'https://facebook.com',
            'youtube' => 'https://youtube.com',
        ],

        // Brand colours from the logo (CSS variables; nothing hard-coded in CSS).
        'colors' => [
            'light' => [
                'primary' => '#093F1E', 'leaf' => '#176C2B', 'accent' => '#FD8D00', 'on_accent' => '#1C2A20',
                'bg' => '#F8F4E8', 'surface' => '#FFFFFF', 'text' => '#1C2A20', 'muted' => '#5E6B61', 'border' => '#E4DDC8',
                'on_primary' => '#FFFFFF', 'overlay' => '#06210F', 'on_photo' => '#FFFFFF', 'danger' => '#C0392B', 'footer_bg' => '#093F1E', 'footer_text' => '#F8F4E8',
            ],
            'dark' => [
                'primary' => '#23A541', 'leaf' => '#23A541', 'accent' => '#FD8D00', 'on_accent' => '#1C2A20',
                'bg' => '#0E1A12', 'surface' => '#16261B', 'text' => '#F8F4E8', 'muted' => '#B9C2B4', 'border' => '#2A3D30',
                'on_primary' => '#0E1A12', 'overlay' => '#06210F', 'on_photo' => '#FFFFFF', 'danger' => '#E5675A', 'footer_bg' => '#0A140D', 'footer_text' => '#F8F4E8',
            ],
        ],
        'logo' => 'images/website/brand/logo-header',
        'logo_dark' => 'images/website/brand/logo-header-dark',
        'favicon' => 'yashadhya-logos/favicon.ico',
        'icon_192' => 'yashadhya-logos/yashadhya-icon-192.png',
    ],
];

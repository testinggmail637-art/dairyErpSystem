<?php

// Products page (7A.4a). Delivery has not started yet, so no prices: every card says "coming soon" (DECISIONS #211).
return [
    'meta_description' => 'Yashadhya Organics products: A2 desi cow milk, Murrah buffalo milk, bilona ghee, matka dahi and fresh paneer — from our own gaushala.',

    'intro' => [
        'eyebrow' => 'Our products',
        'title' => 'From our gaushala, the way nature made it.',
        'text' => 'Five things, all from the milk of our own cows and buffaloes. No water, no powder, no chemicals.',
        'notice' => 'Home delivery is starting soon.',
        'notice_cta' => 'Tell me when it starts',
    ],

    'filter' => [
        'label' => 'Filter products',
        'all' => 'All',
        'milk' => 'Milk',
        'ghee' => 'Ghee',
        'dahi-paneer' => 'Dahi & paneer',
    ],

    'soon' => 'Coming soon',
    'notify' => 'Tell me when it starts',
    'notify_text' => 'Hello, please tell me when ":name" is available.',

    'items' => [
        [
            'slug' => 'a2-cow-milk', 'cat' => 'milk', 'img' => 'milk-bottle',
            'name' => 'A2 desi cow milk', 'sizes' => ['500 ml', '1 L'],
            'text' => 'From our Gir, Sahiwal and Tharparkar cows. Milked in the morning, bottled in glass the same morning.',
            'points' => ['Fat / SNF test on every batch', 'Glass bottle', 'Cooled to 4°C right after milking'],
        ],
        [
            'slug' => 'buffalo-milk', 'cat' => 'milk', 'img' => 'milk-cup',
            'name' => 'Murrah buffalo milk', 'sizes' => ['1 L'],
            'text' => 'Thick, creamy milk — the best for dahi, paneer and chai.',
            'points' => ['Thick cream', 'Tested daily', 'Glass bottle'],
        ],
        [
            'slug' => 'bilona-ghee', 'cat' => 'ghee', 'img' => 'ghee-jar',
            'name' => 'Bilona ghee', 'sizes' => ['500 ml'],
            'text' => 'Grainy desi ghee, slow-cooked from butter churned out of curd.',
            'points' => ['Hand churned', 'From A2 cow milk', 'Glass jar'],
        ],
        [
            'slug' => 'dahi', 'cat' => 'dahi-paneer', 'img' => 'dahi',
            'name' => 'Matka dahi', 'sizes' => ['500 g'],
            'text' => 'Set in a clay pot from the same morning\'s milk. Thick and mildly sour.',
            'points' => ['Set in a clay pot', 'No thickening powder', 'Fresh every day'],
        ],
        [
            'slug' => 'paneer', 'cat' => 'dahi-paneer', 'img' => 'paneer',
            'name' => 'Fresh paneer', 'sizes' => ['250 g'],
            'text' => 'Made every morning from whole milk. Soft, fresh and free of chemicals.',
            'points' => ['Made daily', 'From whole milk', 'No chemicals'],
        ],
    ],

    'free' => [
        'eyebrow' => 'In every product',
        'title' => 'What we leave out matters just as much.',
        'items' => ['Water', 'Milk powder', 'Preservatives', 'Hormone injections'],
        'no' => 'No',
    ],

    'join' => [
        'eyebrow' => 'Be among the first',
        'title' => 'We will tell you first when delivery starts.',
        'text' => 'Send your name on WhatsApp or give us a call. You are welcome to visit the gaushala too.',
        'whatsapp_text' => 'Hello, please tell me when Yashadhya delivery starts.',
    ],
];

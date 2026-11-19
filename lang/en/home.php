<?php

// Home page text — English. SAMPLE content until launch (DECISIONS #204); prices, numbers and reviews are placeholders.
return [
    'hero' => [
        'eyebrow' => 'Desi cow & buffalo milk · from our own herd',
        'title' => 'Pure desi cow milk, at your door every morning',
        'text' => 'Gir, Sahiwal and Tharparkar cows and Murrah buffaloes — grazing in the open, fed on green fodder. Milked at dawn, tested and chilled, never watered down or mixed.',
        'cta' => 'Order milk',
        'cta_secondary' => 'Meet our herd',
        'trust' => ['Fresh every day', 'Every batch tested', 'Delivered before 7:30 AM'],
        'image_alt' => 'Desi cows grazing in a green field',
    ],

    'free_from' => [
        'title' => 'Our milk has no',
        'items' => ['Added water', 'Milk powder', 'Preservatives', 'Hormone injections'],
    ],

    'offer' => [
        'eyebrow' => 'What we offer',
        'title' => 'Pure milk for your family and your business',
        'items' => [
            ['icon' => 'droplet-fill', 'title' => 'A2 desi cow milk', 'text' => 'Light, easy-to-digest A2 milk from Gir, Sahiwal and Tharparkar cows — for children and elders alike.', 'page' => 'products'],
            ['icon' => 'basket2-fill', 'title' => 'Murrah buffalo milk', 'text' => 'Rich, creamy, high-fat milk — the best for curd, paneer, ghee and chai.', 'page' => 'products'],
            ['icon' => 'truck', 'title' => 'Daily home delivery', 'text' => 'Daily or alternate days, at your door before 7:30 AM. Pause for holidays, one bill a month.', 'page' => 'home-delivery'],
            ['icon' => 'shop', 'title' => 'Bulk supply', 'text' => 'Fixed daily quantity at a fixed rate for hotels, sweet shops, tea stalls and events.', 'page' => 'bulk-orders'],
        ],
        'more' => 'Learn more',
    ],

    'breeds' => [
        'eyebrow' => 'Our desi breeds',
        'title' => 'Only native Indian breeds, no foreign crosses',
        'text' => 'Our herd has only desi cows and buffaloes. They stay healthy in the Indian heat, and their milk is part of our tradition.',
        'items' => [
            ['image' => 'breed-gir', 'name' => 'Gir cow', 'origin' => 'Gujarat', 'text' => 'A gentle cow with curved horns and long ears, known across India for its A2 milk.'],
            ['image' => 'breed-sahiwal', 'name' => 'Sahiwal cow', 'origin' => 'Punjab', 'text' => 'Reddish-brown and one of the highest-yielding desi breeds.'],
            ['image' => 'breed-tharparkar', 'name' => 'Tharparkar cow', 'origin' => 'Rajasthan', 'text' => 'A strong white cow that gives good milk even in the heat and dryness of the Thar.'],
            ['image' => 'breed-murrah', 'name' => 'Murrah buffalo', 'origin' => 'Haryana', 'text' => 'Jet-black with tightly curled horns — famous for thick, high-fat milk.'],
        ],
        'cta' => 'Read about our herd',
    ],

    'products' => [
        'eyebrow' => 'Our products',
        'ask_price' => 'Ask for price',
        'enquire' => 'Send enquiry',
        'details' => 'See all products',
        'prev' => 'Previous product',
        'next' => 'Next product',
        'items' => [
            ['image' => 'product-cow-milk', 'icon' => 'droplet-fill', 'tab' => 'Cow milk', 'sub' => 'देसी गाय का दूध', 'name' => 'A2 desi cow milk', 'word' => 'PURE', 'text' => 'Light, sweet milk from Gir, Sahiwal and Tharparkar cows. Milked at dawn, at your door the same morning — no water, no powder.', 'sizes' => ['500 ml', '1 L', '2 L'], 'price' => '₹90 / litre'],
            ['image' => 'product-buffalo-milk', 'icon' => 'basket2-fill', 'tab' => 'Buffalo milk', 'sub' => 'मुर्रा भैंस का दूध', 'name' => 'Murrah buffalo milk', 'word' => 'RICH', 'text' => 'Thick, high-fat milk from Murrah buffaloes. Heavy malai, great curd and the best chai.', 'sizes' => ['500 ml', '1 L', '5 L', 'Bulk'], 'price' => '₹70 / litre'],
            ['image' => 'product-ghee', 'icon' => 'award-fill', 'tab' => 'Bilona ghee', 'sub' => 'बिलोना घी', 'name' => 'A2 bilona ghee', 'word' => 'GOLDEN', 'text' => 'Butter churned from curd with a wooden mathani, slow-cooked into grainy, golden ghee.', 'sizes' => ['500 ml', '1 L'], 'price' => null],
            ['image' => 'product-dahi', 'icon' => 'heart-fill', 'tab' => 'Curd', 'sub' => 'मटकी वाला दही', 'name' => 'Clay-pot curd', 'word' => 'FRESH', 'text' => 'Thick curd set overnight in a clay pot — mild, never too sour, just like home.', 'sizes' => ['500 g', '1 kg'], 'price' => '₹50 / 500 g'],
            ['image' => 'product-paneer', 'icon' => 'star-fill', 'tab' => 'Paneer', 'sub' => 'मलाई पनीर', 'name' => 'Malai paneer', 'word' => 'CREAMY', 'text' => 'Soft, creamy paneer made from the same day\'s milk — for sabzi, tikka and bhurji.', 'sizes' => ['250 g', '500 g', '1 kg'], 'price' => '₹220 / 500 g'],
        ],
    ],

    'compare' => [
        'badge' => 'See the difference',
        'title' => 'Market milk vs Yashadhya milk',
        'text' => 'Drag the line in the middle and see the difference yourself.',
        'slider' => 'Move the comparison line',
        'left' => 'Market milk',
        'right' => 'Yashadhya A2',
        'left_points' => ['Risk of added water', 'Days old, in a plastic packet', 'Fear of chemicals (urea, detergent)'],
        'right_points' => ['Glass bottle', 'Fresh from the same morning', 'Every batch tested'],
    ],

    'bilona' => [
        'badge' => 'The bilona way',
        'title' => 'From cow to ghee, the traditional way',
        'steps' => [
            ['image' => 'hero-herd', 'title' => 'Caring for the herd', 'text' => 'Green fodder, clean water and open fields. No hormone injections.'],
            ['image' => 'milking', 'title' => 'Milking', 'text' => 'Milked by clean hands at dawn; every batch tested.'],
            ['image' => 'product-dahi', 'title' => 'Setting curd', 'text' => 'Boiled milk is set overnight in clay pots.'],
            ['image' => 'bilona-churning', 'title' => 'Bilona churning', 'text' => 'Curd is churned with a wooden mathani to separate the butter.'],
            ['image' => 'product-ghee', 'title' => 'Slow fire', 'text' => 'Butter is slow-cooked for hours into grainy, golden ghee.'],
        ],
        'cta' => 'The full story of our farm',
    ],

    'trust' => [
        'badge' => 'Why trust us',
        'title' => 'Purity you can actually see',
        'zero_label' => 'Adulteration',
        'zero_text' => 'No water, no chemicals, no preservatives. What the cow gives is what reaches you.',
        'stats' => [['value' => 25, 'label' => 'Desi cows'], ['value' => 15, 'label' => 'Murrah buffaloes']],
        'tiles' => [
            'lab' => ['icon' => 'shield-fill-check', 'title' => 'Lab tested', 'text' => 'Every batch checked'],
            'glass' => ['icon' => 'droplet-fill', 'title' => 'Glass packing', 'text' => 'Away from plastic'],
            'fresh' => ['icon' => 'truck', 'title' => 'Fresh delivery', 'text' => 'Same-morning milk, straight to your home'],
            'pure' => ['icon' => 'tree-fill', 'title' => '100% pure', 'text' => 'Only milk from our own farm, nothing bought in'],
        ],
    ],

    'order' => [
        'badge' => 'How to order',
        'title' => 'Just 3 easy steps',
        'steps' => [
            ['icon' => 'whatsapp', 'title' => 'Send an enquiry', 'text' => 'Fill the form or WhatsApp us: product, quantity and your area.'],
            ['icon' => 'telephone-fill', 'title' => 'We call you', 'text' => 'We agree the price, size and delivery days with you on the phone.'],
            ['icon' => 'truck', 'title' => 'Home delivery', 'text' => 'Milk every morning before 7:30 AM, ghee in glass jars — straight to your home.'],
        ],
        'points' => [
            ['icon' => 'calendar2-check', 'text' => 'Daily or alternate days'],
            ['icon' => 'pause-circle-fill', 'text' => 'Pause for holidays'],
            ['icon' => 'receipt', 'text' => 'One bill a month — UPI or cash'],
        ],
        'areas' => 'Delivery:',
    ],

    'why' => [
        'eyebrow' => 'Why Yashadhya',
        'title' => 'A family farm, not a big factory',
        'values' => [
            ['icon' => 'award-fill', 'title' => 'Desi breeds only', 'text' => 'Gir, Sahiwal, Tharparkar and Murrah — no foreign crosses.'],
            ['icon' => 'house-heart-fill', 'title' => 'Our own herd', 'text' => 'We do not buy and resell milk; it comes from our own cows, in our own care.'],
            ['icon' => 'shield-fill-check', 'title' => 'Tested daily', 'text' => 'Every batch is tested; we show the reports when you ask.'],
            ['icon' => 'people-fill', 'title' => 'Fair to farmers', 'text' => 'We buy milk from village farmers at a fair price based on fat/SNF.'],
        ],
        'quote' => 'Our grandmother used to say — care for the cow and she will care for the whole family. Yashadhya began with that thought: pure milk at an honest price.',
        'quote_by' => 'Founder, Yashadhya Organics',
        'image_alt' => 'A farmer in Rajasthan milking a cow with her calf beside her',
    ],

    'ecosystem' => [
        'eyebrow' => 'What we believe',
        'title' => 'Cows, farmers, the land and families — together',
        'tabs' => [
            ['key' => 'cows', 'icon' => 'heart-fill', 'label' => 'Cows', 'title' => 'Cows come first', 'text' => 'Open sheds, shade, clean water and a vet on time. Milk from a sick cow is never sold. Old cows stay with us for life.'],
            ['key' => 'farmers', 'icon' => 'people-fill', 'label' => 'Farmers', 'title' => 'With village farmers', 'text' => 'At our collection center, fat and SNF are tested in front of the farmer, with a printed slip and payment on a fixed day.'],
            ['key' => 'earth', 'icon' => 'tree-fill', 'label' => 'The land', 'title' => 'Giving back to the land', 'text' => 'Dung becomes compost and biogas; chemical fertiliser on our fodder fields is kept to a minimum.'],
            ['key' => 'family', 'icon' => 'house-heart-fill', 'label' => 'Families', 'title' => 'Your family, our responsibility', 'text' => 'The same milk our children drink comes to your home. You are welcome to visit the farm any time.'],
        ],
    ],

    'reviews' => [
        'eyebrow' => 'What customers say',
        'title' => 'From the families we serve',
        'items' => [
            ['text' => 'The children no longer get tummy aches from milk. It arrives on time and tastes just like the village.', 'name' => 'Sunita Sharma', 'place' => 'City area 1'],
            ['text' => 'Buffalo milk gives us thick malai and ghee at home. The monthly bill is always clear.', 'name' => 'Rajesh Verma', 'place' => 'Area 2'],
            ['text' => 'Our sweet shop gets 40 litres every day. Not a single late morning in six months.', 'name' => 'Gupta Sweets', 'place' => 'Area 3'],
        ],
    ],

    'faq' => [
        'eyebrow' => 'Questions',
        'title' => 'Frequently asked questions',
        'items' => [
            ['q' => 'What is A2 milk?', 'a' => 'Milk from native Indian cows contains the A2 beta-casein protein. Many people find it lighter and easier to digest than milk from foreign breeds (A1).'],
            ['q' => 'What time is milk delivered?', 'a' => 'Between 5:30 and 7:30 AM. We confirm the exact time for your area when you enquire.'],
            ['q' => 'Can I pause delivery for a few days?', 'a' => 'Yes. Call or WhatsApp us a day before; those days are not added to your bill.'],
            ['q' => 'How do I pay?', 'a' => 'You get one bill at the end of the month. Pay by UPI, bank transfer or cash — whatever is easiest.'],
            ['q' => 'Can I visit the farm?', 'a' => 'Of course. Sundays 8 to 11 AM — please call before you come.'],
        ],
        'all' => 'See all questions',
    ],

    'enquiry' => [
        'title' => 'Start pure milk from tomorrow morning',
        'text' => 'Leave your name and phone number and we will call you today. Or call / WhatsApp us directly.',
        'name' => 'Your name',
        'phone' => 'Mobile number',
        'interest' => 'What do you need?',
        'interests' => ['home_delivery' => 'Daily milk at home', 'bulk' => 'Bulk / for a shop', 'product' => 'Ghee, curd, paneer', 'other' => 'Something else'],
        'submit' => 'Request a call back',
        'note' => 'We use your number only for this enquiry.',
        'demo' => 'This form will be switched on soon. Until then, please call or WhatsApp us.',
    ],
];

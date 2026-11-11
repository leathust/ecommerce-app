<?php

return [
    'locations' => [
        'header' => [
            'label' => 'Menu đầu trang',
            'description' => 'Điều hướng chính dùng chung cho desktop và mobile.',
        ],
        'footer' => [
            'label' => 'Menu chân trang',
            'description' => 'Các nhóm liên kết ở chân trang; mục gốc là tiêu đề cột.',
        ],
    ],

    'link_types' => [
        'route' => 'Route nội bộ',
        'url' => 'URL tùy chỉnh',
        'none' => 'Chỉ làm nhóm chứa',
    ],

    'targets' => [
        '_self' => 'Mở trong tab hiện tại',
        '_blank' => 'Mở trong tab mới',
    ],

    'routes' => [
        'home' => ['label_vi' => 'Trang chủ', 'label_en' => 'Home'],
        'shop' => ['label_vi' => 'Cửa hàng', 'label_en' => 'Shop'],
        'products' => ['label_vi' => 'Sản phẩm', 'label_en' => 'Products'],
        'cart' => ['label_vi' => 'Giỏ hàng', 'label_en' => 'Cart'],
        'contact' => ['label_vi' => 'Liên hệ', 'label_en' => 'Contact'],
        'track-order' => ['label_vi' => 'Theo dõi đơn hàng', 'label_en' => 'Track order'],
        'help' => ['label_vi' => 'Trợ giúp', 'label_en' => 'Help'],
        'account' => ['label_vi' => 'Tài khoản', 'label_en' => 'Account'],
    ],

    'locales' => ['vi', 'en'],
    'fallback_locale' => 'vi',
    'max_depth' => 4,
    'sort_step' => 10,

    'allowed_url_schemes' => ['http', 'https'],
    'blocked_internal_prefixes' => ['/admin'],

    'cache' => [
        'prefix' => 'menus.published.v2',
    ],

    // Emergency renderer fallback is off after the default menu backfill.
    // It may be enabled temporarily while recovering an empty menu table.
    'fallback_enabled' => (bool) env('MENU_FALLBACK_ENABLED', false),

    // Canonical defaults are shared by the idempotent seeder and emergency fallback.
    'defaults' => [
        'header' => [
            ['label_vi' => 'Trang chủ', 'label_en' => 'Home', 'route_name' => 'home'],
            ['label_vi' => 'Cửa hàng', 'label_en' => 'Shop', 'route_name' => 'shop'],
            ['label_vi' => 'Sản phẩm', 'label_en' => 'Products', 'route_name' => 'products'],
            ['label_vi' => 'Giỏ hàng', 'label_en' => 'Cart', 'route_name' => 'cart'],
            ['label_vi' => 'Liên hệ', 'label_en' => 'Contact', 'route_name' => 'contact'],
        ],
        'footer' => [
            [
                'label_vi' => 'Danh mục phổ biến', 'label_en' => 'Popular Categories',
                'children' => [
                    ['label_vi' => 'Máy tính & Laptop', 'label_en' => 'Laptops & Desktops', 'route_name' => 'shop'],
                    ['label_vi' => 'Nhà thông minh', 'label_en' => 'Smart Home Electronics', 'route_name' => 'shop'],
                    ['label_vi' => 'Tai nghe', 'label_en' => 'Headphones', 'route_name' => 'shop'],
                ],
            ],
            [
                'label_vi' => 'Hỗ trợ bạn', 'label_en' => 'Let us help you',
                'children' => [
                    ['label_vi' => 'Tài khoản', 'label_en' => 'My account', 'route_name' => 'account'],
                    ['label_vi' => 'Theo dõi đơn hàng', 'label_en' => 'Track order', 'route_name' => 'track-order'],
                    ['label_vi' => 'Trung tâm trợ giúp', 'label_en' => 'Help centre', 'route_name' => 'help'],
                ],
            ],
            [
                'label_vi' => 'Tài nguyên', 'label_en' => 'Resources',
                'children' => [
                    ['label_vi' => 'Sản phẩm', 'label_en' => 'Products', 'route_name' => 'products'],
                    ['label_vi' => 'Cửa hàng', 'label_en' => 'Shop', 'route_name' => 'shop'],
                    ['label_vi' => 'Trợ giúp', 'label_en' => 'Support', 'route_name' => 'help'],
                ],
            ],
            [
                'label_vi' => 'Về chúng tôi', 'label_en' => 'Get to know us',
                'children' => [
                    ['label_vi' => 'Liên hệ', 'label_en' => 'Contact', 'route_name' => 'contact'],
                    ['label_vi' => 'Đăng nhập / Đăng ký', 'label_en' => 'Login / Register', 'route_name' => 'account'],
                    ['label_vi' => 'Câu hỏi thường gặp', 'label_en' => 'FAQs', 'route_name' => 'help'],
                ],
            ],
        ],
    ],
];

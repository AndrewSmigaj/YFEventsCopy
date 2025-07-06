<?php
/**
 * Module Configuration
 * 
 * Define all available modules and their settings
 */

return [
    'modules' => [
        'events' => [
            'name' => 'Events Calendar',
            'description' => 'Event management system with calendar, map integration, and email scraping',
            'enabled' => true,
            'version' => '2.0.0',
            'icon' => '📅',
            'routes' => [
                '/events' => 'EventController',
                '/api/events' => 'EventApiController'
            ],
            'admin_menu' => [
                'title' => 'Events',
                'items' => [
                    ['title' => 'All Events', 'url' => '/admin/events', 'icon' => '📅'],
                    ['title' => 'Add Event', 'url' => '/admin/events/create', 'icon' => '➕'],
                    ['title' => 'Event Sources', 'url' => '/admin/event-sources', 'icon' => '🔗'],
                    ['title' => 'Email Events', 'url' => '/admin/email-events.php', 'icon' => '📧']
                ]
            ]
        ],
        
        'shops' => [
            'name' => 'Local Business Directory',
            'description' => 'Shop and business directory with geocoding and amenities',
            'enabled' => true,
            'version' => '2.0.0',
            'icon' => '🏪',
            'routes' => [
                '/shops' => 'ShopController',
                '/api/shops' => 'ShopApiController'
            ],
            'admin_menu' => [
                'title' => 'Shops',
                'items' => [
                    ['title' => 'All Shops', 'url' => '/admin/shops', 'icon' => '🏪'],
                    ['title' => 'Add Shop', 'url' => '/admin/shops/create', 'icon' => '➕']
                ]
            ]
        ],
        
        'yfclaim' => [
            'name' => 'YFClaim Estate Sales',
            'description' => 'Estate sale claim platform for buyers and sellers',
            'enabled' => true,
            'version' => '0.6.0',
            'icon' => '🏷️',
            'routes' => [
                '/modules/yfclaim' => 'ClaimsController'
            ],
            'admin_menu' => [
                'title' => 'Estate Sales',
                'items' => [
                    ['title' => 'YFClaim Admin', 'url' => '/modules/yfclaim/www/admin/', 'icon' => '🏷️']
                ]
            ]
        ],
        
        'yfclassifieds' => [
            'name' => 'YF Classifieds',
            'description' => 'Local classified ads with photos, descriptions and in-store pickup',
            'enabled' => false,
            'version' => '1.0.0',
            'icon' => '🛍️',
            'routes' => [
                '/classifieds' => 'ClassifiedsController',
                '/api/classifieds' => 'ClassifiedsApiController'
            ],
            'admin_menu' => [
                'title' => 'Classifieds',
                'items' => [
                    ['title' => 'All Items', 'url' => '/admin/classifieds', 'icon' => '🛍️'],
                    ['title' => 'Add Item', 'url' => '/admin/classifieds/create', 'icon' => '➕'],
                    ['title' => 'Categories', 'url' => '/admin/classifieds/categories', 'icon' => '📂']
                ]
            ]
        ],
        
        'yfauth' => [
            'name' => 'YFAuth Authentication',
            'description' => 'User authentication and authorization system',
            'enabled' => true,
            'version' => '1.0.0',
            'icon' => '🔐',
            'routes' => [
                '/modules/yfauth' => 'AuthController'
            ],
            'admin_menu' => null // No direct admin menu, integrated into user management
        ],
        
        'yftheme' => [
            'name' => 'YFTheme System',
            'description' => 'Theme and customization management',
            'enabled' => true,
            'version' => '1.0.0',
            'icon' => '🎨',
            'routes' => [
                '/modules/yftheme' => 'ThemeController'
            ],
            'admin_menu' => [
                'title' => 'Theme',
                'items' => [
                    ['title' => 'Customize Theme', 'url' => '/admin/theme', 'icon' => '🎨']
                ]
            ]
        ]
    ]
];
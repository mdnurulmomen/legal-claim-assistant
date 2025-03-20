<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $menus = $this->getMenus();

        Menu::upsert(
            $menus,
            ['id'],
            ['title', 'route_name', 'type', 'menu_id', 'order', 'created_at', 'updated_at']
        );
    }

    public function getMenus(): array
    {
        $now = now();

        $menus = [
            [
                'id' => 1,
                'title' => 'Dashboard',
                'route_name' => 'dashboard',
                'type' => 'endpoint',
                'menu_id' => null,
                'order' => 1,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 2,
                'title' => 'List',
                'route_name' => 'platformList',
                'type' => 'endpoint',
                'menu_id' => null,
                'order' => 2,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 3,
                'title' => 'Reporting',
                'route_name' => 'reporting',
                'type' => 'endpoint',
                'menu_id' => null,
                'order' => 3,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 4,
                'title' => 'Saved Reports',
                'route_name' => 'singleReporting',
                'type' => 'endpoint',
                'menu_id' => null,
                'order' => 4,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 5,
                'title' => 'Lead',
                'route_name' => 'leads',
                'type' => 'endpoint',
                'menu_id' => null,
                'order' => 5,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 6,
                'title' => 'Ping Logs',
                'route_name' => 'platform-list-ping-log',
                'type' => 'endpoint',
                'menu_id' => null,
                'order' => 6,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 7,
                'title' => 'Affiliates',
                'route_name' => 'affiliates',
                'type' => 'endpoint',
                'menu_id' => null,
                'order' => 7,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 8,
                'title' => 'Invoices',
                'route_name' => 'invoices',
                'type' => 'endpoint',
                'menu_id' => null,
                'order' => 8,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 9,
                'title' => 'Global Postback',
                'route_name' => 'globalPostback',
                'type' => 'endpoint',
                'menu_id' => null,
                'order' => 9,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 10,
                'title' => 'List Details',
                'route_name' => 'platformDetails',
                'type' => 'endpoint',
                'menu_id' => null,
                'order' => 2.1,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 11,
                'title' => 'Caps Overview',
                'route_name' => 'caps-overview',
                'type' => 'endpoint',
                'menu_id' => null,
                'order' => 6.1,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 12,
                'title' => 'General Information',
                'route_name' => 'general-information',
                'type' => 'endpoint',
                'menu_id' => null,
                'order' => 10,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 13,
                'title' => 'Campaign News',
                'route_name' => 'newest-campaign',
                'type' => 'endpoint',
                'menu_id' => null,
                'order' => 11,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 14,
                'title' => 'Conferences',
                'route_name' => 'conference',
                'type' => 'endpoint',
                'menu_id' => null,
                'order' => 12,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 15,
                'title' => 'Affiliate Creatives',
                'route_name' => 'creative',
                'type' => 'endpoint',
                'menu_id' => null,
                'order' => 13,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 16,
                'title' => 'Creative Template',
                'route_name' => 'creative-template',
                'type' => 'endpoint',
                'menu_id' => null,
                'order' => 13.1,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 17,
                'title' => 'Creative Offer',
                'route_name' => 'template-offer',
                'type' => 'endpoint',
                'menu_id' => null,
                'order' => 13.2,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 18,
                'title' => 'Case Study',
                'route_name' => 'case-study',
                'type' => 'endpoint',
                'menu_id' => null,
                'order' => 14,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 19,
                'title' => 'Case Study Category',
                'route_name' => 'case-study-category',
                'type' => 'endpoint',
                'menu_id' => null,
                'order' => 14.1,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 20,
                'title' => 'Case Study Tags',
                'route_name' => 'case-study-tags',
                'type' => 'endpoint',
                'menu_id' => null,
                'order' => 14.2,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 21,
                'title' => 'Integration Email',
                'route_name' => 'integrationEmail',
                'type' => 'endpoint',
                'menu_id' => null,
                'order' => 9.1,
                'created_at' => $now,
                'updated_at' => $now
            ]
        ];

        return $menus;
    }
}

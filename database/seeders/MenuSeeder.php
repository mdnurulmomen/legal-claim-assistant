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
            ]
        ];

        return $menus;
    }
}

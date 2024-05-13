<?php

namespace Database\Seeders;

use App\Models\PlatformList;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Generator as Faker;

class PlatformListSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(Faker $faker): void
    {
        $data = [];
        $now = now();

        for($i = 0; $i < 250; $i++) {
            $data[] = [
               'tag' => $faker->word(),
               'name' => $faker->word(),
               'campaign_name' => $faker->word(),
               'source' => $faker->word(),
               'total' => $faker->numberBetween(1, 10),
               'headers' => json_encode($faker->words()),
               'is_test' => $faker->boolean(),
               'cv_trigger' => json_encode($faker->words()),
               'integrations' => json_encode($faker->words()),
               'options' => json_encode($faker->words()),
               'insights' => json_encode($faker->words()),
               'status' => $faker->boolean() ? 'Active' : 'Inactive',
               'created_at' => $now,
               'updated_at' => $now
            ];
        }

        // PlatformList::insert($data);
    }
}

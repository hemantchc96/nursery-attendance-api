<?php

namespace Database\Seeders;

use App\Models\Child;
use App\Models\Nursery;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([1 => 'Acorn Group', 2 => 'Willow Group'] as $id => $name) {
            Tenant::updateOrCreate(['id' => $id], ['name' => $name]);
            Nursery::updateOrCreate(['id' => $id], ['tenant_id' => $id, 'name' => $name.' Nursery']);
            Room::updateOrCreate(['id' => $id * 2 - 1], ['tenant_id' => $id, 'nursery_id' => $id, 'name' => 'Sunflowers']);
            Room::updateOrCreate(['id' => $id * 2], ['tenant_id' => $id, 'nursery_id' => $id, 'name' => 'Little Explorers']);
            Child::updateOrCreate(['id' => $id * 2 - 1], ['tenant_id' => $id, 'name' => $id === 1 ? 'Ava' : 'Mia']);
            Child::updateOrCreate(['id' => $id * 2], ['tenant_id' => $id, 'name' => $id === 1 ? 'Noah' : 'Leo']);
        }
    }
}

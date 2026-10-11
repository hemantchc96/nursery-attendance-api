<?php

namespace Database\Factories;

use App\Models\Child;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

class CheckInFactory extends Factory
{
    public function definition(): array
    {
        return ['room_id' => Room::factory(), 'tenant_id' => fn (array $attributes) => Room::findOrFail($attributes['room_id'])->tenant_id, 'child_id' => fn (array $attributes) => Child::factory()->create(['tenant_id' => $attributes['tenant_id']])->id, 'checked_in_at' => now(), 'checked_out_at' => null];
    }
}

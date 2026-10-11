<?php

namespace Database\Factories;

use App\Models\Nursery;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomFactory extends Factory
{
    public function definition(): array
    {
        return ['nursery_id' => Nursery::factory(), 'tenant_id' => fn (array $attributes) => Nursery::findOrFail($attributes['nursery_id'])->tenant_id, 'name' => fake()->word()];
    }
}

<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class NurseryFactory extends Factory
{
    public function definition(): array
    {
        return ['tenant_id' => Tenant::factory(), 'name' => fake()->company()];
    }
}

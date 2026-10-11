<?php

namespace Tests\Feature;

use App\Models\CheckIn;
use App\Models\Child;
use App\Models\Nursery;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceFixturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_factories_create_consistent_tenant_associations(): void
    {
        $visit = CheckIn::factory()->create();
        $room = Room::findOrFail($visit->room_id);
        $this->assertSame($visit->tenant_id, $room->tenant_id);
        $this->assertSame($visit->tenant_id, Child::findOrFail($visit->child_id)->tenant_id);
        $this->assertSame($visit->tenant_id, Nursery::findOrFail($room->nursery_id)->tenant_id);
    }

    public function test_demo_seeding_is_repeatable_and_has_two_isolated_groups(): void
    {
        $this->seed();
        $this->seed();
        $this->assertSame(2, Tenant::count());
        $this->assertDatabaseHas('rooms', ['id' => 1, 'tenant_id' => 1, 'nursery_id' => 1]);
        $this->assertDatabaseHas('rooms', ['id' => 3, 'tenant_id' => 2, 'nursery_id' => 2]);
        $this->assertDatabaseHas('children', ['id' => 1, 'tenant_id' => 1, 'name' => 'Ava']);
        $this->assertDatabaseHas('children', ['id' => 3, 'tenant_id' => 2, 'name' => 'Mia']);
        $this->assertDatabaseCount('check_ins', 0);
    }
}

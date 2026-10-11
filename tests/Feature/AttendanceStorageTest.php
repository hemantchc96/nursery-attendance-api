<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceStorageTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): void
    {
        DB::table('tenants')->insert([['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']]);
        DB::table('nurseries')->insert([['id' => 1, 'tenant_id' => 1, 'name' => 'A'], ['id' => 2, 'tenant_id' => 2, 'name' => 'B']]);
        DB::table('rooms')->insert([['id' => 1, 'tenant_id' => 1, 'nursery_id' => 1, 'name' => 'A'], ['id' => 2, 'tenant_id' => 2, 'nursery_id' => 2, 'name' => 'B']]);
        DB::table('children')->insert([['id' => 1, 'tenant_id' => 1, 'name' => 'A'], ['id' => 2, 'tenant_id' => 2, 'name' => 'B'], ['id' => 3, 'tenant_id' => 1, 'name' => 'C']]);
    }

    public function test_room_cannot_reference_a_foreign_nursery(): void
    {
        $this->fixtures();
        $this->expectException(QueryException::class);
        DB::table('rooms')->insert(['tenant_id' => 1, 'nursery_id' => 2, 'name' => 'Wrong']);
    }

    public function test_attendance_cannot_reference_a_foreign_room(): void
    {
        $this->fixtures();
        $this->expectException(QueryException::class);
        DB::table('check_ins')->insert(['tenant_id' => 1, 'room_id' => 2, 'child_id' => 1, 'checked_in_at' => now()]);
    }

    public function test_attendance_cannot_reference_a_foreign_child(): void
    {
        $this->fixtures();
        $this->expectException(QueryException::class);
        DB::table('check_ins')->insert(['tenant_id' => 1, 'room_id' => 1, 'child_id' => 2, 'checked_in_at' => now()]);
    }

    public function test_duplicate_active_attendance_is_rejected_by_database(): void
    {
        $this->fixtures();
        $visit = ['tenant_id' => 1, 'room_id' => 1, 'child_id' => 1, 'checked_in_at' => now()];
        DB::table('check_ins')->insert($visit);
        $this->expectException(QueryException::class);
        DB::table('check_ins')->insert($visit);
    }

    public function test_history_and_other_children_do_not_block_active_attendance(): void
    {
        $this->fixtures();
        $visit = ['tenant_id' => 1, 'room_id' => 1, 'child_id' => 1, 'checked_in_at' => now()];
        DB::table('check_ins')->insert($visit + ['checked_out_at' => now()]);
        DB::table('check_ins')->insert($visit);
        DB::table('check_ins')->insert(array_replace($visit, ['child_id' => 3]));
        $this->assertSame(2, DB::table('check_ins')->whereNull('checked_out_at')->count());
    }
}

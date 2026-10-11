<?php

namespace Tests\Feature;

use App\Attendance\TenantContext;
use App\Models\CheckIn;
use App\Models\Child;
use App\Models\Nursery;
use App\Models\Room;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RoomAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function checkIn(int $room = 1, int $child = 1, int $tenant = 1)
    {
        return $this->postJson("/rooms/{$room}/check-ins", ['child_id' => $child], ['X-Tenant-ID' => (string) $tenant]);
    }

    public function test_full_lifecycle_and_old_checkout_retry(): void
    {
        $this->getJson('/rooms/1/occupants', ['X-Tenant-ID' => '1'])->assertOk()->assertExactJson(['data' => []]);
        $this->travelTo(now()->startOfSecond());
        $visit = $this->checkIn()->assertCreated()->assertJsonStructure(['data' => ['id', 'room_id', 'child_id', 'checked_in_at', 'checked_out_at']])
            ->assertJsonPath('data.room_id', 1)->assertJsonPath('data.child_id', 1)->assertJsonPath('data.checked_out_at', null);
        $id = $visit->json('data.id');
        $this->assertSame(now()->toISOString(), $visit->json('data.checked_in_at'));
        $this->assertDatabaseHas('check_ins', ['id' => $id, 'tenant_id' => 1, 'checked_out_at' => null]);
        $this->getJson('/rooms/1/occupants', ['X-Tenant-ID' => '1'])->assertExactJson(['data' => [[
            'child_id' => 1, 'child_name' => 'Ava', 'check_in_id' => $id, 'checked_in_at' => $visit->json('data.checked_in_at'),
        ]]]);
        $this->travel(1)->minute();
        $out = $this->postJson("/check-ins/{$id}/check-out", [], ['X-Tenant-ID' => '1'])->assertOk();
        $this->assertSame(now()->toISOString(), $out->json('data.checked_out_at'));
        $this->getJson('/rooms/1/occupants', ['X-Tenant-ID' => '1'])->assertExactJson(['data' => []]);
        $next = $this->checkIn(2)->assertCreated()->json('data.id');
        $this->assertNotSame($id, $next);
        $this->travel(1)->minute();
        $this->postJson("/check-ins/{$id}/check-out", [], ['X-Tenant-ID' => '1'])->assertOk()->assertJsonPath('data.checked_out_at', $out->json('data.checked_out_at'));
        $this->assertDatabaseHas('check_ins', ['id' => $next, 'checked_out_at' => null]);
        $this->assertDatabaseCount('check_ins', 2);
    }

    public function test_occupants_are_ordered_and_exclude_other_rooms_and_completed_visits(): void
    {
        $this->checkIn(1, 2)->assertCreated();
        $this->checkIn(1, 1)->assertCreated();
        $child = Child::factory()->create(['tenant_id' => 1]);
        $this->checkIn(2, $child->id)->assertCreated();
        CheckIn::factory()->create(['tenant_id' => 1, 'room_id' => 1, 'child_id' => 1, 'checked_out_at' => now()]);
        $this->checkIn(3, 3, 2)->assertCreated();
        $response = $this->getJson('/rooms/1/occupants', ['X-Tenant-ID' => '1'])->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame([1, 2], array_column($response->json('data'), 'child_id'));
    }

    public function test_duplicate_and_second_room_requests_preserve_original_visit(): void
    {
        $id = $this->checkIn()->assertCreated()->json('data.id');
        $nursery = Nursery::factory()->create(['tenant_id' => 1]);
        $room = Room::factory()->create(['nursery_id' => $nursery->id]);
        foreach ([1, 2, $room->id] as $roomId) {
            $this->checkIn($roomId)->assertConflict()->assertJsonStructure(['message']);
        }
        $this->assertDatabaseCount('check_ins', 1);
        $this->assertDatabaseHas('check_ins', ['id' => $id, 'room_id' => 1, 'checked_out_at' => null]);
    }

    public static function invalidPayloads(): array
    {
        return [
            'missing' => [[]], 'null' => [['child_id' => null]], 'word' => [['child_id' => 'abc']],
            'zero' => [['child_id' => 0]], 'negative' => [['child_id' => -1]],
            'fraction' => [['child_id' => 1.5]], 'boolean' => [['child_id' => true]], 'array' => [['child_id' => [1]]],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_payloads_return_422_without_mutation(array $payload): void
    {
        $this->postJson('/rooms/1/check-ins', $payload, ['X-Tenant-ID' => '1'])->assertUnprocessable()->assertJsonValidationErrors('child_id');
        $this->assertDatabaseCount('check_ins', 0);
    }

    public static function invalidHeaders(): array
    {
        return array_map(fn ($header) => [$header], [null, '', 'abc', '0', '-1', '1.5', '1e0', '999999999999999999999999']);
    }

    #[DataProvider('invalidHeaders')]
    public function test_invalid_headers_fail_for_every_endpoint(?string $header): void
    {
        $headers = $header === null ? [] : ['X-Tenant-ID' => $header];
        $this->get('/rooms/1/occupants', $headers)->assertStatus(400)->assertJsonStructure(['message']);
        $this->post('/rooms/1/check-ins', ['child_id' => 1], $headers)->assertStatus(400)->assertJsonStructure(['message']);
        $this->post('/check-ins/1/check-out', [], $headers)->assertStatus(400)->assertJsonStructure(['message']);
        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_unknown_tenant_fails_before_resource_access(): void
    {
        $headers = ['X-Tenant-ID' => '999'];
        $this->get('/rooms/1/occupants', $headers)->assertNotFound()->assertExactJson(['message' => 'Resource not found.']);
        $this->post('/rooms/1/check-ins', ['child_id' => 1], $headers)->assertNotFound();
        $this->post('/check-ins/1/check-out', [], $headers)->assertNotFound();
        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_foreign_and_missing_resources_are_indistinguishable_and_cannot_be_changed(): void
    {
        $foreign = $this->checkIn(3, 3, 2)->assertCreated()->json('data.id');
        $this->checkIn()->assertCreated();
        $before = DB::table('check_ins')->orderBy('id')->get()->toJson();
        $headers = ['X-Tenant-ID' => '1'];
        foreach ([3, 999] as $room) {
            $this->getJson("/rooms/{$room}/occupants", $headers)->assertNotFound()->assertExactJson(['message' => 'Resource not found.']);
            $this->checkIn($room)->assertNotFound()->assertExactJson(['message' => 'Resource not found.']);
        }
        foreach ([3, 999] as $child) {
            $this->checkIn(1, $child)->assertNotFound()->assertExactJson(['message' => 'Resource not found.']);
        }
        foreach ([$foreign, 999] as $id) {
            $this->postJson("/check-ins/{$id}/check-out", [], $headers)->assertNotFound()->assertExactJson(['message' => 'Resource not found.']);
        }
        $this->assertSame($before, DB::table('check_ins')->orderBy('id')->get()->toJson());
    }

    public function test_body_tenant_cannot_override_request_tenant(): void
    {
        $this->postJson('/rooms/1/check-ins', ['child_id' => 1, 'tenant_id' => 2, 'checked_out_at' => now()->toISOString()], ['X-Tenant-ID' => '1'])->assertCreated();
        $this->assertDatabaseHas('check_ins', ['tenant_id' => 1, 'room_id' => 1, 'child_id' => 1, 'checked_out_at' => null]);
        $this->postJson('/rooms/3/check-ins', ['child_id' => 3, 'tenant_id' => 2], ['X-Tenant-ID' => '1'])->assertNotFound();
        $this->assertDatabaseCount('check_ins', 1);
    }

    public function test_successive_requests_do_not_reuse_tenant_context(): void
    {
        $this->checkIn()->assertCreated();
        $this->checkIn(3, 3, 2)->assertCreated();
        $this->get('/rooms/1/occupants', ['X-Tenant-ID' => '1'])->assertOk()->assertJsonPath('data.0.child_id', 1);
        $this->get('/rooms/3/occupants', ['X-Tenant-ID' => '2'])->assertOk()->assertJsonPath('data.0.child_id', 3);
        $this->get('/rooms/1/occupants')->assertStatus(400);
        $this->get('/rooms/1/occupants', ['X-Tenant-ID' => '2'])->assertNotFound();
    }

    public function test_context_without_tenant_fails_closed(): void
    {
        $this->expectException(HttpException::class);
        TenantContext::fromRequest(Request::create('/'));
    }

    public function test_errors_are_json_without_accept_header(): void
    {
        $this->post('/rooms/1/check-ins', [], ['X-Tenant-ID' => '1'])->assertUnprocessable()->assertJsonValidationErrors('child_id');
        $this->post('/rooms/999/check-ins', ['child_id' => 1], ['X-Tenant-ID' => '1'])->assertNotFound()->assertExactJson(['message' => 'Resource not found.']);
        $this->checkIn()->assertCreated();
        $this->post('/rooms/1/check-ins', ['child_id' => 1], ['X-Tenant-ID' => '1'])->assertConflict()->assertJsonStructure(['message']);
    }

    public function test_unrelated_database_failures_are_not_translated_to_conflicts(): void
    {
        CheckIn::creating(function () {
            throw new QueryException('sqlite', 'insert', [], new \PDOException('Unrelated storage failure'));
        });
        try {
            $this->checkIn()->assertStatus(500);
            $this->assertDatabaseCount('check_ins', 0);
        } finally {
            CheckIn::flushEventListeners();
        }
    }
}

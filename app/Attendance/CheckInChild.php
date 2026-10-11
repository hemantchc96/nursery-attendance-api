<?php

namespace App\Attendance;

use App\Models\CheckIn;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CheckInChild
{
    public function execute(TenantContext $tenant, string $roomId, int $childId): CheckIn
    {
        $room = $tenant->rooms()->findOrFail($roomId);
        $child = $tenant->children()->findOrFail($childId);

        try {
            // Resolve resources before the write transaction to avoid SQLite snapshot upgrades.
            return DB::transaction(fn () => CheckIn::create([
                'tenant_id' => $tenant->id,
                'room_id' => $room->id,
                'child_id' => $child->id,
                'checked_in_at' => now('UTC'),
            ]));
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->errorInfo[2] ?? '', 'UNIQUE constraint failed: check_ins.tenant_id, check_ins.child_id')) {
                throw $exception;
            }

            abort(409, 'Child is already checked into a room. Check them out first.');
        }
    }
}

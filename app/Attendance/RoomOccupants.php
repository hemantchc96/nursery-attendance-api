<?php

namespace App\Attendance;

use Illuminate\Support\Collection;

class RoomOccupants
{
    public function get(TenantContext $tenant, string $roomId): Collection
    {
        $room = $tenant->rooms()->findOrFail($roomId);

        return $tenant->checkIns()
            ->join('children', function ($join) {
                $join->on('children.id', '=', 'check_ins.child_id')
                    ->on('children.tenant_id', '=', 'check_ins.tenant_id');
            })
            ->where('children.tenant_id', $tenant->id)
            ->where('room_id', $room->id)
            ->whereNull('checked_out_at')
            ->orderBy('child_id')
            ->get(['check_ins.id', 'child_id', 'children.name as child_name', 'checked_in_at'])
            ->map(fn ($visit) => [
                'child_id' => $visit->child_id,
                'child_name' => $visit->child_name,
                'check_in_id' => $visit->id,
                'checked_in_at' => $visit->checked_in_at->toISOString(),
            ]);
    }
}

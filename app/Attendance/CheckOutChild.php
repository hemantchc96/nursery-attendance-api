<?php

namespace App\Attendance;

use App\Models\CheckIn;

class CheckOutChild
{
    public function execute(TenantContext $tenant, string $id): CheckIn
    {
        $visit = $tenant->checkIns()->findOrFail($id);
        $tenant->checkIns()->whereKey($visit->id)->whereNull('checked_out_at')->update(['checked_out_at' => now('UTC')]);

        return $tenant->checkIns()->findOrFail($visit->id);
    }
}

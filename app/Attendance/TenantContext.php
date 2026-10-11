<?php

namespace App\Attendance;

use App\Models\CheckIn;
use App\Models\Child;
use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final readonly class TenantContext
{
    public function __construct(public int $id) {}

    public static function fromRequest(Request $request): self
    {
        $context = $request->attributes->get(self::class);
        abort_unless($context instanceof self, 400, 'A valid X-Tenant-ID header is required.');

        return $context;
    }

    public function rooms(): Builder
    {
        return Room::query()->where('tenant_id', $this->id);
    }

    public function children(): Builder
    {
        return Child::query()->where('tenant_id', $this->id);
    }

    public function checkIns(): Builder
    {
        return CheckIn::query()->where('check_ins.tenant_id', $this->id);
    }
}

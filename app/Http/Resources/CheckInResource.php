<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckInResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'child_id' => $this->child_id,
            'checked_in_at' => $this->checked_in_at->toISOString(),
            'checked_out_at' => $this->checked_out_at?->toISOString(),
        ];
    }
}

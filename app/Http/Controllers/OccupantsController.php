<?php

namespace App\Http\Controllers;

use App\Attendance\RoomOccupants;
use App\Attendance\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OccupantsController extends Controller
{
    public function __invoke(Request $request, string $room, RoomOccupants $query): JsonResponse
    {
        return response()->json(['data' => $query->get(TenantContext::fromRequest($request), $room)]);
    }
}

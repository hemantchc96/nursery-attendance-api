<?php

namespace App\Http\Controllers;

use App\Attendance\CheckInChild;
use App\Attendance\TenantContext;
use App\Http\Requests\CheckInRequest;
use App\Http\Resources\CheckInResource;
use Illuminate\Http\JsonResponse;

class CheckInController extends Controller
{
    public function __invoke(CheckInRequest $request, string $room, CheckInChild $action): JsonResponse
    {
        $visit = $action->execute(TenantContext::fromRequest($request), $room, (int) $request->validated('child_id'));

        return (new CheckInResource($visit))->response()->setStatusCode(201);
    }
}

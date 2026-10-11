<?php

namespace App\Http\Controllers;

use App\Attendance\CheckOutChild;
use App\Attendance\TenantContext;
use App\Http\Resources\CheckInResource;
use Illuminate\Http\Request;

class CheckOutController extends Controller
{
    public function __invoke(Request $request, string $id, CheckOutChild $action): CheckInResource
    {
        return new CheckInResource($action->execute(TenantContext::fromRequest($request), $id));
    }
}

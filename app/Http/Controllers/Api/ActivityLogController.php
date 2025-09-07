<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Display activity logs for a team
     */
    public function index(Request $request, string $teamId)
    {
        // todo: implement
    }

    /**
     * Export activity logs
     */
    public function export(Request $request, string $teamId)
    {
        // todo: implement
    }
}

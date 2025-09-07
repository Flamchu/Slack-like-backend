<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    /**
     * Display a listing of channels for a team
     */
    public function index(Request $request, string $teamId)
    {
        // todo: implement
    }

    /**
     * Store a newly created channel
     */
    public function store(Request $request, string $teamId)
    {
        // todo: implement
    }

    /**
     * Display the specified channel
     */
    public function show(string $teamId, string $id)
    {
        // todo: implement
    }

    /**
     * Update the specified channel
     */
    public function update(Request $request, string $teamId, string $id)
    {
        // todo: implement
    }

    /**
     * Remove the specified channel
     */
    public function destroy(string $teamId, string $id)
    {
        // todo: implement
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    /**
     * Display messages for a channel
     */
    public function index(Request $request, string $teamId, string $channelId)
    {
        // todo: implement
    }

    /**
     * Store a new message
     */
    public function store(Request $request, string $teamId, string $channelId)
    {
        // todo: implement
    }

    /**
     * Update a message
     */
    public function update(Request $request, string $teamId, string $channelId, string $id)
    {
        // todo: implement
    }

    /**
     * Delete a message
     */
    public function destroy(string $teamId, string $channelId, string $id)
    {
        // todo: implement
    }
}

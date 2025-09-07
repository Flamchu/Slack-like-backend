<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    /**
     * Display a listing of teams
     */
    public function index(Request $request)
    {
        // todo: implement
    }

    /**
     * Store a newly created team
     */
    public function store(Request $request)
    {
        // todo: implement
    }

    /**
     * Display the specified team
     */
    public function show(string $id)
    {
        // todo: implement
    }

    /**
     * Update the specified team
     */
    public function update(Request $request, string $id)
    {
        // todo: implement
    }

    /**
     * Remove the specified team
     */
    public function destroy(string $id)
    {
        // todo: implement
    }

    /**
     * Get team members
     */
    public function members(string $id)
    {
        // todo: implement
    }

    /**
     * Invite user to team
     */
    public function invite(Request $request, string $id)
    {
        // todo: implement
    }

    /**
     * Join team via invitation
     */
    public function join(Request $request)
    {
        // todo: implement
    }

    /**
     * Leave team
     */
    public function leave(string $id)
    {
        // todo: implement
    }
}

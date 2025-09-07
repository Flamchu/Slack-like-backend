<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    /**
     * Display all users for admin management
     */
    public function index(Request $request)
    {
        // todo: implement
    }

    /**
     * Show user details for admin
     */
    public function show(string $id)
    {
        // todo: implement
    }

    /**
     * Update user as admin
     */
    public function update(Request $request, string $id)
    {
        // todo: implement
    }

    /**
     * Suspend/activate user
     */
    public function toggleStatus(string $id)
    {
        // todo: implement
    }

    /**
     * Delete user account
     */
    public function destroy(string $id)
    {
        // todo: implement
    }
}
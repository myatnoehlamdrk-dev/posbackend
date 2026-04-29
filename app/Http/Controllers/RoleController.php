<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
   

    /**
     * Display the specified resource.
     */
    public function show(role $role)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        $role = Role::create([
            'name' => $request->name,
        ]);

        return response()->json([
            'message' => 'Role created',
            'data' => $role
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, role $role)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(role $role)
    {
        //
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        //  Validation
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:customers,email',
            'phone' => 'required|string',
            'password' => 'required|min:6',
        ]);

        //  Save user
        $user = Customer::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password), // 🔒 important
        ]);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user
        ], 201);
    }
    public function login(Request $request)
    {
        //  Validation
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:customers,email',
            'role_id' => 'required|integer',
            'password' => 'required|min:6',
        ]);

        //  Save user
        $user = Customer::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password), // 🔒 important
        ]);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user
        ], 201);
    }
     public function index()
{
    $users = \App\Models\User::all();

    return response()->json($users, 200);
}
}
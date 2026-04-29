<?php

namespace App\Http\Controllers;

use App\Models\Inventorylog;
use Illuminate\Http\Request;

class InventoryLogController extends Controller
{
    

 
    public function store(Request $request)
    {
        $request->validate([
            
            'product_id' => 'required|integer',
            'user_id' => 'required|integer',
            'change_amount' => 'required|numeric',
            // 'category' => 'required|string',
            'reason' => 'required|string',
        ]);

        $log = Inventorylog::create([
            
            'product_id' => $request->product_id,
            'user_id' => $request->user_id,
            'change_amount' => $request->change_amount,
            // 'category' => $request->category,
            'reason' => $request->reason,
        ]);

        return response()->json([
            'message' => 'Inventory log created',
            'data' => $log
        ], 201);
    }
    
}

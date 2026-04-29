<?php

namespace App\Http\Controllers;

use App\Models\Inventorylog;
use Illuminate\Http\Request;

class InventoryLogController extends Controller
{
    // ✅ FETCH ALL
    public function index()
    {
        $logs = Inventorylog::latest()->get();

        return response()->json($logs);
    }

    // ✅ STORE (already yours, just kept)
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer',
            'user_id' => 'required|integer',
            'change_amount' => 'required|numeric',
            'reason' => 'required|string',
        ]);

        $log = Inventorylog::create([
            'product_id' => $request->product_id,
            'user_id' => $request->user_id,
            'change_amount' => $request->change_amount,
            'reason' => $request->reason,
        ]);

        return response()->json([
            'message' => 'Inventory log created',
            'data' => $log
        ], 201);
    }

    // ✅ UPDATE
    public function update(Request $request, $id)
    {
        $log = Inventorylog::findOrFail($id);

        $request->validate([
            'product_id' => 'required|integer',
            'user_id' => 'required|integer',
            'change_amount' => 'required|numeric',
            'reason' => 'required|string',
        ]);

        $log->update([
            'product_id' => $request->product_id,
            'user_id' => $request->user_id,
            'change_amount' => $request->change_amount,
            'reason' => $request->reason,
        ]);

        return response()->json([
            'message' => 'Inventory log updated',
            'data' => $log
        ]);
    }
    public function destroy($id)
{
    try {
        $log = Inventorylog::findOrFail($id);
        $log->delete();

        return response()->json([
            'message' => 'Deleted successfully'
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage()
        ], 500);
    }
}
}
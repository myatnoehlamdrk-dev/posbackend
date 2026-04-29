<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $orders = Order::all();

    return response()->json($orders, 200);
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
    public function store(Request $request)
    {
        $request->validate([
            
            'user_id' => 'required|integer',
            'c_id' => 'required|integer',
            'invoice_no' => 'required|String',
            'total_amount' => 'required|integer',
            'payment' => 'required|string',
            // 'created_at' => 'required|date_format:Y-m-d H:i:s',
        ]);

        $product = Order::create([
            
            'user_id' => $request->user_id,
            'c_id' => $request->c_id,
            'invoice_no' => $request->invoice_no,
            'total_amount' => $request->total_amount,
            'payment' => $request->payment,
            // 'created_at' => $request->created_at,
        ]);

        return response()->json([
            'message' => 'Product created',
            'data' => $product
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(order $order)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(order $order)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
{
    $order = Order::findOrFail($id);

    $request->validate([
        'user_id' => 'required|integer',
        'c_id' => 'required|integer',
        'invoice_no' => 'required|string',
        'total_amount' => 'required|numeric',
        'payment' => 'required|string',
    ]);

    $order->update([
        'user_id' => $request->user_id,
        'c_id' => $request->c_id,
        'invoice_no' => $request->invoice_no,
        'total_amount' => $request->total_amount,
        'payment' => $request->payment,
    ]);

    return response()->json([
        'message' => 'Order updated successfully',
        'data' => $order
    ], 200);
}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
{
    $order = Order::findOrFail($id);

    $order->delete();

    return response()->json([
        'message' => 'Order deleted successfully'
    ], 200);
}
}

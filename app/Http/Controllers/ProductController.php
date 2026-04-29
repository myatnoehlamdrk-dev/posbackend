<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Product::all());
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
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string',
            'sku' => 'required|string|unique:products,sku',
            'selling_price' => 'required|numeric',
            'stock_quantity' => 'required|integer',
        ]);

        $product = Product::create([
            'category_id' => $request->category_id,
            'name' => $request->name,
            'sku' => $request->sku,
            'selling_price' => $request->selling_price,
            'stock_quantity' => $request->stock_quantity,
        ]);

        return response()->json([
            'message' => 'Product created',
            'data' => $product
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(product $product)
    {
            return response()->json(
            Product::where('category_id', 4)->get()
            );
    }

     public function showgift(product $product)
    {
            return response()->json(
            Product::where('category_id', 1)->get()
            );
    }
     public function showfashion(product $product)
    {
            return response()->json(
            Product::where('category_id', 2)->get()
            );
    }
     public function showbook(product $product)
    {
            return response()->json(
            Product::where('category_id', 3)->get()
            );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(product $product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, product $product)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(product $product)
    {
        //
    }
}

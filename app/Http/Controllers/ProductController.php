<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     * GET /api/products
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query();

        // Search by name atau sku
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%") // ilike = case-insensitive di PostgreSQL
                  ->orWhere('sku', 'ilike', "%{$search}%");
            });
        }

        // Filter stok menipis (stock <= min_stock)
        if ($request->boolean('low_stock')) {
            $query->whereColumn('stock', '<=', 'min_stock')
                  ->where('min_stock', '>', 0);
        }

        // Sorting
        $sortBy    = $request->input('sort_by', 'name');
        $sortOrder = $request->input('sort_order', 'asc');
        $allowedSorts = ['name', 'price', 'stock', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Pagination (default 15 per page)
        $products = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $products,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * POST /api/products
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:150',
            'sku'         => 'required|string|max:100|unique:products,sku',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'sometimes|integer|min:0',
            'min_stock'   => 'sometimes|integer|min:0',
            'description' => 'nullable|string',
        ]);

        $product = Product::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan.',
            'data'    => $product,
        ], 201);
    }

    /**
     * Display the specified resource.
     * GET /api/products/{id}
     */
    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $product,
        ]);
    }

    /**
     * Update the specified resource in storage.
     * PUT/PATCH /api/products/{id}
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:150',
            'sku'         => "sometimes|required|string|max:100|unique:products,sku,{$product->id}",
            'price'       => 'sometimes|required|numeric|min:0',
            'stock'       => 'sometimes|integer|min:0',
            'min_stock'   => 'sometimes|integer|min:0',
            'description' => 'nullable|string',
        ]);

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil diperbarui.',
            'data'    => $product->fresh(), // fresh() = ambil data terbaru dari DB
        ]);
    }

    /**
     * Remove the specified resource from storage.
     * DELETE /api/products/{id}
     */
    public function destroy(Product $product): JsonResponse
    {
        // Cek apakah produk pernah ada di transaksi
        // onDelete cascade di transaction_details akan hapus detail-nya,
        // tapi kita perlu pertimbangkan apakah ini boleh dihapus
        if ($product->transactionDetails()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak bisa dihapus karena sudah memiliki riwayat transaksi.',
            ], 422);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil dihapus.',
        ]);
    }

    /**
     * Update stok saja (untuk keperluan inventory adjustment)
     * PATCH /api/products/{id}/stock
     */
    public function updateStock(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'stock'  => 'required|integer|min:0',
            'reason' => 'nullable|string|max:255', // alasan penyesuaian stok
        ]);

        $oldStock = $product->stock;
        $product->update(['stock' => $validated['stock']]);

        return response()->json([
            'success' => true,
            'message' => "Stok diperbarui dari {$oldStock} menjadi {$validated['stock']}.",
            'data'    => $product->fresh(),
        ]);
    }
}
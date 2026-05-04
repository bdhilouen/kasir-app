<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
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
        $query = Product::with('category'); // load relasi kategori

        // Filter by kategori — untuk tab kategori di halaman transaksi
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by slug kategori (alternatif selain id)
        if ($request->has('category_slug')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category_slug);
            });
        }

        // Search by name atau sku
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('sku', 'ilike', "%{$search}%");
            });
        }

        // Filter stok menipis
        if ($request->boolean('low_stock')) {
            $query->whereColumn('stock', '<=', 'min_stock')
                ->where('min_stock', '>', 0);
        }

        // Sorting
        $sortBy       = $request->input('sort_by', 'name');
        $sortOrder    = $request->input('sort_order', 'asc');
        $allowedSorts = ['name', 'price', 'stock', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

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
    /**
     * Store a newly created resource in storage.
     * POST /api/products
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Tambahkan validasi category_id disini
            'category_id' => 'nullable|exists:categories,id',
            'name'        => 'required|string|max:150',
            'sku'         => 'required|string|max:100|unique:products,sku',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'sometimes|integer|min:0',
            'min_stock'   => 'sometimes|integer|min:0',
            'description' => 'nullable|string',
        ]);

        $product = Product::create($validated);

        // Load relasi category agar response API langsung menampilkan detail kategorinya
        $product->load('category');

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
            // Tambahkan validasi category_id disini juga
            'category_id' => 'sometimes|nullable|exists:categories,id',
            'name'        => 'sometimes|required|string|max:150',
            'sku'         => "sometimes|required|string|max:100|unique:products,sku,{$product->id}",
            'price'       => 'sometimes|required|numeric|min:0',
            'stock'       => 'sometimes|integer|min:0',
            'min_stock'   => 'sometimes|integer|min:0',
            'description' => 'nullable|string',
        ]);

        $product->update($validated);

        // fresh() mengambil data terbaru dari database beserta relasi category-nya
        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil diperbarui.',
            'data'    => $product->fresh('category'),
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

    /**
     * Semua kategori beserta produknya — untuk tab kategori di halaman transaksi.
     * GET /api/products/by-category
     */
    public function byCategory(Request $request): JsonResponse
    {
        $search = $request->input('search');

        $categories = Category::with(['products' => function ($q) use ($search) {
            $q->where('stock', '>', 0); // hanya produk yang stoknya ada

            if ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('sku', 'ilike', "%{$search}%");
                });
            }

            $q->orderBy('name');
        }])
            ->orderBy('name')
            ->get();

        // Tambahkan tab "Semua" di paling depan
        $allProducts = Product::with('category')
            ->where('stock', '>', 0)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('sku', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'all'        => $allProducts,        // untuk tab "Semua"
                'categories' => $categories,          // untuk tab per kategori
            ],
        ]);
    }
}

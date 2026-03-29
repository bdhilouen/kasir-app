<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     * GET /api/categories
     */
    public function index(Request $request): JsonResponse
    {
        $query = Category::withCount('products'); // tampilkan jumlah produk per kategori

        if ($request->has('search')) {
            $query->where('name', 'ilike', "%{$request->search}%");
        }

        // Kalau butuh semua kategori tanpa pagination (untuk dropdown)
        if ($request->boolean('all')) {
            $categories = $query->orderBy('name')->get();

            return response()->json([
                'success' => true,
                'data'    => $categories,
            ]);
        }

        $categories = $query->orderBy('name')->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $categories,
        ]);
    }

    /**
     * Store a newly created category.
     * POST /api/categories
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100|unique:categories,name',
            'description' => 'nullable|string',
        ]);

        // Slug di-generate otomatis dari model boot()
        $category = Category::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil ditambahkan.',
            'data'    => $category,
        ], 201);
    }

    /**
     * Display the specified category + produk di dalamnya.
     * GET /api/categories/{id}
     */
    public function show(Category $category): JsonResponse
    {
        $category->loadCount('products');
        $category->load('products');

        return response()->json([
            'success' => true,
            'data'    => $category,
        ]);
    }

    /**
     * Update the specified category.
     * PUT/PATCH /api/categories/{id}
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name'        => "required|string|max:100|unique:categories,name,{$category->id}",
            'description' => 'nullable|string',
        ]);

        // Slug otomatis terupdate dari model boot()
        $category->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil diperbarui.',
            'data'    => $category->fresh(),
        ]);
    }

    /**
     * Remove the specified category.
     * DELETE /api/categories/{id}
     */
    public function destroy(Category $category): JsonResponse
    {
        // Cek apakah masih ada produk yang pakai kategori ini
        if ($category->products()->exists()) {
            $productCount = $category->products()->count();

            return response()->json([
                'success' => false,
                'message' => "Kategori tidak bisa dihapus karena masih dipakai oleh {$productCount} produk.",
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil dihapus.',
        ]);
    }

    /**
     * Pindahkan semua produk dari satu kategori ke kategori lain, lalu hapus kategori asal.
     * POST /api/categories/{id}/merge
     */
    public function merge(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'target_category_id' => "required|exists:categories,id|different:id",
        ]);

        $targetCategory = Category::findOrFail($validated['target_category_id']);

        // Pindahkan semua produk ke kategori target
        $movedCount = $category->products()->count();
        $category->products()->update(['category_id' => $targetCategory->id]);

        // Hapus kategori asal
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => "{$movedCount} produk berhasil dipindahkan ke kategori '{$targetCategory->name}' dan kategori '{$category->name}' dihapus.",
            'data'    => $targetCategory->fresh()->loadCount('products'),
        ]);
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

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
        $ownerId = Category::resolveOwnerId();

        $validated = $request->validate([
            'name'        => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')
                    ->where(fn($query) => $query->where('owner_id', $ownerId)),
            ],
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
        $ownerId = Category::resolveOwnerId();

        $validated = $request->validate([
            'name'        => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')
                    ->where(fn($query) => $query->where('owner_id', $ownerId))
                    ->ignore($category->id),
            ],
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
        $ownerId = Category::resolveOwnerId();

        $validated = $request->validate([
            'target_category_id' => [
                'required',
                Rule::exists('categories', 'id')
                    ->where(fn($query) => $query->where('owner_id', $ownerId)),
                Rule::notIn([$category->id]),
            ],
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

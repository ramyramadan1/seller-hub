<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Tenant endpoints: the default connection is the store's own database (SetTenant middleware). */
class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()->latest('id');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
        }

        return response()->json($query->paginate(min((int) $request->query('per_page', 20), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku'],
            'description' => ['nullable', 'string', 'max:20000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ]);

        $product = Product::create($data + ['stock' => 0]);

        return response()->json($product->fresh(), 201);
    }
}

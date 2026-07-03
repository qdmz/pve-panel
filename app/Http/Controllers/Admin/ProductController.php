<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateProductRequest;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        try {
            $products = Product::withCount('virtualMachines')
                         ->orderBy('sort_order')
                         ->get();

            return ApiResponse::success(['products' => $products]);
        } catch (\Exception $e) {
            \Log::error('Admin\\ProductController::index failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve products.', 500);
        }
    }

    public function show(Product $product)
    {
        try {
            $product->loadCount('virtualMachines', 'orders');

            return ApiResponse::success(['product' => $product]);
        } catch (\Exception $e) {
            \Log::error('Admin\\ProductController::show failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve product.', 500);
        }
    }

    public function store(CreateProductRequest $request)
    {
        try {
            $product = Product::create($request->validated());

            return ApiResponse::success(['product' => $product], 'Product created.', 201);
        } catch (\Exception $e) {
            \Log::error('Admin\\ProductController::store failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to create product.', 500);
        }
    }

    public function update(Request $request, Product $product)
    {
        try {
            $data = $request->only([
                'name', 'type', 'cpu', 'memory', 'disk', 'bandwidth',
                'traffic', 'monthly_price', 'yearly_price',
                'description', 'status', 'sort_order', 'stock',
                'node_ids', 'template_ids', 'features',
            ]);

            $product->update($data);

            return ApiResponse::success(['product' => $product], 'Product updated.');
        } catch (\Exception $e) {
            \Log::error('Admin\\ProductController::update failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to update product.', 500);
        }
    }

    public function destroy(Product $product)
    {
        try {
            if ($product->virtualMachines()->count() > 0) {
                return ApiResponse::error('Cannot delete product with active VMs.', 400);
            }

            $product->delete();

            return ApiResponse::success(null, 'Product deleted.');
        } catch (\Exception $e) {
            \Log::error('Admin\\ProductController::destroy failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to delete product.', 500);
        }
    }

    public function toggleStatus(Product $product)
    {
        try {
            $product->status = $product->status === 'active' ? 'inactive' : 'active';
            $product->save();

            return ApiResponse::success(['product' => $product], 'Product status toggled.');
        } catch (\Exception $e) {
            \Log::error('Admin\\ProductController::toggleStatus failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to toggle product status.', 500);
        }
    }

    public function sort(Request $request)
    {
        try {
            $sortedIds = $request->input('ids', []);

            foreach ($sortedIds as $index => $id) {
                Product::where('id', $id)->update(['sort_order' => $index + 1]);
            }

            return ApiResponse::success(null, 'Products reordered.');
        } catch (\Exception $e) {
            \Log::error('Admin\\ProductController::sort failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to reorder products.', 500);
        }
    }
}

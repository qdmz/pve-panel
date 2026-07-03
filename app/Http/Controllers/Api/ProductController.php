<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Models\Product;

class ProductController extends Controller
{
    public function index()
    {
        try {
            $products = Product::active()
                ->when(request('type'), function ($query, $type) {
                    return $query->where('type', $type);
                })
                ->orderBy('sort_order')
                ->paginate(20);

            $onlineNodes = Node::where('status', 'online')->count();

            $products->getCollection()->transform(function ($product) use ($onlineNodes) {
                $nodeIds      = $product->node_ids ?? [];
                $availableNodes = $onlineNodes;
                $product->available = $availableNodes > 0;
                return $product;
            });

            return ApiResponse::paginated($products, 'Products retrieved.');
        } catch (\Exception $e) {
            \Log::error('ProductController::index failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve products.', 500);
        }
    }

    public function show(Product $product)
    {
        try {
            $onlineNodes = Node::where('status', 'online')->count();
            $product->available = $onlineNodes > 0;

            return ApiResponse::success(['product' => $product]);
        } catch (\Exception $e) {
            \Log::error('ProductController::show failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve product.', 500);
        }
    }
}

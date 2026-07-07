<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Node;

class NodeTemplateController extends Controller
{
    public function index(Node $node)
    {
        try {
            $templates = $node->templates()
                ->orderBy('type')
                ->orderBy('name')
                ->get();

            return ApiResponse::success(['templates' => $templates]);
        } catch (\Exception $e) {
            \Log::error('NodeTemplateController::index failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to load templates.', 500);
        }
    }
}

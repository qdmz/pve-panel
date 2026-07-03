<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        try {
            $users = User::query()
                ->when($request->search, function ($query, $search) {
                    return $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->when($request->status, function ($query, $status) {
                    return $query->where('status', $status);
                })
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return ApiResponse::paginated($users, 'Users retrieved.');
        } catch (\Exception $e) {
            \Log::error('Admin\\UserController::index failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve users.', 500);
        }
    }

    public function show(User $user)
    {
        try {
            $user->load([
                'vms' => function ($q) {
                    $q->orderBy('created_at', 'desc')->limit(10);
                },
                'orders' => function ($q) {
                    $q->orderBy('created_at', 'desc')->limit(10);
                },
                'transactions' => function ($q) {
                    $q->orderBy('created_at', 'desc')->limit(10);
                },
            ]);

            $user->vm_count       = $user->virtualMachines()->count();
            $user->order_count    = $user->orders()->count();
            $user->ticket_count   = $user->tickets()->count();

            return ApiResponse::success(['user' => $user]);
        } catch (\Exception $e) {
            \Log::error('Admin\\UserController::show failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve user.', 500);
        }
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        try {
            $user->update($request->validated());

            return ApiResponse::success(['user' => $user], 'User updated.');
        } catch (\Exception $e) {
            \Log::error('Admin\\UserController::update failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to update user.', 500);
        }
    }

    public function disable(User $user)
    {
        try {
            $user->update(['status' => 'disabled']);
            $user->tokens()->delete();

            return ApiResponse::success(['user' => $user], 'User disabled.');
        } catch (\Exception $e) {
            \Log::error('Admin\\UserController::disable failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to disable user.', 500);
        }
    }

    public function enable(User $user)
    {
        try {
            $user->update(['status' => 'active']);

            return ApiResponse::success(['user' => $user], 'User enabled.');
        } catch (\Exception $e) {
            \Log::error('Admin\\UserController::enable failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to enable user.', 500);
        }
    }

    public function destroy(User $user)
    {
        try {
            $user->virtualMachines()->delete();
            $user->orders()->delete();
            $user->tickets()->delete();
            $user->tokens()->delete();
            $user->delete();

            return ApiResponse::success(null, 'User deleted.');
        } catch (\Exception $e) {
            \Log::error('Admin\\UserController::destroy failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to delete user.', 500);
        }
    }
}

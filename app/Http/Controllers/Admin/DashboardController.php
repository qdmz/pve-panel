<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Models\VirtualMachine;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        try {
            $totalUsers    = User::count();
            $totalVms      = VirtualMachine::count();
            $totalRevenue  = Order::paid()
                                  ->whereMonth('paid_at', now()->month)
                                  ->sum('amount');
            $pendingTickets = Ticket::open()->count();

            // Revenue trend (last 30 days)
            $revenueTrend = Order::paid()
                ->where('paid_at', '>=', now()->subDays(30))
                ->selectRaw('DATE(paid_at) as date, SUM(amount) as total')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->pluck('total', 'date')
                ->toArray();

            // VM type distribution
            $vmTypeDistribution = VirtualMachine::selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray();

            // Recent orders
            $recentOrders = Order::with('user:id,name,email')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            // Pending tickets
            $pendingTicketsList = Ticket::open()
                ->with('user:id,name')
                ->orderBy('priority', 'desc')
                ->orderBy('created_at', 'asc')
                ->limit(5)
                ->get();

            // Node statuses
            $nodeStatuses = Node::select('id', 'name', 'status', 'cpu_used', 'memory_used', 'disk_used', 'cpu_total', 'memory_total', 'disk_total')
                ->get();

            return ApiResponse::success([
                'total_users'       => $totalUsers,
                'total_vms'         => $totalVms,
                'total_revenue'     => (float) $totalRevenue,
                'pending_tickets'   => $pendingTickets,
                'revenue_trend'     => $revenueTrend,
                'vm_type_distribution' => $vmTypeDistribution,
                'recent_orders'     => $recentOrders,
                'pending_tickets_list' => $pendingTicketsList,
                'node_statuses'     => $nodeStatuses,
            ]);
        } catch (\Exception $e) {
            \Log::error('DashboardController::index failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve dashboard data.', 500);
        }
    }
}

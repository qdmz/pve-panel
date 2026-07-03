<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TicketReplyRequest;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        try {
            $tickets = Ticket::with('user:id,name,email')
                ->when($request->status, function ($query, $status) {
                    return $query->byStatus($status);
                })
                ->when($request->department, function ($query, $dept) {
                    return $query->byDepartment($dept);
                })
                ->when($request->priority, function ($query, $priority) {
                    return $query->byPriority($priority);
                })
                ->orderBy(
                    $request->sort_by ?? 'updated_at',
                    $request->sort_order ?? 'desc'
                )
                ->paginate(20);

            return ApiResponse::paginated($tickets, 'Tickets retrieved.');
        } catch (\Exception $e) {
            \Log::error('Admin\\TicketController::index failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve tickets.', 500);
        }
    }

    public function show(Ticket $ticket)
    {
        try {
            $ticket->load([
                'user:id,name,email',
                'replies' => function ($q) {
                    $q->with('user:id,name,role')->orderBy('created_at', 'asc');
                },
            ]);

            return ApiResponse::success(['ticket' => $ticket]);
        } catch (\Exception $e) {
            \Log::error('Admin\\TicketController::show failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve ticket.', 500);
        }
    }

    public function reply(TicketReplyRequest $request, Ticket $ticket)
    {
        try {
            if ($ticket->status === 'closed') {
                return ApiResponse::error('Cannot reply to a closed ticket.', 400);
            }

            $reply = $ticket->replies()->create([
                'user_id'  => $request->user()->id,
                'content'  => $request->content,
                'is_admin' => true,
            ]);

            $ticket->update([
                'status'        => 'replied',
                'last_reply_at' => now(),
            ]);

            return ApiResponse::success(['reply' => $reply], 'Reply added.');
        } catch (\Exception $e) {
            \Log::error('Admin\\TicketController::reply failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to add reply.', 500);
        }
    }

    public function updateStatus(Request $request, Ticket $ticket)
    {
        try {
            $validStatuses = ['open', 'in_progress', 'replied', 'closed'];

            $request->validate([
                'status' => ['required', 'string', 'in:' . implode(',', $validStatuses)],
            ]);

            $status = $request->status;
            $data   = ['status' => $status];

            if ($status === 'closed') {
                $data['closed_at'] = now();
            }

            $ticket->update($data);

            return ApiResponse::success(['ticket' => $ticket], 'Ticket status updated.');
        } catch (\Exception $e) {
            \Log::error('Admin\\TicketController::updateStatus failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to update ticket status.', 500);
        }
    }

    public function updatePriority(Request $request, Ticket $ticket)
    {
        try {
            $request->validate([
                'priority' => ['required', 'string', 'in:low,medium,high,urgent'],
            ]);

            $ticket->update(['priority' => $request->priority]);

            return ApiResponse::success(['ticket' => $ticket], 'Ticket priority updated.');
        } catch (\Exception $e) {
            \Log::error('Admin\\TicketController::updatePriority failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to update ticket priority.', 500);
        }
    }
}

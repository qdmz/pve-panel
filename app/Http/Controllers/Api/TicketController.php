<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateTicketRequest;
use App\Http\Requests\Api\TicketReplyRequest;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        try {
            $tickets = $request->user()->tickets()
                            ->when($request->status, function ($query, $status) {
                                return $query->byStatus($status);
                            })
                            ->orderBy('updated_at', 'desc')
                            ->paginate(15);

            return ApiResponse::paginated($tickets, 'Tickets retrieved.');
        } catch (\Exception $e) {
            \Log::error('TicketController::index failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve tickets.', 500);
        }
    }

    public function show(Request $request, Ticket $ticket)
    {
        try {
            if ($ticket->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            $ticket->load(['replies' => function ($q) {
                $q->with('user:id,name,role')->orderBy('created_at', 'asc');
            }]);

            return ApiResponse::success(['ticket' => $ticket]);
        } catch (\Exception $e) {
            \Log::error('TicketController::show failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve ticket.', 500);
        }
    }

    public function store(CreateTicketRequest $request)
    {
        try {
            $ticket = Ticket::create([
                'ticket_no'  => 'TKT-' . date('Ymd') . rand(1000, 9999),
                'user_id'    => $request->user()->id,
                'subject'    => $request->subject,
                'department' => $request->department,
                'priority'   => $request->priority,
                'status'     => 'open',
            ]);

            $ticket->replies()->create([
                'user_id'  => $request->user()->id,
                'content'  => $request->content,
                'is_admin' => false,
            ]);

            $ticket->update(['last_reply_at' => now()]);

            return ApiResponse::success(['ticket' => $ticket], 'Ticket created.', 201);
        } catch (\Exception $e) {
            \Log::error('TicketController::store failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to create ticket.', 500);
        }
    }

    public function reply(TicketReplyRequest $request, Ticket $ticket)
    {
        try {
            if ($ticket->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            if ($ticket->status === 'closed') {
                return ApiResponse::error('Cannot reply to a closed ticket.', 400);
            }

            $reply = $ticket->replies()->create([
                'user_id'  => $request->user()->id,
                'content'  => $request->content,
                'is_admin' => false,
            ]);

            $ticket->update([
                'status'        => 'open',
                'last_reply_at' => now(),
            ]);

            return ApiResponse::success(['reply' => $reply], 'Reply added.');
        } catch (\Exception $e) {
            \Log::error('TicketController::reply failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to add reply.', 500);
        }
    }

    public function close(Request $request, Ticket $ticket)
    {
        try {
            if ($ticket->user_id !== $request->user()->id) {
                return ApiResponse::error('Unauthorized.', 403);
            }

            if ($ticket->status === 'closed') {
                return ApiResponse::error('Ticket is already closed.', 400);
            }

            $ticket->update([
                'status'    => 'closed',
                'closed_at' => now(),
            ]);

            return ApiResponse::success(['ticket' => $ticket], 'Ticket closed.');
        } catch (\Exception $e) {
            \Log::error('TicketController::close failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to close ticket.', 500);
        }
    }
}

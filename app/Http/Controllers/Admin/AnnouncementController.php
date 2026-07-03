<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateAnnouncementRequest;
use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index()
    {
        try {
            $announcements = Announcement::orderBy('is_pinned', 'desc')
                                   ->orderBy('created_at', 'desc')
                                   ->paginate(20);

            return ApiResponse::paginated($announcements, 'Announcements retrieved.');
        } catch (\Exception $e) {
            \Log::error('Admin\\AnnouncementController::index failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve announcements.', 500);
        }
    }

    public function store(CreateAnnouncementRequest $request)
    {
        try {
            $announcement = Announcement::create([
                'title'        => $request->title,
                'content'      => $request->content,
                'type'         => $request->type ?? 'info',
                'status'       => $request->status ?? 'draft',
                'published_at' => ($request->status === 'published') ? now() : null,
            ]);

            return ApiResponse::success(['announcement' => $announcement], 'Announcement created.', 201);
        } catch (\Exception $e) {
            \Log::error('Admin\\AnnouncementController::store failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to create announcement.', 500);
        }
    }

    public function update(Request $request, Announcement $announcement)
    {
        try {
            $data = $request->only(['title', 'content', 'type', 'status']);

            if (isset($data['status'])) {
                if ($data['status'] === 'published' && $announcement->status !== 'published') {
                    $data['published_at'] = now();
                } elseif ($data['status'] === 'draft') {
                    $data['published_at'] = null;
                }
            }

            $announcement->update($data);

            return ApiResponse::success(['announcement' => $announcement], 'Announcement updated.');
        } catch (\Exception $e) {
            \Log::error('Admin\\AnnouncementController::update failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to update announcement.', 500);
        }
    }

    public function destroy(Announcement $announcement)
    {
        try {
            $announcement->delete();

            return ApiResponse::success(null, 'Announcement deleted.');
        } catch (\Exception $e) {
            \Log::error('Admin\\AnnouncementController::destroy failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to delete announcement.', 500);
        }
    }

    public function togglePin(Announcement $announcement)
    {
        try {
            $announcement->is_pinned = !$announcement->is_pinned;
            $announcement->save();

            return ApiResponse::success(
                ['announcement' => $announcement],
                $announcement->is_pinned ? 'Announcement pinned.' : 'Announcement unpinned.'
            );
        } catch (\Exception $e) {
            \Log::error('Admin\\AnnouncementController::togglePin failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to toggle pin.', 500);
        }
    }

    public function toggleStatus(Announcement $announcement)
    {
        try {
            if ($announcement->status === 'published') {
                $announcement->update(['status' => 'draft', 'published_at' => null]);
            } else {
                $announcement->update(['status' => 'published', 'published_at' => now()]);
            }

            return ApiResponse::success(
                ['announcement' => $announcement],
                "Announcement {$announcement->status}."
            );
        } catch (\Exception $e) {
            \Log::error('Admin\\AnnouncementController::toggleStatus failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to toggle status.', 500);
        }
    }
}

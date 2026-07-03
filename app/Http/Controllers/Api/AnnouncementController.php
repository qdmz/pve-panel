<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Announcement;

class AnnouncementController extends Controller
{
    public function index()
    {
        try {
            $announcements = Announcement::published()
                                   ->orderBy('is_pinned', 'desc')
                                   ->orderBy('published_at', 'desc')
                                   ->paginate(15);

            return ApiResponse::paginated($announcements, 'Announcements retrieved.');
        } catch (\Exception $e) {
            \Log::error('AnnouncementController::index failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve announcements.', 500);
        }
    }

    public function show(Announcement $announcement)
    {
        try {
            if ($announcement->status !== 'published') {
                return ApiResponse::error('Announcement not found.', 404);
            }

            return ApiResponse::success(['announcement' => $announcement]);
        } catch (\Exception $e) {
            \Log::error('AnnouncementController::show failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve announcement.', 500);
        }
    }
}

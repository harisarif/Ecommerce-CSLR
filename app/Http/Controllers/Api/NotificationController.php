<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Notification;

class NotificationController extends Controller
{
    /**
     * ✅ Get all notifications (with filters)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = Notification::where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Extract sender IDs from notifications
        $senderIds = collect($notifications->items())
            ->pluck('data.sender_id')
            ->filter()
            ->unique()
            ->values();

        // Fetch sender info
        $senders = User::whereIn('id', $senderIds)
            ->get(['id', 'username', 'avatar', 'first_name', 'last_name']);

        // Attach sender info into notification data
        $notifications->getCollection()->transform(function ($notification) use ($senders) {
            $data = $notification->data ?? [];
            if (!empty($data['sender_id'])) {
                $sender = $senders->firstWhere('id', $data['sender_id']);
                if ($sender) {
                    $data['sender'] = [
                        'id' => $sender->id,
                        'username' => $sender->username,
                        'avatar' => $sender->avatar,
                        'full_name' => trim(($sender->first_name ?? '') . ' ' . ($sender->last_name ?? '')),
                    ];
                }
            }
            $notification->data = $data;
            return $notification;
        });

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    /**
     * ✅ Get only unread notifications
     */
    public function unread(Request $request)
    {
        $user = $request->user();

        $notifications = Notification::where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    /**
     * ✅ Mark single notification as read
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();

        $notification = Notification::where('id', $id)
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->firstOrFail();

        $notification->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    /**
     * ✅ Mark all or selected notifications as read
     */
    public function markMultipleAsRead(Request $request)
    {
        $user = $request->user();
        $ids = $request->input('ids', []);

        $query = Notification::where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id);

        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        }

        $updated = $query->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Notifications marked as read',
            'count' => $updated,
        ]);
    }

    /**
     * ✅ Delete single notification
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $notification = Notification::where('id', $id)
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->firstOrFail();

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted',
        ]);
    }

    /**
     * ✅ Bulk delete
     */
    public function destroyMultiple(Request $request)
    {
        $user = $request->user();
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'No IDs provided',
            ], 400);
        }

        $deleted = Notification::where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->whereIn('id', $ids)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Deleted {$deleted} notifications",
        ]);
    }

    /**
     * ✅ Clear all notifications
     */
    public function clearAll(Request $request)
    {
        $user = $request->user();

        $count = Notification::where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Cleared all ({$count}) notifications",
        ]);
    }
}

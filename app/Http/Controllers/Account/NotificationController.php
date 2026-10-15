<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * In-app (database) notifications for the bell icon. Notifications are created by later phases
 * (e.g. the daily reminders command); data keys used here: `message`, optional `url`, optional `icon`.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.notifications', [
            'notifications' => $request->user()->notifications()->paginate(25),
        ]);
    }

    /** Mark one notification read and go to its link (or back to the list). */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        return $url && str_starts_with($url, url('/'))
            ? redirect()->to($url)
            : redirect()->route('notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', __('All notifications marked as read.'));
    }
}

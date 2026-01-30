<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PropertyMessage;
use Illuminate\Support\Facades\Auth;

class PropertyMessageController extends Controller
{
    /**
     * Display a listing of messages for properties owned by the user.
     */
    public function index()
    {
        $messages = PropertyMessage::whereHas('propertyListing', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->with(['propertyListing', 'user'])
            ->orderBy('is_read', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $unreadCount = PropertyMessage::whereHas('propertyListing', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->where('is_read', false)
            ->count();

        return view('theme::pages.dashboard.messages.index', compact('messages', 'unreadCount'));
    }

    /**
     * Display the specified message.
     */
    public function show($id)
    {
        $message = PropertyMessage::whereHas('propertyListing', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->with(['propertyListing', 'user'])
            ->findOrFail($id);

        // Mark as read
        if (!$message->is_read) {
            $message->update(['is_read' => true]);
        }

        return view('theme::pages.dashboard.messages.show', compact('message'));
    }

    /**
     * Mark a message as read.
     */
    public function markAsRead($id)
    {
        $message = PropertyMessage::whereHas('propertyListing', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->findOrFail($id);

        $message->update(['is_read' => true]);

        return back()->with('success', __('messages.message_marked_read'));
    }

    /**
     * Mark a message as unread.
     */
    public function markAsUnread($id)
    {
        $message = PropertyMessage::whereHas('propertyListing', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->findOrFail($id);

        $message->update(['is_read' => false]);

        return back()->with('success', __('messages.message_marked_unread'));
    }

    /**
     * Delete the specified message.
     */
    public function destroy($id)
    {
        $message = PropertyMessage::whereHas('propertyListing', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->findOrFail($id);

        $message->delete();

        return redirect()->route('dashboard.messages.index')->with('success', __('messages.message_deleted'));
    }
}

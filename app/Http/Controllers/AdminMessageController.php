<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\AdminMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMessageController extends Controller
{
    public function index()
    {
        $messages = AdminMessage::with('user')->latest()->get();

        return view('admin.messages.index', compact('messages'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:140'],
        ]);

        $message = AdminMessage::create([
            'user_id' => Auth::id(),
            'message' => $validated['message'],
        ]);

        Activity::create([
            'user_id' => Auth::id(),
            'subject_type' => AdminMessage::class,
            'subject_id' => $message->id,
            'action' => 'admin_message',
        ]);

        return redirect()->route('admin.messages.index')->with('status', 'Nachricht erstellt.');
    }

    public function destroy(AdminMessage $message)
    {
        $message->activity?->delete();
        $message->delete();

        return back()->with('status', 'Nachricht gelöscht.');
    }
}

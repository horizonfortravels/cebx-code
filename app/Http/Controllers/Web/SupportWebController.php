<?php

namespace App\Http\Controllers\Web;

use App\Models\{SupportTicket, TicketReply};
use Illuminate\Http\Request;

class SupportWebController extends WebController
{
    public function index()
    {
        $query = SupportTicket::with('user');
        if (!$this->isAdmin()) {
            $query->where('account_id', auth()->user()->account_id);
        } else {
            $query->with('account');
        }

        $tickets = $query->latest()->paginate(15);

        $statsQ = fn() => $this->isAdmin() ? SupportTicket::query() : SupportTicket::where('account_id', auth()->user()->account_id);
        $openCount     = $statsQ()->where('status', 'open')->count();
        $resolvedCount = $statsQ()->where('status', 'resolved')->count();

        return view('pages.support.index', compact('tickets', 'openCount', 'resolvedCount'));
    }

    public function store(Request $request)
    {
        $v = $request->validate(['subject' => 'required|string', 'message' => 'required|string', 'priority' => 'nullable|string']);
        $ticket = SupportTicket::create(array_merge($v, [
            'account_id' => auth()->user()->account_id,
            'user_id'    => auth()->id(),
            'status'     => 'open',
        ]));
        TicketReply::create([
            'ticket_id' => $ticket->id, 'user_id' => auth()->id(),
            'message' => $v['message'], 'is_agent' => false,
        ]);
        return redirect()->route('support.show', $ticket)->with('success', 'تم إنشاء التذكرة');
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->load('replies.user', 'account');
        return view('pages.support.show', compact('ticket'));
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $v = $request->validate(['message' => 'required|string']);
        $isAgent = $this->isAdmin();
        TicketReply::create([
            'ticket_id' => $ticket->id, 'user_id' => auth()->id(),
            'message' => $v['message'], 'is_agent' => $isAgent,
        ]);
        if ($ticket->status === 'resolved') $ticket->update(['status' => 'open']);
        return back()->with('success', 'تم إرسال الرد');
    }

    public function resolve(SupportTicket $ticket)
    {
        $ticket->update(['status' => 'resolved', 'resolved_at' => now()]);
        return back()->with('success', 'تم حل التذكرة');
    }
}

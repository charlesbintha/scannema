<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ScannerSession;
use App\Models\Event;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WebAdminController extends Controller
{
    public function login(Request $request)
    {
        $request->validate(['email' => 'required|string|max:190', 'password' => 'required|string|max:256']);
        $response = app(AuthController::class)->login($request);
        $data = $response->getData(true);
        if ($response->getStatusCode() !== 200) {
            return back()->withErrors(['email' => $data['error'] ?? 'Connexion impossible.'])->withInput($request->only('email'));
        }
        $previous = $request->session()->get('scanner_token');
        if ($previous) {
            DB::table('auth_sessions')->where('token_hash', hash('sha256', $previous))->delete();
        }
        $request->session()->regenerate();
        $request->session()->put('scanner_token', $data['session']['accessToken']);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        DB::table('auth_sessions')->where('token_hash', hash('sha256', $request->session()->get('scanner_token')))->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function dashboard(Request $request)
    {
        $request->validate([
            'event' => 'nullable|integer|min:1', 'q' => 'nullable|string|max:100',
            'payment' => ['nullable', Rule::in(['PAID', 'UNPAID'])],
            'status' => ['nullable', Rule::in(['NOT_SCANNED', 'SCANNED', 'BLOCKED', 'CANCELLED'])],
        ]);
        $events = ScannerSession::events($request->user())->withCount('invitations')->latest('id')->get();
        $event = $request->filled('event') ? $events->firstWhere('id', (int) $request->event) : $events->first();
        if ($request->filled('event')) {
            abort_unless($event, 403);
        }
        $tickets = null;
        $stats = ['total' => 0, 'paid' => 0, 'unpaid' => 0, 'scanned' => 0];
        $logs = collect();
        if ($event) {
            $base = Invitation::where('event_id', $event->id);
            $stats['total'] = (clone $base)->count();
            $stats['paid'] = (clone $base)->where('payment_status', 'PAID')->count();
            $stats['unpaid'] = $stats['total'] - $stats['paid'];
            $stats['scanned'] = (clone $base)->where('status', 'SCANNED')->count();
            $tickets = $base->when($request->filled('q'), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('code', 'like', '%'.$request->q.'%')->orWhere('guest_name', 'like', '%'.$request->q.'%');
                });
            })->when($request->filled('payment'), fn ($q) => $q->where('payment_status', $request->payment))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
                ->orderBy('ticket_number')->paginate(25)->withQueryString();
            $logs = $event->scanLogs()->with(['invitation', 'user'])->latest('id')->limit(10)->get();
        }

        return view('admin.dashboard', compact('events', 'event', 'tickets', 'stats', 'logs'));
    }

    public function eventForm(Request $request, $eventId = null)
    {
        abort_unless(in_array($request->user()->role, ['SUPER_ADMIN', 'MANAGER']), 403);
        $event = $eventId ? Event::findOrFail($eventId) : null;

        return view('admin.event', compact('event'));
    }

    public function saveEvent(Request $request, $eventId = null)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:100', Rule::unique('events', 'code')->ignore($eventId)],
            'status' => 'required|in:DRAFT,LIVE,ENDED,CANCELLED',
            'timezone' => 'required|timezone', 'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'expected_guests' => 'required|integer|min:0|max:10000000',
        ]);
        foreach (['starts_at', 'ends_at'] as $field) {
            $data[$field] = ! empty($data[$field])
                ? Carbon::parse($data[$field], $data['timezone'])->utc()->format('Y-m-d H:i:s')
                : null;
        }
        $id = DB::transaction(function () use ($request, $data, $eventId) {
            $event = $eventId ? Event::findOrFail($eventId) : new Event;
            $event->fill($data);
            $event->expected_guests = $data['expected_guests'];
            if (! $eventId) {
                $event->organization_id = $request->user()->organization_id;
            }
            $event->save();
            if (! $eventId) {
                DB::table('event_users')->insert(['event_id' => $event->id, 'user_id' => $request->user()->id]);
            }

            return $event->id;
        });

        return redirect()->route('dashboard', ['event' => $id])->with('success', 'Événement enregistré.');
    }

    public function pay(Request $request, $eventId)
    {
        $request->validate(['confirm' => 'accepted']);
        $response = app(PaymentController::class)->update($request, $eventId);

        return redirect()->route('dashboard', ['event' => $eventId])->with('success', $response->getData(true)['updated'].' ticket(s) marqué(s) payé(s).');
    }

    public function import(Request $request, $eventId)
    {
        $response = app(ImportController::class)->import($request, $eventId);
        $data = $response->getData(true);
        if ($response->getStatusCode() !== 200) {
            return back()->withErrors(['file' => $data['error'] ?? 'Import impossible.']);
        }

        return redirect()->route('dashboard', ['event' => $eventId])->with('import', $data);
    }

    public function agents(Request $request)
    {
        $data = app(AgentController::class)->index($request)->getData(true);

        return view('admin.agents', $data);
    }

    public function saveAgent(Request $request)
    {
        $request->merge(['eventIds' => $request->input('eventIds', []), 'isActive' => $request->boolean('isActive')]);
        app(AgentController::class)->save($request);

        return redirect()->route('web.agents')->with('success', 'Agent enregistré. Ses anciennes sessions sont révoquées.');
    }
}

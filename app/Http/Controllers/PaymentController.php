<?php
namespace App\Http\Controllers;

use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function update(Request $request, $eventId)
    {
        $data = $request->validate(['ticketIds' => 'required|array|min:1|max:1000', 'ticketIds.*' => 'required|integer|min:1|distinct']);
        $ids = $data['ticketIds'];
        sort($ids);
        return DB::transaction(function () use ($request, $eventId, $ids) {
            $tickets = Invitation::where('event_id', $eventId)->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            abort_if($tickets->count() !== count($ids), 422, 'Selection invalide. Aucun changement effectue.');
            $updated = 0;
            foreach ($tickets as $ticket) {
                if ($ticket->payment_status === 'PAID') continue;
                $ticket->update(['payment_status' => 'PAID', 'paid_at' => now(), 'paid_by_user_id' => $request->user()->id]);
                DB::table('payment_audits')->insert(['event_id' => $eventId, 'invitation_id' => $ticket->id, 'user_id' => $request->user()->id, 'created_at' => now()]);
                $updated++;
            }
            return response()->json(['ok' => true, 'updated' => $updated]);
        });
    }
}

<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AgentController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->role === 'SUPER_ADMIN', 403);
        $org = $request->user()->organization_id;
        $events = DB::table('events')->where('organization_id', $org)->select('id', 'name')->orderBy('name')->get();
        $agents = DB::table('users')->where('organization_id', $org)->where('role', 'CHECKER')->select('id', 'name as fullName', 'email', 'is_active as isActive')->get();
        $links = DB::table('event_users')->whereIn('event_id', $events->pluck('id'))->get();
        return response()->json(['events' => $events, 'agents' => $agents->map(function ($agent) use ($links) {
            $agent->isActive = (bool)$agent->isActive;
            $agent->eventIds = $links->where('user_id', $agent->id)->pluck('event_id')->values();
            return $agent;
        })]);
    }

    public function save(Request $request)
    {
        abort_unless($request->user()->role === 'SUPER_ADMIN', 403);
        $creating = $request->isMethod('POST');
        $org = $request->user()->organization_id;
        $data = $request->validate([
            'id' => $creating ? 'nullable' : 'required|integer|min:1',
            'fullName' => 'required|string|max:120',
            'email' => $creating ? ['required', 'email', 'max:190', Rule::unique('users', 'email')] : 'nullable',
            'password' => $creating ? 'required|string|min:12|max:72' : 'nullable',
            'isActive' => 'required|boolean',
            'eventIds' => 'present|array|max:1000',
            'eventIds.*' => 'integer|min:1|distinct',
        ]);
        if ($creating) abort_if(strlen($data['password']) > 72, 422, 'Mot de passe trop long.');
        return DB::transaction(function () use ($data, $org, $creating) {
            $events = DB::table('events')->where('organization_id', $org)->whereIn('id', $data['eventIds'])->orderBy('id')->lockForUpdate()->get();
            abort_unless($events->count() === count($data['eventIds']), 403, 'Evenement non autorise.');
            if ($creating) {
                $id = DB::table('users')->insertGetId(['organization_id' => $org, 'name' => trim($data['fullName']), 'email' => strtolower(trim($data['email'])), 'password_hash' => Hash::make($data['password']), 'role' => 'CHECKER', 'is_active' => $data['isActive'], 'created_at' => now(), 'updated_at' => now()]);
            } else {
                $id = $data['id'];
                $agent = DB::table('users')->where('id', $id)->where('organization_id', $org)->where('role', 'CHECKER')->lockForUpdate()->first();
                abort_unless($agent, 403, 'Agent non autorise.');
                DB::table('users')->where('id', $id)->update(['name' => trim($data['fullName']), 'is_active' => $data['isActive'], 'updated_at' => now()]);
            }
            DB::table('event_users')->where('user_id', $id)->delete();
            foreach ($data['eventIds'] as $eventId) DB::table('event_users')->insert(['event_id' => $eventId, 'user_id' => $id]);
            DB::table('auth_sessions')->where('user_id', $id)->delete();
            return response()->json(['ok' => true, 'agentId' => $id], $creating ? 201 : 200);
        });
    }
}

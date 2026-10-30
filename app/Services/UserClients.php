<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

/**
 * Mdl_user_clients::get_users_all_clients() and set_all_clients_user(): gives every user who
 * sees all clients an assignment to each active client they are not assigned yet.
 */
final class UserClients
{
    public function assignAllClients(): void
    {
        foreach (DB::table('ip_users')->where('user_all_clients', 1)->orderBy('user_name')->pluck('user_id') as $userId) {
            // Assignments to a client that no longer exists do not count (the inner join to ip_clients).
            $assigned = DB::table('ip_user_clients')
                ->join('ip_users', 'ip_users.user_id', '=', 'ip_user_clients.user_id')
                ->join('ip_clients', 'ip_clients.client_id', '=', 'ip_user_clients.client_id')
                ->where('ip_user_clients.user_id', $userId)
                ->pluck('ip_user_clients.client_id')
                ->all();

            $clients = Client::query()->listing()->where('client_active', 1);

            if ($assigned !== []) {
                $clients->whereNotIn('ip_clients.client_id', $assigned);
            }

            foreach ($clients->pluck('client_id') as $clientId) {
                DB::table('ip_user_clients')->insert(['user_id' => $userId, 'client_id' => $clientId]);
            }
        }
    }
}

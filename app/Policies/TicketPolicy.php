<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy {
    /**
     * Memastikan hanya pemilik tiket atau Admin yang dapat melihat detail percakapan.
     */
    public function view(User $user, Ticket $ticket): bool {
        return $user->id === $ticket->user_id || $user->isAdmin();
    }

    /**
     * Memastikan hanya pemilik tiket atau Admin yang dapat membalas pesan.
     */
    public function reply(User $user, Ticket $ticket): bool {
        return ($user->id === $ticket->user_id && $ticket->status !== 'closed') || $user->isAdmin();
    }
}
<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bersihkan notifikasi kolaborasi yang sudah YATIM — kolaborasinya sudah tidak ada lagi
 * (mis. kolaborator sudah membatalkan/menolak sebelum perbaikan ini dibuat), tapi notifikasi
 * undangan / "diterima"-nya masih menggantung.
 *
 *   php artisan collab:cleanup-notifications --scan   → hanya menghitung, tidak menghapus
 *   php artisan collab:cleanup-notifications          → hapus
 *
 * Aman & idempoten: HANYA menghapus notifikasi yang barisnya di post_collaborators sudah
 * tidak ada. Undangan yang masih menunggu & kolaborasi aktif tidak tersentuh.
 */
class CollabNotificationsCleanup extends Command
{
    protected $signature = 'collab:cleanup-notifications {--scan : Hanya hitung, tidak menghapus}';

    protected $description = 'Hapus notifikasi kolaborasi yang kolaborasinya sudah tidak ada.';

    public function handle(): int
    {
        $scan = (bool) $this->option('scan');

        // 1) Undangan (collab_invite) → yatim bila TIDAK ada baris kolaborasi apa pun untuk
        //    (penerima notif = calon kolaborator, post terkait).
        $inviteQuery = Notification::where('type', 'collab_invite')
            ->whereNotNull('related_post_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('post_collaborators as pc')
                    ->whereColumn('pc.post_id', 'notifications.related_post_id')
                    ->whereColumn('pc.user_id', 'notifications.recipient_id');
            });

        // 2) "Diterima" (collab_accepted) → yatim bila TIDAK ada baris kolaborasi yang
        //    diterima untuk (pengirim = kolaborator, post terkait).
        $acceptedQuery = Notification::where('type', 'collab_accepted')
            ->whereNotNull('related_post_id')
            ->whereNotNull('related_user_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('post_collaborators as pc')
                    ->whereColumn('pc.post_id', 'notifications.related_post_id')
                    ->whereColumn('pc.user_id', 'notifications.related_user_id')
                    ->where('pc.status', 'accepted');
            });

        $invites = (clone $inviteQuery)->count();
        $accepted = (clone $acceptedQuery)->count();

        $this->line('');
        $this->info('== Notifikasi kolaborasi yatim ==');
        $this->line("  Undangan (collab_invite)     : {$invites}");
        $this->line("  Diterima (collab_accepted)   : {$accepted}");
        $this->line('  Total                        : '.($invites + $accepted));

        if ($scan) {
            $this->line('');
            $this->info('Mode scan — tidak ada yang dihapus. Jalankan tanpa --scan untuk menghapus.');

            return self::SUCCESS;
        }

        if ($invites + $accepted === 0) {
            $this->line('');
            $this->info('Tidak ada yang perlu dibersihkan.');

            return self::SUCCESS;
        }

        $delInvite = $inviteQuery->delete();
        $delAccepted = $acceptedQuery->delete();

        $this->line('');
        $this->info('== Selesai ==');
        $this->line("  Undangan dihapus  : {$delInvite}");
        $this->line("  Diterima dihapus  : {$delAccepted}");

        return self::SUCCESS;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\MailAccount;
use App\Models\MailSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MailAdminController extends Controller
{
    /** Status gate master password. */
    public function settings()
    {
        $s = MailSetting::current();

        return response()->json([
            'gate_enabled' => $s->gate_enabled,
            'has_master_password' => $s->hasMasterPassword(),
            'accounts_count' => MailAccount::count(),
        ]);
    }

    /** Ubah enable/disable gate & (opsional) master password. */
    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'gate_enabled' => ['required', 'boolean'],
            'master_password' => ['nullable', 'string', 'min:4', 'max:100'],
            'clear_master_password' => ['sometimes', 'boolean'],
        ]);

        $s = MailSetting::current();
        $s->gate_enabled = $data['gate_enabled'];

        if (! empty($data['clear_master_password'])) {
            $s->master_password = null;
        } elseif (! empty($data['master_password'])) {
            $s->master_password = Hash::make($data['master_password']);
        }

        $s->save();

        return response()->json([
            'gate_enabled' => $s->gate_enabled,
            'has_master_password' => $s->hasMasterPassword(),
        ]);
    }

    /** Daftar akun email yang sudah dibuat user. */
    public function accounts(Request $request)
    {
        $accounts = MailAccount::query()
            ->with('user')
            ->latest()
            ->paginate((int) $request->integer('per_page', 30));

        $accounts->getCollection()->transform(fn (MailAccount $a) => [
            'id' => $a->id,
            'email' => $a->email,
            'created_at' => $a->created_at,
            'user' => $a->user ? [
                'id' => $a->user->getKey(),
                'name' => $a->user->full_name ?? $a->user->username,
                'username' => $a->user->username,
                'account_email' => $a->user->email,
            ] : null,
        ]);

        return response()->json($accounts);
    }
}

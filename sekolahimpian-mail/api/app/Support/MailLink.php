<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

/**
 * Pusat pembuatan tautan + pengiriman email transaksional Sekolah Impian Mail.
 * Semua email dikirim via Mail::html (tanpa file view) agar ringkas & portable.
 */
class MailLink
{
    public static function frontend(): string
    {
        // Jangan pakai env() di sini: setelah config:cache, env() runtime = null.
        return rtrim((string) (config('app.frontend_url') ?: 'https://mail.sekolahimpian.com'), '/');
    }

    /** Tautan verifikasi email pendaftaran (signed, 48 jam). */
    public static function verifyUrl(User $user): string
    {
        return URL::temporarySignedRoute('verify.email', now()->addHours(48), [
            'id' => $user->getKey(),
            'hash' => sha1($user->email),
        ]);
    }

    /** Tautan konfirmasi ganti email (signed, 1 jam) — dibuka dari email LAMA. */
    public static function emailChangeUrl(User $user): string
    {
        return URL::temporarySignedRoute('email.change', now()->addHour(), [
            'id' => $user->getKey(),
            't' => $user->email_change_token,
        ]);
    }

    public static function sendVerification(User $user): void
    {
        $url = self::verifyUrl($user);
        self::deliver(
            $user->email,
            'Verifikasi email — Sekolah Impian Mail',
            self::wrap(
                'Verifikasi email kamu',
                '<p>Halo <b>' . e($user->full_name) . '</b>,</p>'
                . '<p>Terima kasih sudah mendaftar di <b>SI Mail</b>. Klik tombol di bawah untuk memverifikasi alamat email ini. '
                . 'Kamu belum bisa masuk sebelum email diverifikasi.</p>',
                'Verifikasi Email',
                $url,
                'Tautan berlaku 48 jam. Abaikan email ini jika kamu tidak mendaftar.'
            )
        );
    }

    public static function sendPasswordReset(User $user): void
    {
        $token = Password::broker()->createToken($user);
        $url = self::frontend() . '/reset?token=' . $token . '&email=' . urlencode($user->email);
        self::deliver(
            $user->email,
            'Ganti kata sandi — Sekolah Impian Mail',
            self::wrap(
                'Setel ulang kata sandi',
                '<p>Halo <b>' . e($user->full_name) . '</b>,</p>'
                . '<p>Kami menerima permintaan untuk mengganti kata sandi akun <b>SI Mail</b> kamu. '
                . 'Klik tombol di bawah untuk menyetel kata sandi baru.</p>'
                . '<p style="color:#a15c00"><b>Catatan:</b> kata sandi ini adalah kata sandi akun SI Mail (untuk masuk & mengelola email), bukan kata sandi mailbox IMAP.</p>',
                'Setel Kata Sandi Baru',
                $url,
                'Tautan berlaku 60 menit. Abaikan email ini jika kamu tidak memintanya.'
            )
        );
    }

    /** Konfirmasi ganti email dikirim ke alamat LAMA. */
    public static function sendEmailChangeConfirm(User $user): void
    {
        $url = self::emailChangeUrl($user);
        self::deliver(
            $user->email,
            'Konfirmasi penggantian email — Sekolah Impian Mail',
            self::wrap(
                'Konfirmasi penggantian email',
                '<p>Halo <b>' . e($user->full_name) . '</b>,</p>'
                . '<p>Ada permintaan untuk mengganti email pemulihan akun <b>SI Mail</b> kamu menjadi:</p>'
                . '<p style="font-size:16px"><b>' . e((string) $user->pending_email) . '</b></p>'
                . '<p>Jika ini kamu, klik tombol di bawah untuk mengonfirmasi. Jika bukan, abaikan email ini dan kata sandi akunmu tetap aman.</p>',
                'Konfirmasi Ganti Email',
                $url,
                'Tautan berlaku 60 menit.'
            )
        );
    }

    /** Pemberitahuan sukses dikirim ke alamat BARU setelah ganti email berhasil. */
    public static function sendEmailChangedNotice(User $user): void
    {
        self::deliver(
            $user->email,
            'Email pemulihan berhasil diganti — Sekolah Impian Mail',
            self::wrap(
                'Email berhasil diganti',
                '<p>Halo <b>' . e($user->full_name) . '</b>,</p>'
                . '<p>Alamat email pemulihan akun <b>SI Mail</b> kamu kini telah diperbarui menjadi alamat ini (<b>' . e($user->email) . '</b>).</p>'
                . '<p>Mulai sekarang, notifikasi keamanan dan tautan reset kata sandi akan dikirim ke alamat ini.</p>',
                null,
                null,
                'Jika kamu tidak melakukan perubahan ini, segera hubungi admin.'
            )
        );
    }

    private static function deliver(string $to, string $subject, string $html): void
    {
        Mail::html($html, function ($m) use ($to, $subject) {
            $m->to($to)->subject($subject);
        });
    }

    private static function wrap(string $title, string $body, ?string $btnLabel, ?string $btnUrl, string $foot = ''): string
    {
        $btn = $btnLabel && $btnUrl
            ? '<tr><td style="padding:8px 0 22px"><a href="' . e($btnUrl) . '" style="display:inline-block;background:#1f6feb;color:#fff;text-decoration:none;font-weight:700;padding:13px 26px;border-radius:12px;font-size:15px">' . e($btnLabel) . '</a></td></tr>'
            . '<tr><td style="padding:0 0 8px;color:#6a6155;font-size:12px;word-break:break-all">Atau salin tautan: ' . e($btnUrl) . '</td></tr>'
            : '';

        $footHtml = $foot ? '<tr><td style="padding:14px 0 0;color:#9b958e;font-size:12px;border-top:1px solid #eee">' . e($foot) . '</td></tr>' : '';

        return '<div style="background:#f3ede3;padding:28px 12px;font-family:Inter,Arial,sans-serif">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;margin:0 auto;background:#fff;border-radius:18px;overflow:hidden;border:1px solid #ececec">'
            . '<tr><td style="background:#10233f;padding:18px 26px;color:#fff;font-size:18px;font-weight:800;letter-spacing:-.02em">SI <span style="color:#ffaf36">Mail</span></td></tr>'
            . '<tr><td style="padding:26px 26px 6px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
            . '<tr><td style="font-size:20px;font-weight:800;color:#23201d;padding-bottom:6px">' . e($title) . '</td></tr>'
            . '<tr><td style="color:#3d352a;font-size:14px;line-height:1.6">' . $body . '</td></tr>'
            . $btn . $footHtml
            . '</table></td></tr>'
            . '<tr><td style="padding:16px 26px;background:#faf7f2;color:#9b958e;font-size:12px">Sekolah Impian Mail · mail.sekolahimpian.com</td></tr>'
            . '</table></div>';
    }
}

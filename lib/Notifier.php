<?php
/**
 * Notifikasi akun Mini POS ke user toko (T-43). LMD mengurus notifikasi komersial (pesanan, pembayaran,
 * lisensi); aplikasi client mengurus akun di dalam aplikasinya. Gagal kirim tidak pernah menggagalkan aksi user.
 * Kanal 'mailbox' (default): disimpan di tabel notifications dan dibaca admin toko di menu Notifikasi.
 */
class Notifier {
    public static function send(array $tenant, $event, $recipient, $recipientName, array $vars = []) {
        try {
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                return null;
            }
            $vars += ['name' => $recipientName ?: 'Pengguna', 'store' => $tenant['store_name'] ?: $tenant['domain'], 'app_url' => self::appUrl($tenant)];
            [$subject, $lines] = self::template($event, $vars);
            $body = implode("\n", array_merge(["Halo {$vars['name']},", ''], $lines, [
                '', '—', "Mini POS Express · {$vars['store']}", 'Email otomatis, mohon tidak membalas.',
            ]));
            $channel = app_config('notify')['channel'] ?? 'mailbox';
            $error = $channel === 'mailbox' ? null : "Kanal '{$channel}' belum tersedia (📋 rencana). Notifikasi tersimpan di kotak surat.";
            $stmt = db()->prepare("
                INSERT INTO notifications (tenant_id, event, channel, recipient, recipient_name, subject, body, status, last_error)
                VALUES (:tenant_id, :event, :channel, :recipient, :name, :subject, :body, :status, :error)
            ");
            $stmt->execute([
                'tenant_id' => $tenant['id'], 'event' => $event, 'channel' => $channel,
                'recipient' => strtolower($recipient), 'name' => $recipientName,
                'subject' => mb_substr($subject, 0, 200), 'body' => $body,
                'status' => $error ? 'failed' : 'sent', 'error' => $error,
            ]);
            return (int) db()->lastInsertId();
        } catch (Throwable $e) {
            error_log('[mini-pos notifier] ' . $event . ': ' . $e->getMessage());
            return null;
        }
    }

    private static function appUrl(array $tenant) {
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/mini-pos/index.php')), '/');
        return base_host_url() . $dir . '/?tenant=' . rawurlencode($tenant['domain']);
    }

    private static function template($event, array $v) {
        $when = date('d M Y H:i');
        switch ($event) {
            case 'admin_created':
                return ["Akun admin {$v['store']} berhasil dibuat", [
                    "Akun admin Mini POS untuk toko {$v['store']} sudah dibuat dengan email ini ({$when}).",
                    "Masuk di: {$v['app_url']}",
                    'Tambahkan kasir di menu Kelola User. Info langganan & bukti pembayaran ada di menu Langganan.',
                ]];
            case 'user_created':
                return ["Anda ditambahkan ke Mini POS {$v['store']}", [
                    "{$v['by']} menambahkan Anda sebagai {$v['role']} di toko {$v['store']}.",
                    "Masuk di: {$v['app_url']}",
                    'Password awal diberikan langsung oleh admin toko. Segera minta admin menggantinya bila perlu.',
                ]];
            case 'password_changed':
                return ["Password akun Mini POS Anda diganti", [
                    "Password akun Anda di toko {$v['store']} diganti pada {$when} oleh {$v['by']}.",
                    'Bila Anda tidak merasa melakukannya, segera hubungi admin toko.',
                ]];
            case 'user_deactivated':
                return ["Akun Mini POS Anda dinonaktifkan", [
                    "Akun Anda di toko {$v['store']} dinonaktifkan oleh {$v['by']} ({$when}). Anda tidak bisa masuk sampai diaktifkan kembali.",
                ]];
            case 'user_activated':
                return ["Akun Mini POS Anda aktif kembali", [
                    "Akun Anda di toko {$v['store']} diaktifkan kembali oleh {$v['by']} ({$when}).",
                    "Masuk di: {$v['app_url']}",
                ]];
            case 'account_locked':
                return ["Peringatan keamanan: akun dikunci sementara", [
                    "Ada {$v['attempts']} percobaan masuk gagal ke akun Anda di toko {$v['store']}. Akun dikunci {$v['minutes']} menit ({$when}).",
                    'Bila itu bukan Anda, minta admin toko mengganti password Anda.',
                ]];
        }
        throw new InvalidArgumentException("Unknown notification event '{$event}'.");
    }
}

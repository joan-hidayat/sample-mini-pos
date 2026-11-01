<?php
/**
 * Reset password admin toko memakai link sekali pakai yang diterbitkan Central SaaS LMD (T-21).
 *
 * Alur: link ?tenant=…&reset_token=… → token disimpan di sesi & dihapus dari URL → token diperiksa ke LMD
 * → admin memilih akun & password baru → token dihanguskan di LMD → password disimpan.
 */
require_once __DIR__ . '/lib/bootstrap.php';

[$tenant, $license] = boot_tenant();
$gate = new LicenseGate(db(), app_config());
$users = new UserRepository(db());
$tenantId = (int) $tenant['id'];

// Token tidak dibiarkan di address bar / riwayat browser
if (!empty($_GET['reset_token'])) {
    $_SESSION['reset_token'] = ['tenant_id' => $tenantId, 'token' => (string) $_GET['reset_token']];
    redirect(page_url('reset.php'));
}

$token = ($_SESSION['reset_token']['tenant_id'] ?? null) === $tenantId ? $_SESSION['reset_token']['token'] : null;

$reasonMessages = [
    'reset_token_invalid' => 'Link reset tidak dikenal untuk toko ini. Pastikan Anda membuka link terbaru dari penyedia layanan.',
    'reset_token_used' => 'Link reset ini sudah dipakai. Minta link baru bila masih perlu mengganti password.',
    'reset_token_expired' => 'Link reset sudah kedaluwarsa (berlaku 24 jam). Minta link baru ke penyedia layanan.',
    'license_inactive' => 'Lisensi toko ini sedang tidak aktif.',
    'unreachable' => 'Server lisensi Central SaaS tidak dapat dihubungi. Coba lagi beberapa saat lagi.',
];

$denyToken = function ($reason) use ($reasonMessages) {
    unset($_SESSION['reset_token']);
    http_response_code(403);
    render('error', ['title' => 'Link Reset Tidak Berlaku', 'message' => $reasonMessages[$reason] ?? 'Link reset tidak berlaku.']);
    exit;
};

if (!$token) {
    $denyToken('reset_token_invalid');
}

$admins = $users->listActiveAdmins($tenantId);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $adminId = (int) ($_POST['user_id'] ?? 0);
    $password = (string) ($_POST['password'] ?? '');
    $target = null;
    foreach ($admins as $admin) {
        if ((int) $admin['id'] === $adminId) {
            $target = $admin;
        }
    }

    try {
        if (!$target) {
            throw new InvalidArgumentException('Pilih akun admin yang akan direset.');
        }
        if (!hash_equals($password, (string) ($_POST['password_confirm'] ?? ''))) {
            throw new InvalidArgumentException('Konfirmasi password tidak sama.');
        }
        // Validasi lokal dulu agar token tidak hangus karena salah ketik
        $users->checkPassword($password);

        $result = $gate->checkResetToken($tenant, $token, true);
        if (!$result['valid']) {
            $denyToken($result['reason']);
        }
        $users->resetPassword($target['id'], $tenantId, $password);
        Notifier::send($tenant, 'password_changed', $target['email'], $target['name'], ['by' => 'link reset dari penyedia layanan (Central SaaS)']);
        unset($_SESSION['reset_token']);
        logout_user();
        flash('success', "Password {$target['email']} berhasil diganti. Silakan masuk dengan password baru.");
        redirect(page_url('login.php'));
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
} else {
    $result = $gate->checkResetToken($tenant, $token, false);
    if (!$result['valid']) {
        $denyToken($result['reason']);
    }
}

render('reset', ['license' => $license, 'admins' => $admins, 'error' => $error]);

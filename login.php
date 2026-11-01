<?php
require_once __DIR__ . '/lib/bootstrap.php';

[$tenant, $license] = boot_tenant();
$users = new UserRepository(db());
$tenantId = (int) $tenant['id'];

if ($users->countAll($tenantId) === 0) {
    redirect(page_url('setup.php'));
}
if (current_user($tenant)) {
    redirect(page_url('index.php'));
}

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $attempt = $users->attemptLogin(
        $email,
        (string) ($_POST['password'] ?? ''),
        $tenantId,
        (int) app_config('login_max_attempts'),
        (int) app_config('login_lock_minutes')
    );
    [$user, $error] = $attempt;
    $lockedUser = $attempt[2] ?? null;

    if (!empty($lockedUser)) {
        Notifier::send($tenant, 'account_locked', $lockedUser['email'], $lockedUser['name'], [
            'attempts' => (int) app_config('login_max_attempts'), 'minutes' => (int) app_config('login_lock_minutes'),
        ]);
    }

    // Kuota turun (misal paket di-downgrade): hanya admin yang boleh masuk untuk merapikan user
    if ($user && $user['role'] !== 'admin' && $users->countActive($tenantId) > $license['max_users']) {
        $user = null;
        $error = "Jumlah user aktif melebihi kuota lisensi ({$license['max_users']} user). Hubungi admin toko.";
    }

    if ($user) {
        login_user($user);
        redirect(page_url('index.php'));
    }
}

render('login', ['license' => $license, 'email' => $email, 'error' => $error]);

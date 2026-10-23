<?php
/**
 * Kelola user toko (khusus admin). Jumlah user aktif dibatasi max_users dari lisensi LMD.
 */
require_once __DIR__ . '/lib/bootstrap.php';

[$tenant, $license] = boot_tenant();
$user = require_login($tenant, $license);
if ($user['role'] !== 'admin') {
    flash('error', 'Halaman Kelola User hanya untuk admin.');
    redirect(page_url('index.php'));
}

$repo = new UserRepository(db());
$tenantId = (int) $tenant['id'];
$maxUsers = $license['max_users'];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $targetId = (int) ($_POST['user_id'] ?? 0);
    $target = $targetId ? $repo->findById($targetId, $tenantId) : null;

    try {
        if ($action === 'create') {
            $old = ['name' => $_POST['name'] ?? '', 'email' => $_POST['email'] ?? '', 'role' => $_POST['role'] ?? 'kasir'];
            $created = $repo->create($tenantId, $old['name'], $old['email'], (string) ($_POST['password'] ?? ''), $old['role'], $maxUsers);
            flash('success', "User {$created['email']} berhasil ditambahkan.");
            redirect(page_url('users.php'));
        }

        if (!$target) {
            throw new InvalidArgumentException('User tidak ditemukan.');
        }
        if ((int) $target['id'] === (int) $user['id'] && $action !== 'reset_password') {
            throw new InvalidArgumentException('Anda tidak dapat menonaktifkan akun sendiri.');
        }

        if ($action === 'deactivate') {
            $repo->setStatus($target['id'], $tenantId, 'inactive', $maxUsers);
            flash('success', "User {$target['email']} dinonaktifkan.");
        } elseif ($action === 'activate') {
            $repo->setStatus($target['id'], $tenantId, 'active', $maxUsers);
            flash('success', "User {$target['email']} diaktifkan kembali.");
        } elseif ($action === 'reset_password') {
            $repo->resetPassword($target['id'], $tenantId, (string) ($_POST['new_password'] ?? ''));
            flash('success', "Password {$target['email']} berhasil diganti.");
        } else {
            throw new InvalidArgumentException('Aksi tidak dikenal.');
        }
        redirect(page_url('users.php'));
    } catch (InvalidArgumentException $e) {
        flash('error', $e->getMessage());
        if ($action !== 'create') {
            redirect(page_url('users.php'));
        }
    }
}

render('users', [
    'tenant' => $tenant,
    'license' => $license,
    'user' => $user,
    'users' => $repo->listByTenant($tenantId),
    'activeCount' => $repo->countActive($tenantId),
    'old' => $old,
]);

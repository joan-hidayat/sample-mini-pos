<?php
require_once __DIR__ . '/lib/bootstrap.php';

start_app_session();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    logout_user();
    flash('success', 'Anda sudah keluar.');
}
redirect(page_url('login.php'));

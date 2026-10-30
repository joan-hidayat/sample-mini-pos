<?php
/**
 * Multi Outlet (modul MULTI_OUTLET, khusus admin). Penguncian modul sudah berlaku; fiturnya belum dibuat (📋 rencana).
 */
require_once __DIR__ . '/lib/bootstrap.php';

[$tenant, $license] = boot_tenant();
$user = require_login($tenant, $license);
require_module($tenant, $license, $user, 'MULTI_OUTLET');

render('locked', ['tenant' => $tenant, 'license' => $license, 'user' => $user, 'module' => APP_MODULES['MULTI_OUTLET'], 'code' => 'MULTI_OUTLET']);

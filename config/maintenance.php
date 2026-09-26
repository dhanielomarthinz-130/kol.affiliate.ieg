<?php
// config/maintenance.php

define('MAINTENANCE_CONFIG_FILE', __DIR__ . '/maintenance_config.json');

/**
 * Mengambil konfigurasi mode pemeliharaan sistem
 */
function getMaintenanceConfig(): array {
    $defaults = [
        'is_maintenance'   => false,
        'allowed_username' => 'Daniel',
        'allowed_role'     => 'superadmin',
        'message'          => 'Sistem sedang dalam mode pemeliharaan (Maintenance). Hanya Superadmin (Daniel) yang diizinkan mengakses sistem.',
        'updated_at'       => null,
        'updated_by'       => null
    ];

    if (!file_exists(MAINTENANCE_CONFIG_FILE)) {
        return $defaults;
    }

    $raw = @file_get_contents(MAINTENANCE_CONFIG_FILE);
    if (!$raw) return $defaults;

    $json = json_decode($raw, true);
    if (!is_array($json)) return $defaults;

    return array_merge($defaults, $json);
}

/**
 * Memeriksa apakah mode maintenance sedang aktif
 */
function isMaintenanceActive(): bool {
    $cfg = getMaintenanceConfig();
    return !empty($cfg['is_maintenance']);
}

/**
 * Memeriksa apakah pengguna memiliki hak akses untuk melewati mode pemeliharaan
 * Hanya role superadmin dengan username Daniel (case-insensitive) yang diizinkan
 */
function canBypassMaintenance(?array $user = null): bool {
    if (!$user) {
        if (function_exists('getCurrentUser')) {
            $user = getCurrentUser();
        }
    }
    if (!$user) return false;

    $role = strtolower(trim($user['role'] ?? ''));
    $username = strtolower(trim($user['username'] ?? ''));

    return ($role === 'superadmin' && $username === 'daniel');
}

/**
 * Mengubah status mode pemeliharaan sistem
 */
function setMaintenanceMode(bool $active, string $byUsername = 'Daniel'): bool {
    $current = getMaintenanceConfig();
    $current['is_maintenance'] = $active;
    $current['updated_at'] = date('Y-m-d H:i:s');
    $current['updated_by'] = $byUsername;

    return (bool)file_put_contents(MAINTENANCE_CONFIG_FILE, json_encode($current, JSON_PRETTY_PRINT));
}

<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Configuración global de la plataforma (comisión, impuestos, etc.).
 */
final class SettingRepository extends BaseRepository
{
    public function get(string $key, string $default = ''): string
    {
        $row = $this->fetchOne('SELECT setting_value FROM settings WHERE setting_key = ?', [$key]);
        return $row['setting_value'] ?? $default;
    }

    public function float(string $key, float $default = 0.0): float
    {
        return (float) $this->get($key, (string) $default);
    }

    public function all(): array
    {
        return $this->fetchAll('SELECT setting_key, setting_value, description, updated_at FROM settings ORDER BY setting_key');
    }

    public function set(string $key, string $value): void
    {
        $this->execute(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            [$key, $value]
        );
    }
}

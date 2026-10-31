<?php

/**
 * JSON payloads for per-row visit saves (OpenEMR xl/xlt strings).
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Domain;

final class VisitSaveJson
{
    /**
     * @return array{ok: true}
     */
    public static function success(): array
    {
        return ['ok' => true];
    }

    /**
     * @param list<string> $errors English message keys/lines from VisitBoard
     * @return array{ok: false, errors: list<string>}
     */
    public static function failure(array $errors): array
    {
        $translated = [];
        foreach ($errors as $error) {
            $translated[] = self::translate($error);
        }
        return [
            'ok' => false,
            'errors' => $translated,
        ];
    }

    public static function translate(string $message): string
    {
        if (function_exists('xl')) {
            return xl($message);
        }
        return $message;
    }
}

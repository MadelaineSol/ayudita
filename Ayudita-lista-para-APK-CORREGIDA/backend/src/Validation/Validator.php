<?php

declare(strict_types=1);

namespace App\Validation;

use App\Exceptions\ValidationException;

/**
 * Validador declarativo de datos de entrada.
 *
 * Uso:
 *   $data = Validator::validate($input, [
 *       'email'    => 'required|email|max:190',
 *       'password' => 'required|min:8',
 *       'role'     => 'required|in:client,provider',
 *   ]);
 */
final class Validator
{
    /**
     * @return array<string,mixed> Solo los campos declarados (whitelist), saneados.
     */
    public static function validate(array $input, array $rules): array
    {
        $errors = [];
        $clean = [];

        foreach ($rules as $field => $ruleString) {
            $ruleList = explode('|', $ruleString);
            $value = $input[$field] ?? null;
            $isRequired = in_array('required', $ruleList, true);

            if ($value === null || $value === '') {
                if ($isRequired) {
                    $errors[$field][] = 'Este campo es obligatorio';
                }
                continue;
            }

            foreach ($ruleList as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $error = self::check($name, $arg, $value);
                if ($error !== null) {
                    $errors[$field][] = $error;
                }
            }

            if (!isset($errors[$field])) {
                $clean[$field] = is_string($value) ? trim($value) : $value;
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $clean;
    }

    private static function check(string $rule, ?string $arg, mixed $value): ?string
    {
        return match ($rule) {
            'required' => null,
            'email'    => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Debe ser un email válido',
            'min'      => (is_string($value) ? mb_strlen($value) >= (int) $arg : (float) $value >= (float) $arg)
                ? null : "Mínimo: $arg",
            'max'      => (is_string($value) ? mb_strlen($value) <= (int) $arg : (float) $value <= (float) $arg)
                ? null : "Máximo: $arg",
            'numeric'  => is_numeric($value) ? null : 'Debe ser un número',
            'integer'  => filter_var($value, FILTER_VALIDATE_INT) !== false ? null : 'Debe ser un número entero',
            'in'       => in_array((string) $value, explode(',', (string) $arg), true) ? null : 'Valor no permitido',
            'array'    => is_array($value) ? null : 'Debe ser una lista',
            'boolean'  => is_bool($value) || in_array($value, [0, 1, '0', '1'], true) ? null : 'Debe ser verdadero o falso',
            'date'     => strtotime((string) $value) !== false ? null : 'Debe ser una fecha válida',
            'phone'    => preg_match('/^\+?[0-9\s\-]{6,20}$/', (string) $value) ? null : 'Debe ser un teléfono válido',
            default    => null,
        };
    }
}

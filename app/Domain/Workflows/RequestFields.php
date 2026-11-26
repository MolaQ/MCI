<?php

namespace App\Domain\Workflows;

use App\Models\WorkflowTemplate;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Checks the values a request gives for its template's fields and returns
 * them cleaned (only known keys, typed). Errors are keyed "data.<key>".
 */
class RequestFields
{
    /**
     * @param  list<array{key: string, label: string, type: string, required: bool, options?: list<string>}>  $fields
     * @param  array<string, mixed>  $data
     * @return array<string, string|bool|null>
     */
    public static function clean(array $fields, array $data): array
    {
        $out = [];
        $errors = [];
        foreach ($fields as $field) {
            $key = $field['key'];
            $raw = $data[$key] ?? null;
            $label = $field['label'];

            if ($field['type'] === 'checkbox') {
                $value = filter_var($raw, FILTER_VALIDATE_BOOLEAN);
                if ($field['required'] && ! $value) {
                    $errors["data.{$key}"] = __('Zaznacz pole „:field”.', ['field' => $label]);
                }
                $out[$key] = $value;

                continue;
            }

            $value = trim((string) (is_scalar($raw) ? $raw : ''));
            if ($value === '') {
                if ($field['required']) {
                    $errors["data.{$key}"] = __('Pole „:field” jest wymagane.', ['field' => $label]);
                }
                $out[$key] = null;

                continue;
            }

            $error = match ($field['type']) {
                'text' => mb_strlen($value) > 255 ? __('Pole „:field” może mieć najwyżej 255 znaków.', ['field' => $label]) : null,
                'textarea' => mb_strlen($value) > 5000 ? __('Pole „:field” może mieć najwyżej 5000 znaków.', ['field' => $label]) : null,
                'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false ? null : __('Pole „:field” musi być datą.', ['field' => $label]),
                'number' => is_numeric(str_replace(',', '.', $value)) && strlen($value) <= 20 ? null : __('Pole „:field” musi być liczbą.', ['field' => $label]),
                'select' => in_array($value, $field['options'] ?? [], true) ? null : __('Wybierz wartość pola „:field” z listy.', ['field' => $label]),
                default => null,
            };
            if ($error) {
                $errors["data.{$key}"] = $error;
            }
            $out[$key] = $field['type'] === 'number' ? str_replace(',', '.', $value) : $value;
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    /** Human-readable value for the request page. */
    public static function display(array $field, mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => '—',
            $field['type'] === 'checkbox' => $value ? __('Tak') : __('Nie'),
            $field['type'] === 'date' => Carbon::parse((string) $value)->translatedFormat('j F Y'),
            default => (string) $value,
        };
    }

    /** @return list<string> */
    public static function types(): array
    {
        return WorkflowTemplate::FIELD_TYPES;
    }
}

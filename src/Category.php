<?php

namespace ErnestDefoe\Garage;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $icon
 * @property string|null $color
 * @property list<array{key: string, label: string, type: string, options?: list<string>, unit?: string, card?: bool, filter?: bool}>|null $fields
 * @property int $position
 * @property int $build_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Category extends AbstractModel
{
    public const TYPES = ['text', 'number', 'select', 'link'];

    public const MAX_FIELDS = 30;

    protected $table = 'garage_categories';

    public $timestamps = true;

    protected $casts = [
        'fields' => 'array',
        'position' => 'integer',
        'build_count' => 'integer',
    ];

    /**
     * The admin's spec sheet, cleaned: every field gets a unique key, a known
     * type and only the settings that type uses. Unknown input is dropped,
     * not stored.
     *
     * @return list<array<string, mixed>>
     */
    public static function cleanFields(mixed $input): array
    {
        $fields = [];
        $keys = [];

        foreach (array_slice(is_array($input) ? array_values($input) : [], 0, self::MAX_FIELDS) as $field) {
            if (! is_array($field)) {
                continue;
            }

            $label = Str::limit(trim((string) ($field['label'] ?? '')), 60, '');
            if ($label === '') {
                continue;
            }

            $type = in_array($field['type'] ?? null, self::TYPES, true) ? $field['type'] : 'text';
            // A key once given stays, so renaming a field keeps every build's value.
            $key = Str::slug((string) ($field['key'] ?? '') ?: $label, '_') ?: 'field';
            $base = $key = Str::limit($key, 40, '');
            for ($n = 2; isset($keys[$key]); $n++) {
                $key = $base.'_'.$n;
            }
            $keys[$key] = true;

            $clean = ['key' => $key, 'label' => $label, 'type' => $type, 'card' => (bool) ($field['card'] ?? false)];

            if ($type === 'select') {
                $options = array_values(array_unique(array_filter(array_map(
                    fn ($option) => Str::limit(trim((string) $option), 60, ''),
                    array_slice(is_array($field['options'] ?? null) ? $field['options'] : [], 0, 50),
                ), fn ($option) => $option !== '')));
                $clean['options'] = $options;
                $clean['filter'] = (bool) ($field['filter'] ?? false);
            }

            if ($type === 'number') {
                $clean['unit'] = Str::limit(trim((string) ($field['unit'] ?? '')), 12, '');
            }

            $fields[] = $clean;
        }

        return $fields;
    }

    /**
     * A build's spec values, kept only where this category has the field and
     * the value fits it. Empty values are dropped.
     *
     * @return array<string, string|float|int>
     */
    public function cleanSpecs(mixed $input): array
    {
        $specs = [];
        $input = is_array($input) ? $input : [];

        foreach ($this->fields ?? [] as $field) {
            $value = $input[$field['key']] ?? null;
            if (! is_scalar($value) || trim((string) $value) === '') {
                continue;
            }
            $value = trim((string) $value);

            $clean = match ($field['type']) {
                'number' => is_numeric($value) && abs((float) $value) < 1e12 ? $value + 0 : null,
                'select' => in_array($value, $field['options'] ?? [], true) ? $value : null,
                'link' => filter_var($value, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $value) ? Str::limit($value, 300, '') : null,
                default => Str::limit($value, 120, ''),
            };

            if ($clean !== null) {
                $specs[$field['key']] = $clean;
            }
        }

        return $specs;
    }

    /** @return array<string, mixed>|null */
    public function field(string $key): ?array
    {
        foreach ($this->fields ?? [] as $field) {
            if ($field['key'] === $key) {
                return $field;
            }
        }

        return null;
    }
}

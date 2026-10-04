<?php

namespace App\Models;

use App\Enums\FormFieldType;
use Database\Factories\FormFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A Form field addressed by its stable `key`; key and type are immutable once created.
 *
 * @property int $id
 * @property int $form_id
 * @property string $key
 * @property FormFieldType $type
 * @property string $label
 * @property string|null $placeholder
 * @property string|null $default_value
 * @property bool $required
 * @property array{max_length?: int}|null $validation
 * @property list<string>|null $options
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['label', 'placeholder', 'default_value', 'required', 'validation', 'options', 'sort_order'])]
#[Hidden(['id', 'form_id'])]
class FormField extends Model
{
    /** @use HasFactory<FormFieldFactory> */
    use HasFactory;

    public const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,39}$/';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FormFieldType::class,
            'required' => 'boolean',
            'validation' => 'array',
            'options' => 'array',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (FormField $field): void {
            if ($field->exists && $field->isDirty(['form_id', 'key', 'type'])) {
                throw new LogicException('Form field Form, key and type are immutable.');
            }

            if (preg_match(self::KEY_PATTERN, $field->key) !== 1) {
                throw new LogicException('Form field key must be a lowercase machine key.');
            }
        });
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function maxLength(): ?int
    {
        $limit = $this->type->maxLength();
        $custom = $this->validation['max_length'] ?? null;

        return $limit !== null && is_int($custom) ? min($limit, $custom) : $limit;
    }
}

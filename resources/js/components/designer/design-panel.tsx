import { designChoices } from '@/blocks/design';
import type { DesignTokens } from '@/blocks/design';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ChoiceKey = keyof typeof designChoices;

const choiceLabels: Record<ChoiceKey, string> = {
    font_family: 'Шрифт',
    radius: 'Скругление',
    container: 'Ширина контента',
    button_style: 'Стиль кнопок',
};

const colorLabels = {
    primary_color: 'Основной цвет',
    secondary_color: 'Дополнительный цвет',
} as const;

export function DesignPanel({
    tokens,
    errors,
    canEdit,
    dirty,
    saving,
    onChange,
    onSave,
}: {
    tokens: DesignTokens;
    errors: Record<string, string>;
    canEdit: boolean;
    dirty: boolean;
    saving: boolean;
    onChange: (tokens: DesignTokens) => void;
    onSave: () => void;
}) {
    return (
        <fieldset disabled={!canEdit} className="flex flex-col gap-4">
            <legend className="sr-only">Стиль сайта</legend>
            {(Object.keys(colorLabels) as (keyof typeof colorLabels)[]).map(
                (key) => (
                    <div key={key} className="grid gap-1.5">
                        <Label htmlFor={`design-${key}`}>
                            {colorLabels[key]}
                        </Label>
                        <div className="flex items-center gap-2">
                            <input
                                type="color"
                                aria-label={`${colorLabels[key]}: палитра`}
                                value={tokens[key]}
                                onChange={(event) =>
                                    onChange({
                                        ...tokens,
                                        [key]: event.target.value,
                                    })
                                }
                                className="h-9 w-12 shrink-0 cursor-pointer rounded-md border border-input bg-transparent p-1"
                            />
                            <Input
                                id={`design-${key}`}
                                value={tokens[key]}
                                maxLength={7}
                                aria-invalid={Boolean(errors[key])}
                                aria-describedby={
                                    errors[key]
                                        ? `design-${key}-error`
                                        : undefined
                                }
                                onChange={(event) =>
                                    onChange({
                                        ...tokens,
                                        [key]: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <InputError
                            id={`design-${key}-error`}
                            message={errors[key]}
                        />
                    </div>
                ),
            )}
            {(Object.keys(choiceLabels) as ChoiceKey[]).map((key) => (
                <div key={key} className="grid gap-1.5">
                    <Label htmlFor={`design-${key}`}>{choiceLabels[key]}</Label>
                    <select
                        id={`design-${key}`}
                        value={tokens[key]}
                        aria-invalid={Boolean(errors[key])}
                        onChange={(event) =>
                            onChange({ ...tokens, [key]: event.target.value })
                        }
                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        {Object.entries(designChoices[key]).map(
                            ([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ),
                        )}
                    </select>
                    <InputError message={errors[key]} />
                </div>
            ))}
            {canEdit && (
                <Button
                    type="button"
                    disabled={saving || !dirty}
                    onClick={onSave}
                >
                    Сохранить стиль
                </Button>
            )}
        </fieldset>
    );
}

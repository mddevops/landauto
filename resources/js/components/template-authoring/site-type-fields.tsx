import type { Choice } from '@/components/platform/form-fields';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

/** Checkboxes for the compatible Site types of a Template (D-119), submitted as `site_types[]`. */
export function SiteTypeFields({
    choices,
    selected,
    error,
}: {
    choices: Choice[];
    selected: string[];
    error?: string;
}) {
    return (
        <fieldset className="grid gap-2">
            <legend className="mb-1 text-sm font-medium">
                Подходит для сайтов
            </legend>
            {choices.map((choice) => (
                <div key={choice.value} className="flex items-center gap-2">
                    <Checkbox
                        id={`site-type-${choice.value}`}
                        name="site_types[]"
                        value={choice.value}
                        defaultChecked={selected.includes(choice.value)}
                    />
                    <Label htmlFor={`site-type-${choice.value}`}>
                        {choice.label}
                    </Label>
                </div>
            ))}
            <InputError message={error} />
        </fieldset>
    );
}

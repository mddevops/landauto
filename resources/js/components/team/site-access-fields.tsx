import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

export type SiteAccessMode = 'all_sites' | 'selected_sites';

export type TeamSite = { public_id: string; name: string };

export function SiteAccessFields({
    idPrefix,
    sites,
    mode,
    selected,
    onModeChange,
    onSelectedChange,
    error,
    forcedAllSites,
}: {
    idPrefix: string;
    sites: TeamSite[];
    mode: SiteAccessMode;
    selected: string[];
    onModeChange: (mode: SiteAccessMode) => void;
    onSelectedChange: (selected: string[]) => void;
    error?: string;
    forcedAllSites: boolean;
}) {
    if (forcedAllSites) {
        return (
            <p className="text-sm text-muted-foreground">
                Доступ к сайтам: все сайты. Для этой роли доступ всегда открыт
                ко всем сайтам пространства.
            </p>
        );
    }

    function toggle(publicId: string, checked: boolean) {
        onSelectedChange(
            checked
                ? [...selected, publicId]
                : selected.filter((id) => id !== publicId),
        );
    }

    return (
        <fieldset className="grid gap-3">
            <legend className="mb-2 text-sm font-medium">
                Доступ к сайтам
            </legend>
            <div className="flex flex-col gap-2 sm:flex-row sm:gap-6">
                {(
                    [
                        ['all_sites', 'Все сайты'],
                        ['selected_sites', 'Выбранные сайты'],
                    ] as const
                ).map(([value, label]) => (
                    <label
                        key={value}
                        className="flex items-center gap-2 text-sm"
                        htmlFor={`${idPrefix}-mode-${value}`}
                    >
                        <input
                            id={`${idPrefix}-mode-${value}`}
                            type="radio"
                            name={`${idPrefix}-site-access-mode`}
                            value={value}
                            checked={mode === value}
                            onChange={() => onModeChange(value)}
                            className="size-4 accent-primary"
                        />
                        {label}
                    </label>
                ))}
            </div>
            {mode === 'selected_sites' &&
                (sites.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        В пространстве пока нет сайтов.
                    </p>
                ) : (
                    <ul
                        className="grid max-h-56 gap-2 overflow-y-auto rounded-md border p-3"
                        aria-label="Сайты"
                    >
                        {sites.map((site) => {
                            const id = `${idPrefix}-site-${site.public_id}`;

                            return (
                                <li
                                    key={site.public_id}
                                    className="flex items-center gap-2"
                                >
                                    <Checkbox
                                        id={id}
                                        checked={selected.includes(
                                            site.public_id,
                                        )}
                                        onCheckedChange={(checked) =>
                                            toggle(
                                                site.public_id,
                                                checked === true,
                                            )
                                        }
                                    />
                                    <Label
                                        htmlFor={id}
                                        className="font-normal break-words"
                                    >
                                        {site.name}
                                    </Label>
                                </li>
                            );
                        })}
                    </ul>
                ))}
            <InputError message={error} />
        </fieldset>
    );
}

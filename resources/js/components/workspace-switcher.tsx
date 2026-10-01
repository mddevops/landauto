import { router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown } from 'lucide-react';
import { useState } from 'react';
import WorkspaceContextController from '@/actions/App/Http/Controllers/WorkspaceContextController';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { Spinner } from '@/components/ui/spinner';
import type { WorkspaceSummary } from '@/types';

export function WorkspaceSwitcher() {
    const { workspace } = usePage().props;
    const [switchingTo, setSwitchingTo] = useState<string | null>(null);
    const current = workspace.current;

    if (!current) {
        return null;
    }

    const switchWorkspace = (selected: WorkspaceSummary) => {
        if (switchingTo || selected.public_id === current.public_id) {
            return;
        }

        setSwitchingTo(selected.public_id);
        router.post(
            WorkspaceContextController.update.url(selected.public_id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setSwitchingTo(null),
            },
        );
    };

    const currentLabel = (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                <Building2 className="size-4" aria-hidden="true" />
            </div>
            <span className="min-w-0 flex-1 truncate font-medium">
                {current.name}
            </span>
        </>
    );

    if (workspace.available.length === 1) {
        return (
            <SidebarMenu aria-label="Текущее рабочее пространство">
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" asChild tooltip={current.name}>
                        <div
                            aria-label={`Текущее рабочее пространство: ${current.name}`}
                        >
                            {currentLabel}
                        </div>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        );
    }

    return (
        <SidebarMenu aria-label="Переключатель рабочих пространств">
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            tooltip="Сменить рабочее пространство"
                            aria-label={`Сменить рабочее пространство. Текущее: ${current.name}`}
                            aria-busy={switchingTo !== null}
                            disabled={switchingTo !== null}
                        >
                            {currentLabel}
                            {switchingTo ? (
                                <Spinner className="ml-auto" />
                            ) : (
                                <ChevronsUpDown
                                    className="ml-auto size-4"
                                    aria-hidden="true"
                                />
                            )}
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        align="start"
                        side="bottom"
                    >
                        <DropdownMenuLabel>
                            Рабочие пространства
                        </DropdownMenuLabel>
                        {workspace.available.map((availableWorkspace) => {
                            const isCurrent =
                                availableWorkspace.public_id ===
                                current.public_id;

                            return (
                                <DropdownMenuItem
                                    key={availableWorkspace.public_id}
                                    disabled={switchingTo !== null || isCurrent}
                                    onSelect={() =>
                                        switchWorkspace(availableWorkspace)
                                    }
                                >
                                    <span className="truncate">
                                        {availableWorkspace.name}
                                    </span>
                                    {isCurrent && (
                                        <Check
                                            className="ml-auto size-4"
                                            aria-label="Выбрано"
                                        />
                                    )}
                                </DropdownMenuItem>
                            );
                        })}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}

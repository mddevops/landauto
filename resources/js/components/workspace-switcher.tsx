import { Link, router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown, Plus, Settings } from 'lucide-react';
import { useState } from 'react';
import WorkspaceContextController from '@/actions/App/Http/Controllers/WorkspaceContextController';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { Spinner } from '@/components/ui/spinner';
import { edit as workspaceSettings } from '@/routes/workspace/settings';
import { create as createWorkspace } from '@/routes/workspaces';
import type { WorkspaceSummary } from '@/types';

export function WorkspaceSwitcher() {
    const { workspace } = usePage().props;
    const [switchingTo, setSwitchingTo] = useState<string | null>(null);
    const current = workspace.current;

    if (!current) {
        return null;
    }

    const canManage = workspace.permissions.includes('edit_workspace');

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
                            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                                <Building2
                                    className="size-4"
                                    aria-hidden="true"
                                />
                            </div>
                            <span className="grid min-w-0 flex-1 text-left leading-tight">
                                <span className="truncate text-xs text-muted-foreground">
                                    Пространство
                                </span>
                                <span className="truncate font-medium">
                                    {current.name}
                                </span>
                            </span>
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
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <Link href={createWorkspace()}>
                                <Plus aria-hidden="true" />
                                Создать пространство
                            </Link>
                        </DropdownMenuItem>
                        {canManage && (
                            <DropdownMenuItem asChild>
                                <Link href={workspaceSettings()}>
                                    <Settings aria-hidden="true" />
                                    Управление пространством
                                </Link>
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}

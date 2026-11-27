import { ChevronsUpDown, Layers, Pencil, Plus } from 'lucide-react';
import { Fragment, useState } from 'react';
import {
    CtButton,
    CtInput,
    CtNumber,
    CtSelect,
    CtSwitch,
    IconGhost,
} from '@/components/widget/ct';
import { DiscardConfirm } from '@/components/widget/widget-frame';
import {
    CONDITION_TYPES,
    OPERATOR_LABELS,
    OPERATORS,
    TYPE_LABELS,
    defaultRule,
} from '@/popups/launch';
import type {
    ConditionGroup,
    ConditionRule,
    ConditionType,
    PopupLaunch,
} from '@/popups/launch';

const groupCategories: { label: string; type: ConditionType }[] = [
    { label: 'Страницы', type: 'pages' },
    { label: 'Трафик', type: 'channel' },
    { label: 'Устройства', type: 'devices' },
    { label: 'Посещение сайта', type: 'visit' },
    { label: 'Цели', type: 'goals' },
    { label: 'Геотаргетинг', type: 'geo' },
];

const operatorLabel = (operator: string) =>
    operator === 'regex'
        ? 'Регулярное выражение (RegExp)'
        : (OPERATOR_LABELS[operator] ?? operator);

function valueLabel(rule: ConditionRule): string {
    if (rule.type === 'devices') {
        return rule.value === 'desktop' ? 'Компьютеры' : 'Мобильные устройства';
    }

    if (rule.type === 'visit') {
        return rule.operator === 'unique'
            ? 'уникальный визит'
            : `за последние ${typeof rule.value === 'number' ? rule.value : 3} дн.`;
    }

    return typeof rule.value === 'string' && rule.value !== ''
        ? rule.value
        : '—';
}

function Chip({
    children,
    tone = 'field',
}: {
    children: React.ReactNode;
    tone?: 'field' | 'operator';
}) {
    return (
        <span
            className={
                tone === 'operator'
                    ? 'bg-[#E2F3F7] px-2 py-0.5 text-[#064861]'
                    : 'bg-[#F2F0ED] px-2 py-0.5 text-[#3F3932]'
            }
        >
            {children}
        </span>
    );
}

export function JoinPill({
    value,
    onChange,
}: {
    value: 'and' | 'or';
    onChange: (value: 'and' | 'or') => void;
}) {
    return (
        <button
            type="button"
            aria-label={`Соединение условий: ${value === 'and' ? 'И' : 'ИЛИ'}`}
            className="my-2 inline-flex h-6 items-center gap-1 bg-[#F1F0EE] px-2 text-xs text-[#746A5D] hover:text-[#3F3932]"
            onClick={() => onChange(value === 'and' ? 'or' : 'and')}
        >
            {value === 'and' ? 'И' : 'ИЛИ'}
            <ChevronsUpDown className="size-3" aria-hidden="true" />
        </button>
    );
}

export function ConditionsAddButton({ onClick }: { onClick: () => void }) {
    return (
        <CtButton
            tone="brown"
            className="h-6 text-xs"
            icon={<Layers className="size-3.5" aria-hidden="true" />}
            onClick={onClick}
        >
            Добавить набор условий
        </CtButton>
    );
}

export function ConditionsScreen({
    launch,
    onChange,
    saving,
    dirty,
    onDiscard,
}: {
    launch: PopupLaunch;
    onChange: (launch: PopupLaunch) => void;
    saving: boolean;
    dirty: boolean;
    onDiscard: () => void;
}) {
    const [editing, setEditing] = useState(false);
    const [picking, setPicking] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const conditions = launch.conditions;
    const groups = conditions.groups;

    function setConditions(patch: Partial<PopupLaunch['conditions']>) {
        onChange({ ...launch, conditions: { ...conditions, ...patch } });
    }

    function setGroups(next: ConditionGroup[]) {
        setConditions({ groups: next });
    }

    function updateRule(
        groupIndex: number,
        ruleIndex: number,
        rule: ConditionRule,
    ) {
        setGroups(
            groups.map((group, index) =>
                index === groupIndex
                    ? {
                          ...group,
                          rules: group.rules.map((current, currentIndex) =>
                              currentIndex === ruleIndex ? rule : current,
                          ),
                      }
                    : group,
            ),
        );
    }

    if (groups.length === 0) {
        return (
            <div className="flex min-h-[320px] flex-1 flex-col items-center justify-center gap-3 bg-white p-8 text-center">
                <h2 className="text-lg text-[#1F282C]">
                    Пока нет условий показа
                </h2>
                <p className="max-w-sm text-sm text-[#746A5D]">
                    Создайте набор условий, чтобы показывать виджет только
                    нужной аудитории
                </p>
                <ConditionsAddButton
                    onClick={() => {
                        setGroups([{ join: 'or', rules: [defaultRule()] }]);
                        setEditing(true);
                    }}
                />
            </div>
        );
    }

    const title = conditions.name || 'Набор условий';

    return (
        <div className="relative flex flex-1 flex-col">
            <div className="bg-white">
                <div className="flex items-center gap-2 px-4 pt-4 pb-3">
                    {editing ? (
                        <input
                            aria-label="Название набора условий"
                            value={conditions.name}
                            placeholder="Набор условий"
                            maxLength={80}
                            className="h-8 min-w-64 border border-[#8F9496] px-2 text-lg outline-none focus:border-[#3DAFCC]"
                            onChange={(event) =>
                                setConditions({ name: event.target.value })
                            }
                        />
                    ) : (
                        <h2 className="text-lg text-[#1F282C]">{title}</h2>
                    )}
                    {editing && (
                        <Pencil
                            className="size-4 text-[#3DAFCC]"
                            aria-hidden="true"
                        />
                    )}
                    {!editing && (
                        <div className="ml-auto flex items-center gap-4">
                            <CtSwitch
                                checked={conditions.enabled}
                                onChange={(enabled) =>
                                    setConditions({ enabled })
                                }
                                label="Набор условий включён"
                            />
                            <IconGhost
                                label="Удалить набор"
                                kind="trash"
                                onClick={() =>
                                    setConditions({ name: '', groups: [] })
                                }
                            />
                        </div>
                    )}
                    {editing && (
                        <div className="ml-auto">
                            <IconGhost
                                label="Удалить набор"
                                kind="trash"
                                onClick={() => {
                                    setGroups([]);
                                    setEditing(false);
                                }}
                            />
                        </div>
                    )}
                </div>
                {!editing ? (
                    <button
                        type="button"
                        aria-label="Изменить набор условий"
                        className="block w-full px-4 pb-6 pl-10 text-left text-sm"
                        onClick={() => setEditing(true)}
                    >
                        {groups.map((group, groupIndex) => (
                            <Fragment key={groupIndex}>
                                {groupIndex > 0 && (
                                    <span className="my-2 block text-[#746A5D]">
                                        {conditions.join === 'and'
                                            ? 'и'
                                            : 'или'}
                                    </span>
                                )}
                                <span className="flex flex-wrap items-center gap-1.5">
                                    <span className="text-[#746A5D]">(</span>
                                    {group.rules.map((rule, ruleIndex) => (
                                        <Fragment key={ruleIndex}>
                                            {ruleIndex > 0 && (
                                                <span className="px-1 text-[#746A5D]">
                                                    {group.join === 'and'
                                                        ? 'и'
                                                        : 'или'}
                                                </span>
                                            )}
                                            <Chip>
                                                {TYPE_LABELS[rule.type]}
                                            </Chip>
                                            <Chip tone="operator">
                                                {operatorLabel(rule.operator)}
                                            </Chip>
                                            <Chip>{valueLabel(rule)}</Chip>
                                        </Fragment>
                                    ))}
                                    <span className="text-[#746A5D]">)</span>
                                </span>
                            </Fragment>
                        ))}
                    </button>
                ) : (
                    <div className="px-4 pb-4">
                        {groups.map((group, groupIndex) => (
                            <Fragment key={groupIndex}>
                                {groupIndex > 0 && (
                                    <JoinPill
                                        value={conditions.join}
                                        onChange={(join) =>
                                            setConditions({ join })
                                        }
                                    />
                                )}
                                <div className="bg-[#F0F0F0] p-4">
                                    {group.rules.map((rule, ruleIndex) => (
                                        <Fragment key={ruleIndex}>
                                            {ruleIndex > 0 && (
                                                <JoinPill
                                                    value={group.join}
                                                    onChange={(join) =>
                                                        setGroups(
                                                            groups.map(
                                                                (
                                                                    item,
                                                                    index,
                                                                ) =>
                                                                    index ===
                                                                    groupIndex
                                                                        ? {
                                                                              ...item,
                                                                              join,
                                                                          }
                                                                        : item,
                                                            ),
                                                        )
                                                    }
                                                />
                                            )}
                                            <RuleRow
                                                rule={rule}
                                                onChange={(next) =>
                                                    updateRule(
                                                        groupIndex,
                                                        ruleIndex,
                                                        next,
                                                    )
                                                }
                                                onDelete={() =>
                                                    setGroups(
                                                        groups
                                                            .map(
                                                                (
                                                                    item,
                                                                    index,
                                                                ) =>
                                                                    index ===
                                                                    groupIndex
                                                                        ? {
                                                                              ...item,
                                                                              rules: item.rules.filter(
                                                                                  (
                                                                                      _,
                                                                                      current,
                                                                                  ) =>
                                                                                      current !==
                                                                                      ruleIndex,
                                                                              ),
                                                                          }
                                                                        : item,
                                                            )
                                                            .filter(
                                                                (item) =>
                                                                    item.rules
                                                                        .length >
                                                                    0,
                                                            ),
                                                    )
                                                }
                                            />
                                        </Fragment>
                                    ))}
                                    <div className="mt-2 flex justify-end">
                                        <button
                                            type="button"
                                            className="inline-flex items-center gap-1 text-xs text-[#746A5D] hover:text-[#3F3932]"
                                            onClick={() =>
                                                setGroups(
                                                    groups.map((item, index) =>
                                                        index === groupIndex
                                                            ? {
                                                                  ...item,
                                                                  rules: [
                                                                      ...item.rules,
                                                                      defaultRule(),
                                                                  ],
                                                              }
                                                            : item,
                                                    ),
                                                )
                                            }
                                        >
                                            <Layers
                                                className="size-3.5"
                                                aria-hidden="true"
                                            />
                                            Добавить условие
                                        </button>
                                    </div>
                                </div>
                            </Fragment>
                        ))}
                        <div className="relative mt-4 w-fit">
                            <CtButton
                                tone="sand"
                                icon={
                                    <Plus
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                }
                                onClick={() => setPicking((value) => !value)}
                            >
                                Добавить группу
                            </CtButton>
                            {picking && (
                                <div className="absolute top-full left-0 z-10 mt-1 w-56 bg-[#746A5D] py-1 text-sm text-white shadow-lg">
                                    <p className="px-3 py-1.5 text-xs opacity-80">
                                        Выберите условие
                                    </p>
                                    {groupCategories.map((category) => (
                                        <button
                                            key={category.label}
                                            type="button"
                                            className="block w-full px-3 py-1.5 text-left hover:bg-white/10"
                                            onClick={() => {
                                                setGroups([
                                                    ...groups,
                                                    {
                                                        join: 'or',
                                                        rules: [
                                                            defaultRule(
                                                                category.type,
                                                            ),
                                                        ],
                                                    },
                                                ]);
                                                setPicking(false);
                                            }}
                                        >
                                            {category.label}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                )}
                {(editing || dirty) && (
                    <div className="flex items-center justify-end gap-2 bg-[#444B4C] px-4 py-3">
                        <button
                            type="button"
                            className="h-8 bg-[#8F9496] px-4 text-sm text-white hover:bg-[#7A8083]"
                            onClick={() => {
                                if (dirty) {
                                    setConfirming(true);
                                } else {
                                    setEditing(false);
                                }
                            }}
                        >
                            Отменить
                        </button>
                        <button
                            type="submit"
                            disabled={saving}
                            className="h-8 bg-[#3DAFCC] px-4 text-sm text-white hover:bg-[#2D9CB8] disabled:bg-[#9ED7E5]"
                        >
                            Сохранить
                        </button>
                    </div>
                )}
            </div>
            {confirming && (
                <DiscardConfirm
                    onYes={() => {
                        setConfirming(false);
                        setEditing(false);
                        onDiscard();
                    }}
                    onNo={() => setConfirming(false)}
                />
            )}
        </div>
    );
}

function RuleRow({
    rule,
    onChange,
    onDelete,
}: {
    rule: ConditionRule;
    onChange: (rule: ConditionRule) => void;
    onDelete: () => void;
}) {
    return (
        <div className="grid items-center gap-2 md:grid-cols-[300px_194px_1fr_24px]">
            <CtSelect
                value={rule.type}
                ariaLabel="Тип условия"
                options={CONDITION_TYPES.map((type) => ({
                    value: type,
                    label: TYPE_LABELS[type],
                }))}
                onChange={(type) =>
                    onChange(defaultRule(type as ConditionType))
                }
            />
            <CtSelect
                value={rule.operator}
                ariaLabel="Оператор"
                options={OPERATORS[rule.type].map((operator) => ({
                    value: operator,
                    label: operatorLabel(operator),
                }))}
                onChange={(operator) => {
                    if (rule.type === 'visit' && operator === 'unique') {
                        onChange({ ...rule, operator, value: null });

                        return;
                    }

                    if (
                        rule.type === 'visit' &&
                        (operator === 'visited' || operator === 'not_visited')
                    ) {
                        onChange({
                            ...rule,
                            operator,
                            value:
                                typeof rule.value === 'number' ? rule.value : 3,
                        });

                        return;
                    }

                    onChange({ ...rule, operator });
                }}
            />
            <RuleValue rule={rule} onChange={onChange} />
            <IconGhost
                label="Удалить условие"
                kind="trash"
                onClick={onDelete}
            />
        </div>
    );
}

function RuleValue({
    rule,
    onChange,
}: {
    rule: ConditionRule;
    onChange: (rule: ConditionRule) => void;
}) {
    if (rule.type === 'devices') {
        return (
            <CtSelect
                value={rule.value === 'desktop' ? 'desktop' : 'mobile'}
                ariaLabel="Значение условия"
                options={[
                    { value: 'mobile', label: 'Мобильные устройства' },
                    { value: 'desktop', label: 'Компьютеры' },
                ]}
                onChange={(value) => onChange({ ...rule, value })}
            />
        );
    }

    if (rule.type === 'visit') {
        if (rule.operator === 'unique') {
            return <span className="text-sm text-[#8F9496]">Без срока</span>;
        }

        return (
            <span className="flex items-center gap-2 text-sm">
                за последние
                <CtNumber
                    className="w-28"
                    value={typeof rule.value === 'number' ? rule.value : 3}
                    min={1}
                    max={365}
                    suffix="дн."
                    slider={false}
                    ariaLabel="Значение условия"
                    onChange={(value) => onChange({ ...rule, value })}
                />
            </span>
        );
    }

    const placeholder =
        rule.type === 'geo'
            ? 'Введите название города, региона или страны'
            : rule.type === 'pages' || rule.type === 'campaign'
              ? 'Введите значение'
              : 'Выберите значение';

    return (
        <CtInput
            aria-label="Значение условия"
            placeholder={placeholder}
            value={typeof rule.value === 'string' ? rule.value : ''}
            onChange={(event) =>
                onChange({ ...rule, value: event.target.value })
            }
        />
    );
}

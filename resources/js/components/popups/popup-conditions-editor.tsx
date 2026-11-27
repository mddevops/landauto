import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

function isType(value: string): value is ConditionType {
    return (CONDITION_TYPES as readonly string[]).includes(value);
}

function valueControl(
    rule: ConditionRule,
    onChange: (rule: ConditionRule) => void,
) {
    if (rule.type === 'visit' && rule.operator === 'unique') {
        return (
            <p className="text-xs text-muted-foreground">
                Значение не нужно: проверяется первый визит в этом браузере.
            </p>
        );
    }

    if (rule.type === 'visit') {
        return (
            <div className="grid gap-1.5">
                <Label>За последние дни</Label>
                <Input
                    type="number"
                    min={1}
                    max={365}
                    value={typeof rule.value === 'number' ? rule.value : 30}
                    onChange={(event) =>
                        onChange({
                            ...rule,
                            value: Number(event.target.value),
                        })
                    }
                />
            </div>
        );
    }

    if (rule.type === 'devices') {
        return (
            <div className="grid gap-1.5">
                <Label>Устройство</Label>
                <select
                    className={selectClass}
                    value={rule.value === 'desktop' ? 'desktop' : 'mobile'}
                    onChange={(event) =>
                        onChange({ ...rule, value: event.target.value })
                    }
                >
                    <option value="mobile">Мобильные устройства</option>
                    <option value="desktop">Компьютеры</option>
                </select>
            </div>
        );
    }

    if (rule.type === 'goals') {
        return (
            <p className="text-xs text-muted-foreground">
                Цели Метрики в Landflow не передаются в попап. Правило не
                сработает, пока нет данных.
            </p>
        );
    }

    if (rule.type === 'geo') {
        return (
            <div className="grid gap-1.5">
                <Label>Город, регион или страна</Label>
                <Input
                    value={typeof rule.value === 'string' ? rule.value : ''}
                    onChange={(event) =>
                        onChange({ ...rule, value: event.target.value })
                    }
                    placeholder="Москва"
                />
                <p className="text-xs text-muted-foreground">
                    Гео посетителя сейчас не определяется: правило не
                    выполняется.
                </p>
            </div>
        );
    }

    const placeholder =
        rule.type === 'channel'
            ? 'cpc'
            : rule.type === 'source'
              ? 'yandex'
              : rule.type === 'campaign'
                ? 'spring'
                : '/credit';

    return (
        <div className="grid gap-1.5">
            <Label>Значение</Label>
            <Input
                value={typeof rule.value === 'string' ? rule.value : ''}
                onChange={(event) =>
                    onChange({ ...rule, value: event.target.value })
                }
                placeholder={placeholder}
            />
        </div>
    );
}

export function PopupConditionsEditor({
    value,
    onChange,
}: {
    value: PopupLaunch['conditions'];
    onChange: (value: PopupLaunch['conditions']) => void;
}) {
    function setGroup(index: number, group: ConditionGroup) {
        onChange({
            ...value,
            groups: value.groups.map((item, current) =>
                current === index ? group : item,
            ),
        });
    }

    function setRule(
        groupIndex: number,
        ruleIndex: number,
        rule: ConditionRule,
    ) {
        const group = value.groups[groupIndex];
        setGroup(groupIndex, {
            ...group,
            rules: group.rules.map((item, current) =>
                current === ruleIndex ? rule : item,
            ),
        });
    }

    return (
        <fieldset className="grid gap-3">
            <legend className="text-sm font-medium">Условия аудитории</legend>
            <p className="text-xs text-muted-foreground">
                Эти правила ограничивают только автоматический показ. Клик по
                кнопке «Открыть попап» открывает окно независимо от таймера и
                аудитории.
            </p>
            {value.groups.length > 1 && (
                <div className="grid gap-1.5">
                    <Label>Связка между группами</Label>
                    <select
                        className={selectClass}
                        value={value.join}
                        onChange={(event) =>
                            onChange({
                                ...value,
                                join: event.target.value as 'and' | 'or',
                            })
                        }
                    >
                        <option value="and">И</option>
                        <option value="or">ИЛИ</option>
                    </select>
                </div>
            )}
            {value.groups.map((group, groupIndex) => (
                <div
                    key={groupIndex}
                    className="grid gap-3 rounded-md border p-3"
                >
                    <div className="flex items-center justify-between gap-2">
                        <p className="text-sm font-medium">
                            Группа {groupIndex + 1}
                        </p>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                onChange({
                                    ...value,
                                    groups: value.groups.filter(
                                        (_, current) => current !== groupIndex,
                                    ),
                                })
                            }
                        >
                            <Trash2 aria-hidden="true" />
                            Удалить группу
                        </Button>
                    </div>
                    {group.rules.length > 1 && (
                        <div className="grid gap-1.5">
                            <Label>Связка внутри группы</Label>
                            <select
                                className={selectClass}
                                value={group.join}
                                onChange={(event) =>
                                    setGroup(groupIndex, {
                                        ...group,
                                        join: event.target.value as
                                            | 'and'
                                            | 'or',
                                    })
                                }
                            >
                                <option value="and">И</option>
                                <option value="or">ИЛИ</option>
                            </select>
                        </div>
                    )}
                    {group.rules.map((rule, ruleIndex) => (
                        <div
                            key={ruleIndex}
                            className="grid gap-2 rounded-md bg-muted/40 p-3"
                        >
                            <div className="grid gap-2 sm:grid-cols-2">
                                <div className="grid gap-1.5">
                                    <Label>Тип</Label>
                                    <select
                                        className={selectClass}
                                        value={rule.type}
                                        onChange={(event) => {
                                            const type = event.target.value;

                                            if (isType(type)) {
                                                setRule(
                                                    groupIndex,
                                                    ruleIndex,
                                                    defaultRule(type),
                                                );
                                            }
                                        }}
                                    >
                                        {CONDITION_TYPES.map((type) => (
                                            <option key={type} value={type}>
                                                {TYPE_LABELS[type]}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="grid gap-1.5">
                                    <Label>Оператор</Label>
                                    <select
                                        className={selectClass}
                                        value={rule.operator}
                                        onChange={(event) => {
                                            const operator = event.target.value;
                                            const next = defaultRule(rule.type);
                                            next.operator = operator;

                                            if (operator === 'unique') {
                                                next.value = null;
                                            }

                                            setRule(
                                                groupIndex,
                                                ruleIndex,
                                                next,
                                            );
                                        }}
                                    >
                                        {OPERATORS[rule.type].map(
                                            (operator) => (
                                                <option
                                                    key={operator}
                                                    value={operator}
                                                >
                                                    {OPERATOR_LABELS[operator]}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                </div>
                            </div>
                            {valueControl(rule, (next) =>
                                setRule(groupIndex, ruleIndex, next),
                            )}
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    const rules = group.rules.filter(
                                        (_, current) => current !== ruleIndex,
                                    );

                                    if (rules.length === 0) {
                                        onChange({
                                            ...value,
                                            groups: value.groups.filter(
                                                (_, current) =>
                                                    current !== groupIndex,
                                            ),
                                        });

                                        return;
                                    }

                                    setGroup(groupIndex, { ...group, rules });
                                }}
                            >
                                Удалить строку
                            </Button>
                        </div>
                    ))}
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={group.rules.length >= 10}
                        onClick={() =>
                            setGroup(groupIndex, {
                                ...group,
                                rules: [...group.rules, defaultRule()],
                            })
                        }
                    >
                        <Plus aria-hidden="true" />
                        Добавить строку
                    </Button>
                </div>
            ))}
            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={value.groups.length >= 8}
                    onClick={() =>
                        onChange({
                            ...value,
                            groups: [
                                ...value.groups,
                                { join: 'and', rules: [defaultRule()] },
                            ],
                        })
                    }
                >
                    <Plus aria-hidden="true" />
                    Добавить группу
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() =>
                        onChange({
                            join: 'and',
                            groups: [],
                            name: '',
                            enabled: true,
                        })
                    }
                >
                    Отменить условия
                </Button>
            </div>
        </fieldset>
    );
}

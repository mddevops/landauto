import { createContext, use } from 'react';
import type { ReactNode } from 'react';

/**
 * Public identifiers describing where an action was triggered. They are hints only: the
 * backend resolves and verifies every referenced entity before trusting it.
 */
export type TriggerContextValue = {
    block?: string;
    vehicle?: string;
    offer?: string;
    media_set?: string;
};

const TriggerContext = createContext<TriggerContextValue>({});

export function useTriggerContext(): TriggerContextValue {
    return use(TriggerContext);
}

export function TriggerScope({
    value,
    children,
}: {
    value: TriggerContextValue;
    children: ReactNode;
}) {
    const parent = useTriggerContext();

    return (
        <TriggerContext value={{ ...parent, ...value }}>
            {children}
        </TriggerContext>
    );
}

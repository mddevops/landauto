import { useState } from 'react';
import { ButtonPreview, Container } from '@/blocks/official-blocks';
import { quizSteps } from '@/blocks/quiz-block';
import { useBlockRenderContext } from '@/blocks/render-context';
import type { BlockRendererProps } from '@/blocks/state';
import { flag, group, text } from '@/blocks/state';
import { TriggerScope } from '@/blocks/trigger-context';
import { vehicleFullTitle } from '@/blocks/vehicles';
import { cn } from '@/lib/utils';

type Message = { id: string; from: 'site' | 'visitor'; text: string };

type Choice = { id: string; label: string; onChoose: () => void };

const UNDECIDED = 'Пока не определился';

/**
 * Official Chat Selection Block (P9-017): the Site's scripted questions in a chat layout, an
 * optional pick from the Site vehicles, then the lead button. Nothing pretends to be an operator:
 * there are no names, typing indicators or generated replies.
 */
export function ChatSelectionBlock({ state }: BlockRendererProps) {
    const steps = quizSteps(state);
    const { vehicles } = useBlockRenderContext();
    const vehicleQuestion = text(state, 'vehicle_question');
    const asksVehicle =
        flag(state, 'show_vehicles', true) &&
        vehicles.length > 0 &&
        vehicleQuestion !== null;
    const [answers, setAnswers] = useState<string[]>([]);
    const [vehicle, setVehicle] = useState<string | null | undefined>(
        undefined,
    );
    const current = Math.min(answers.length, steps.length);
    const messages: Message[] = [];
    let choices: Choice[] = [];

    const intro = text(state, 'intro');

    if (intro) {
        messages.push({ id: 'intro', from: 'site', text: intro });
    }

    steps.slice(0, current + 1).forEach((step, index) => {
        messages.push({
            id: `q-${step.id}`,
            from: 'site',
            text: step.question,
        });
        const answer = step.options.find(
            (option) => option.id === answers[index],
        );

        if (answer) {
            messages.push({
                id: `a-${step.id}`,
                from: 'visitor',
                text: answer.label,
            });
        } else {
            choices = step.options.map((option) => ({
                id: option.id,
                label: option.label,
                onChoose: () => setAnswers([...answers, option.id]),
            }));
        }
    });

    const stepsDone = current >= steps.length;

    if (stepsDone && asksVehicle) {
        messages.push({ id: 'vehicle-q', from: 'site', text: vehicleQuestion });

        if (vehicle === undefined) {
            choices = [
                ...vehicles.map((item) => ({
                    id: item.public_id,
                    label: vehicleFullTitle(item),
                    onChoose: () => setVehicle(item.public_id),
                })),
                {
                    id: 'undecided',
                    label: UNDECIDED,
                    onChoose: () => setVehicle(null),
                },
            ];
        } else {
            const chosen = vehicles.find((item) => item.public_id === vehicle);
            messages.push({
                id: 'vehicle-a',
                from: 'visitor',
                text: chosen ? vehicleFullTitle(chosen) : UNDECIDED,
            });
        }
    }

    const finished = stepsDone && (!asksVehicle || vehicle !== undefined);
    const finalMessage = text(state, 'final_message');

    if (finished && finalMessage) {
        messages.push({ id: 'final', from: 'site', text: finalMessage });
    }

    return (
        <section className="bg-neutral-100 py-16 text-neutral-900">
            <Container className="flex max-w-2xl flex-col gap-4">
                <div className="flex flex-col gap-1">
                    <h2 className="text-3xl font-bold break-words">
                        {text(state, 'title')}
                    </h2>
                    <p className="text-sm text-neutral-500">
                        Автоматические вопросы сайта. Менеджер ответит после
                        заявки.
                    </p>
                </div>
                <div className="flex flex-col gap-4 rounded-(--lf-radius) bg-white p-4 shadow-sm sm:p-6">
                    <ol
                        role="log"
                        aria-label="Переписка"
                        className="flex flex-col gap-3"
                    >
                        {messages.map((message) => (
                            <li
                                key={message.id}
                                className={cn(
                                    'max-w-[85%] rounded-2xl px-4 py-2 break-words whitespace-pre-line',
                                    message.from === 'site'
                                        ? 'self-start bg-neutral-100'
                                        : 'self-end bg-(--lf-primary) text-(--lf-on-primary)',
                                )}
                            >
                                <span className="sr-only">
                                    {message.from === 'site'
                                        ? 'Сайт: '
                                        : 'Вы: '}
                                </span>
                                {message.text}
                            </li>
                        ))}
                    </ol>
                    {choices.length > 0 && (
                        <div
                            role="group"
                            aria-label="Варианты ответа"
                            className="flex flex-wrap justify-end gap-2"
                        >
                            {choices.map((choice) => (
                                <button
                                    key={choice.id}
                                    type="button"
                                    className="rounded-full border-2 border-(--lf-primary) px-4 py-1.5 text-sm font-medium text-(--lf-primary) hover:bg-(--lf-primary) hover:text-(--lf-on-primary)"
                                    onClick={choice.onChoose}
                                >
                                    {choice.label}
                                </button>
                            ))}
                        </div>
                    )}
                    {finished && (
                        <div className="flex flex-wrap items-center gap-4">
                            <TriggerScope
                                value={{
                                    ...(steps.length > 0 && {
                                        answers: answers.slice(0, steps.length),
                                    }),
                                    ...(typeof vehicle === 'string' && {
                                        vehicle,
                                    }),
                                }}
                            >
                                <ButtonPreview
                                    button={group(state, 'button')}
                                />
                            </TriggerScope>
                            <button
                                type="button"
                                className="text-sm text-neutral-600 underline"
                                onClick={() => {
                                    setAnswers([]);
                                    setVehicle(undefined);
                                }}
                            >
                                Начать заново
                            </button>
                        </div>
                    )}
                </div>
            </Container>
        </section>
    );
}

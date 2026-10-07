import { useState } from 'react';
import { ButtonPreview, Container } from '@/blocks/official-blocks';
import type { BlockRendererProps } from '@/blocks/state';
import { group, items, text } from '@/blocks/state';
import { TriggerScope } from '@/blocks/trigger-context';

type QuizStep = {
    id: string;
    question: string;
    options: { id: string; label: string }[];
};

function quizSteps(state: BlockRendererProps['state']): QuizStep[] {
    return items(state, 'steps').flatMap((step) => {
        const question = text(step, 'question');
        const options = items(step, 'options').flatMap((option) => {
            const label = text(option, 'label');

            return label === null ? [] : [{ id: option.id, label }];
        });

        return question === null || options.length === 0
            ? []
            : [{ id: step.id, question, options }];
    });
}

/**
 * Official quiz Block (P9-016): one question at a time, then the result with the lead button.
 * The chosen option IDs travel as a trigger hint; the backend resolves them against the Block.
 */
export function QuizBlock({ state }: BlockRendererProps) {
    const steps = quizSteps(state);
    const [answers, setAnswers] = useState<string[]>([]);
    const current = Math.min(answers.length, steps.length);
    const step = steps[current] as QuizStep | undefined;

    return (
        <section className="bg-neutral-100 py-16 text-neutral-900">
            <Container className="flex max-w-3xl flex-col gap-6">
                <div className="flex flex-col gap-3">
                    <h2 className="text-3xl font-bold break-words">
                        {text(state, 'title')}
                    </h2>
                    {text(state, 'subtitle') && (
                        <p className="whitespace-pre-line text-neutral-600">
                            {text(state, 'subtitle')}
                        </p>
                    )}
                </div>
                {steps.length === 0 ? (
                    <p className="rounded-(--lf-radius) border border-dashed border-neutral-300 bg-white p-6 text-sm text-neutral-500">
                        В квизе пока нет вопросов.
                    </p>
                ) : step ? (
                    <fieldset className="flex flex-col gap-4 rounded-(--lf-radius) bg-white p-6 shadow-sm">
                        <legend className="float-left w-full text-xl font-semibold break-words">
                            {step.question}
                        </legend>
                        <p className="text-sm text-neutral-500">
                            {`Вопрос ${current + 1} из ${steps.length}`}
                        </p>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {step.options.map((option) => (
                                <button
                                    key={option.id}
                                    type="button"
                                    className="rounded-(--lf-radius) border-2 border-neutral-200 px-4 py-3 text-left font-medium break-words hover:border-(--lf-primary) focus-visible:border-(--lf-primary) focus-visible:outline-none"
                                    onClick={() =>
                                        setAnswers([
                                            ...answers.slice(0, current),
                                            option.id,
                                        ])
                                    }
                                >
                                    {option.label}
                                </button>
                            ))}
                        </div>
                        {current > 0 && (
                            <button
                                type="button"
                                className="self-start text-sm text-neutral-600 underline"
                                onClick={() =>
                                    setAnswers(answers.slice(0, current - 1))
                                }
                            >
                                Назад
                            </button>
                        )}
                    </fieldset>
                ) : (
                    <div className="flex flex-col gap-4 rounded-(--lf-radius) bg-white p-6 shadow-sm">
                        <h3 className="text-2xl font-semibold break-words">
                            {text(state, 'result_title')}
                        </h3>
                        {text(state, 'result_text') && (
                            <p className="whitespace-pre-line text-neutral-600">
                                {text(state, 'result_text')}
                            </p>
                        )}
                        <dl className="grid gap-2 text-sm">
                            {steps.map((quizStep, index) => (
                                <div key={quizStep.id}>
                                    <dt className="text-neutral-500">
                                        {quizStep.question}
                                    </dt>
                                    <dd className="font-medium">
                                        {
                                            quizStep.options.find(
                                                (option) =>
                                                    option.id ===
                                                    answers[index],
                                            )?.label
                                        }
                                    </dd>
                                </div>
                            ))}
                        </dl>
                        <div className="flex flex-wrap items-center gap-4">
                            <TriggerScope
                                value={{
                                    answers: answers.slice(0, steps.length),
                                }}
                            >
                                <ButtonPreview
                                    button={group(state, 'button')}
                                />
                            </TriggerScope>
                            <button
                                type="button"
                                className="text-sm text-neutral-600 underline"
                                onClick={() => setAnswers([])}
                            >
                                Пройти заново
                            </button>
                        </div>
                    </div>
                )}
            </Container>
        </section>
    );
}

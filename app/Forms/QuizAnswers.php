<?php

namespace App\Forms;

/**
 * Resolves answers sent by a visitor from a quiz or chat Block (P9-016, P9-017): one Repeater
 * option ID per step, in step order, looked up in the Block state the page was rendered from.
 * Questions and answers stored in the Submission come from that state, never from the browser.
 */
final class QuizAnswers
{
    /**
     * @return list<array{question: string, answer: string}>|null Null when the answers do not match the steps.
     */
    public static function resolve(mixed $state, mixed $answers): ?array
    {
        if (! is_array($state) || ! is_array($answers) || ! array_is_list($answers)) {
            return null;
        }

        $steps = is_array($state['steps'] ?? null) ? array_values($state['steps']) : [];

        if ($steps === [] || count($answers) !== count($steps)) {
            return null;
        }

        $resolved = [];

        foreach ($steps as $index => $step) {
            $choice = $answers[$index];
            $question = is_array($step) ? ($step['question'] ?? null) : null;

            if (! is_string($choice) || ! is_string($question) || ! is_array($step['options'] ?? null)) {
                return null;
            }

            $answer = null;

            foreach ($step['options'] as $option) {
                if (is_array($option) && is_string($option['id'] ?? null) && strtolower($option['id']) === strtolower($choice) && is_string($option['label'] ?? null)) {
                    $answer = $option['label'];
                }
            }

            if ($answer === null) {
                return null;
            }

            $resolved[] = ['question' => $question, 'answer' => $answer];
        }

        return $resolved;
    }
}

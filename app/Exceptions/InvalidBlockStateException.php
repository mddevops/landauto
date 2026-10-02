<?php

namespace App\Exceptions;

use InvalidArgumentException;

final class InvalidBlockStateException extends InvalidArgumentException
{
    /**
     * @param  array<string, string>  $errors  State path => Russian message.
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Некорректное состояние блока: '.implode('; ', array_map(
            fn (string $path, string $message): string => "{$path}: {$message}",
            array_keys($errors),
            $errors,
        )));
    }
}

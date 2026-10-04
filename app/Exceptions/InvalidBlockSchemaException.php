<?php

namespace App\Exceptions;

use InvalidArgumentException;

final class InvalidBlockSchemaException extends InvalidArgumentException
{
    /**
     * @param  array<string, string>  $errors  Schema path => Russian message.
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Некорректная схема блока: '.implode('; ', array_map(
            fn (string $path, string $message): string => "{$path}: {$message}",
            array_keys($errors),
            $errors,
        )));
    }
}

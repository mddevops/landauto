<?php

namespace App\Blocks\Native;

/**
 * Compiled public output of one Native Block Instance: the trusted HTML fragment placed inside
 * the controlled root and the action keys the runtime may resolve against this Instance.
 */
final readonly class NativeRender
{
    /**
     * @param  list<string>  $actions
     */
    public function __construct(
        public string $scope,
        public string $html,
        public array $actions,
    ) {}

    /**
     * @return array{scope: string, html: string, actions: list<string>}
     */
    public function toPayload(): array
    {
        return ['scope' => $this->scope, 'html' => $this->html, 'actions' => $this->actions];
    }
}

<?php

namespace App\Publishing;

/**
 * One Publish validation finding with a stable machine code, a Russian message and the public
 * IDs needed to locate it. Never carries internal IDs or secrets.
 */
final readonly class PublishIssue
{
    public function __construct(
        public string $code,
        public string $message,
        public ?string $page = null,
        public ?string $block = null,
        public ?string $target = null,
        public ?string $path = null,
    ) {}

    /**
     * @return array{code: string, message: string, page: string|null, block: string|null, target: string|null, path: string|null}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'page' => $this->page,
            'block' => $this->block,
            'target' => $this->target,
            'path' => $this->path,
        ];
    }
}

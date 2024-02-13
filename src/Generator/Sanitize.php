<?php

namespace Battis\OpenAPI\Generator;

use sspat\ReservedWords\ReservedWords;

class Sanitize
{
    private ReservedWords $reserved;

    public function __construct()
    {
        $this->reserved = new ReservedWords();
    }

    public function isSafe(string $name): bool
    {
        // PHP reserved words
        return !$this->reserved->isReserved($name);
    }

    public function clean(string $name): string
    {
        if (!$this->isSafe($name)) {
            return $this->clean($this->rename($name));
        }
        return $name;
    }

    public function rename(string $name): string
    {
        return "_$name";
    }
}

<?php

namespace Battis\OpenAPI\Generator;

use Battis\Loggable\Loggable;
use sspat\ReservedWords\ReservedWords;

class Sanitize extends Loggable
{
    protected static ?Sanitize $instance = null;

    private ReservedWords $reserved;

    private function __construct()
    {
        $this->reserved = new ReservedWords();
    }

    public static function getInstance(): Sanitize
    {
        if (self::$instance === null) {
            self::$instance = new Sanitize();
        }
        return self::$instance;
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
        return $name . "_";
    }
}

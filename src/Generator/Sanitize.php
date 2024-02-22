<?php

namespace Battis\OpenAPI\Generator;

use League\HTMLToMarkdown\HtmlConverter;
use sspat\ReservedWords\ReservedWords;

class Sanitize
{
    protected static ?Sanitize $instance = null;

    private ReservedWords $reserved;
    private HtmlConverter $htmlConverter;

    private function __construct()
    {
        $this->reserved = new ReservedWords();
        $this->htmlConverter = new HtmlConverter();
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

    public function stripHtml(?string $html): ?string
    {
        if ($html !== null) {
            return $this->htmlConverter->convert($html);
        }
        return null;
    }
}

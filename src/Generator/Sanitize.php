<?php

namespace Battis\OpenAPI\Generator;

use League\HTMLToMarkdown\HtmlConverter;
use sspat\ReservedWords\ReservedWords;

/**
 * Sanitize identifiers of PHP keywords
 *
 * Override methods to customize how PHP keywords (and HTML are handled coming
 * from the OpenAPI specification).
 *
 * @api
 */
class Sanitize
{
    private static ?Sanitize $instance = null;

    private ReservedWords $reserved;
    private HtmlConverter $htmlConverter;

    /**
     * Singleton
     */
    private function __construct()
    {
        $this->reserved = new ReservedWords();
        $this->htmlConverter = new HtmlConverter();
    }

    /**
     * Sanitize is a singleton object
     *
     * @return Sanitize Singleton instance
     *
     * @api
     */
    public static function getInstance(): Sanitize
    {
        if (static::$instance === null) {
            static::$instance = new Sanitize();
        }
        return static::$instance;
    }

    /**
     * Is an identifier safe to use (i.e. not a PHP keyword)?
     *
     * @param string $name The identifier to test
     *
     * @return bool
     *
     * @api
     */
    public function isSafe(string $name): bool
    {
        // PHP reserved words
        return !$this->reserved->isReserved($name);
    }

    /**
     * Make an identifer safe to use
     *
     * @param string $name The identifier to clean
     *
     * @return string  A version of the identifier renamed (if necessary) to
     *   not be a PHP keyword
     *
     * @api
     */
    public function clean(string $name): string
    {
        if (!$this->isSafe($name)) {
            return $this->clean($this->rename($name));
        }
        return $name;
    }

    /**
     * Rename an $identifer
     *
     * Hook to override to change the naming schema (e.g. a specific keyword
     * to the identifier)
     *
     * *Default is to append `_` to the end of the identifier*
     *
     * @param string $name The identifier to be renamed
     *
     * @return string  A renamed version of the identifier
     *
     * @api
     */
    public function rename(string $name): string
    {
        return $name . '_';
    }

    /**
     * Convert raw HTML to Markdown
     *
     * @param string $html A string that potentially contains HTML
     *
     * @return string|null `$html` with any HTML converted to Markdown
     */
    public function stripHtml(?string $html): ?string
    {
        if ($html !== null) {
            return $this->htmlConverter->convert($html);
        }
        return null;
    }
}

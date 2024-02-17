<?php

namespace Battis\OpenAPI\Generator\CodeComponent;

use Battis\Loggable\Loggable;
use Battis\OpenAPI\Generator\Exceptions\GeneratorException;

class PHPDoc extends BaseComponent
{
    /** @var string[] $items */
    private array $items = [];

    public function addItem(string $item): void
    {
        $this->items[] = $item;
    }

    public function asString(int $level = 1, int $width = 78): string
    {
        if (empty($this->items)) {
            return "";
        }
        $prevDirective = null;
        $indent = "";
        for($i = 0; $i < $level; $i++) {
            $indent .= "    ";
        }
        $phpdoc = $indent . "/**" . PHP_EOL;
        foreach($this->items as $item) {
            $directive = false;
            if (preg_match("/(@\w+)/i", $item, $match)) {
                $directive = $match[1] ?? false;
            }
            $item = "$indent * $item";
            if ($prevDirective !== null && ($directive !== $prevDirective || $directive === false)) {
                $phpdoc .= "$indent *" . PHP_EOL;
            }
            $directiveIndent = $directive !== false ? "  " : "";
            $wrapped = false;
            $longLastLine = false;
            $w = $width - strlen("$indent * " . ($wrapped ? $directiveIndent : ""));
            while (strlen($item) > $width) {
                $regex = "/^(" . $indent . " \* " . ($wrapped ? $directiveIndent : "") . "(($directive \S{" . ($w - strlen($directive)) . ",})|(.{1,$w})))(\s(.*))?$/m";
                preg_match($regex, $item, $match);
                // TODO tidy up this logic
                if (array_key_exists(1, $match)) {
                    $phpdoc .= $match[1] . PHP_EOL;
                } else {
                    $phpdoc .= $item;
                    $item = "";
                    $longLastLine = true;
                }
                if (array_key_exists(6, $match)) {
                    $item =  "$indent * " . $directiveIndent . $match[6];
                } else {
                    $item = "";
                    $longLastLine = true;
                }
                $wrapped = true;
            }
            if (!$longLastLine) {
                $phpdoc .=  $item . PHP_EOL;
            }            $prevDirective = $directive;
        }
        $phpdoc .= "$indent */" . PHP_EOL;
        return $phpdoc;
    }
}

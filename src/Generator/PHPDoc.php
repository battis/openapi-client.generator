<?php

namespace Battis\OpenAPI\Generator;

use Battis\OpenAPI\Exceptions\OpenAPIException;

class PHPDoc
{
    /** @var string[] $items */
    private array $items = [];

    public function addItem(string $item): void
    {
        array_push($this->items, $item);
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
        $phpdoc = "$indent/**" . PHP_EOL;
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
            while (strlen($item) > $width) {
                $w = $width - strlen("$indent * " . ($wrapped ? $directiveIndent : ""));
                $regex = "/^( \* " . ($wrapped ? $directiveIndent : "") . "((.{1,$w})|(\S{" . $w . ",})))(\s(.*))?$/m";
                preg_match($regex, $item, $match);
                assert(array_key_exists(1, $match), new OpenAPIException(var_export(['item' => $item, 'regex' => $regex,'match' => $match], true)));
                $phpdoc .= $match[1] . PHP_EOL;
                $item =  "$indent * " . $directiveIndent . $match[6];
                $wrapped = true;
            }
            $phpdoc .=  $item . PHP_EOL;
            $prevDirective = $directive;
        }
        $phpdoc .= "$indent */" . PHP_EOL;
        return $phpdoc;
    }
}

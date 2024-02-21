<?php

namespace Battis\OpenAPI\Generator\Classes;

use Battis\OpenAPI\CLI\Logger;
use Battis\OpenAPI\Generator\Exceptions\GeneratorException;
use Battis\PHPGenerator\Method;
use Battis\PHPGenerator\PHPClass;
use Battis\PHPGenerator\Property;

abstract class Writable extends PHPClass
{
    protected string $path;

    public function getPath(): string
    {
        return $this->path;
    }

    public function mergeWith(Writable $other)
    {
        // merge $url properties, taking longest one
        $thisUrlProps = array_filter($this->properties, fn(Property $prop) => $prop->getName() === 'url');
        $thisUrlProp = $thisUrlProps[0] ?? null;

        $otherUrlProps = array_filter($other->properties, fn(Property $prop) => $prop->getName() === 'url');
        $otherUrlProp = $otherUrlProps[0] ?? null;

        if ($thisUrlProp && $otherUrlProp) {
            $base = $thisUrlProp->getDefaultValue();
            $base = substr($base, 1, strlen($base) - 2);
            $extension = $otherUrlProp->getDefaultValue();
            $extension = substr($extension, 1, strlen($extension) - 2);
            if ($base !== $extension) {
                if (strlen($base) > strlen($extension)) {
                    $temp = $base;
                    $base = $extension;
                    $extension = $temp;
                }
                Logger::log("Merging $base and $extension into one endpoint", Logger::WARNING);

                $this->removeProperty($thisUrlProp);
                $other->removeProperty($otherUrlProp);
                $this->addProperty(Property::protectedStatic('url', 'string', null, "\"$extension\""));
            } else {
                $other->removeProperty($otherUrlProp);
            }
        }

        // testing to make sure there are no other duplicate properties
        $thisProperties = array_map(fn(Property $p) => $p->getName(), $this->properties);
        $otherProperties = array_map(fn(Property $p) => $p->getName(), $other->properties);
        $duplicateProperties = array_intersect($thisProperties, $otherProperties);
        assert(count($duplicateProperties) === 0, new GeneratorException("Duplicate properties in merge: " . var_export($duplicateProperties, true)));


        $thisMethods = array_map(fn(Method $m) => $m->getName(), $this->methods);
        $otherMethods = array_map(fn(Method $m) => $m->getName(), $other->methods);
        $duplicateMethods = array_intersect($thisMethods, $otherMethods);
        assert(count($duplicateMethods) === 0, new GeneratorException("Duplicate methods in merge: " . var_export($duplicateMethods, true)));

        $this->uses = array_merge($this->uses, $other->uses);
        $this->properties = array_merge($this->properties, $other->properties);
        $this->methods = array_merge($this->methods, $other->methods);
    }
}

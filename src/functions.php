<?php

namespace Lack\OpenAi;


/**
 * @template T
 * @param class-string<T> $className
 * @param \ReflectionClass|\ReflectionMethod|\ReflectionParameter $refl
 * @return T|null
 */
function get_attribute(string $className, \ReflectionClass|\ReflectionMethod|\ReflectionParameter|\ReflectionFunction|\Closure $refl) : ?object
{
    if ($refl instanceof \Closure) {
        $refl = new \ReflectionFunction($refl);
    }
    foreach ($refl->getAttributes($className) as $attribute) {
        return $attribute->newInstance();
    }
    return null;
}

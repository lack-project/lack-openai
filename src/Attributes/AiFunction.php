<?php

namespace Lack\OpenAi\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
class AiFunction
{

    public function __construct(
        public string $desc = "",
        public string|null $name = null
    ){}
}

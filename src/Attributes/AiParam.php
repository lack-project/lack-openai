<?php

namespace Lack\OpenAi\Attributes;

#[\Attribute(\Attribute::TARGET_PARAMETER)]

class AiParam
{
        public function __construct(
            public string $desc = "",
            public string|null $type = null
        ){}
}

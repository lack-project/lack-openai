<?php

namespace Lack\OpenAi\Attributes;

class AiParam
{
        public function __construct(
            public string $desc = "",
            public string|null $type = null
        ){}
}

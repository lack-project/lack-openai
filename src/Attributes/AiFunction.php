<?php

namespace Lack\OpenAi\Attributes;

class AiFunction
{

    public function __construct(
        public string $desc = "",
        public string|null $name = null
    ){}
}

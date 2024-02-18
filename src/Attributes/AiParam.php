<?php

namespace Lack\OpenAi\Attributes;

#[\Attribute(\Attribute::TARGET_PARAMETER)]

class AiParam
{
        public function __construct(
            public string $desc = "",
            public string|null $type = null,
            /**
             * E.g. allow assoc array:
             * 
             * ["type" => "string", "description" => "The name of the user"]
             * 
             * @var array|null
             */
            public array|null $additionalProperties = null
        ){}
}

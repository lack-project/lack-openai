<?php

namespace Lack\OpenAi\Helper;

use Lack\OpenAi\Attributes\AiFunction;
use Lack\OpenAi\Attributes\AiParam;
use function Lack\OpenAi\get_attribute;

class FunctionDefinitionGenerator
{


    /**
     * Generate the function definition for OpenAI
     *
     * @param $name
     * @param $reflection
     * @return array
     * @throws \ReflectionException
     */
    public function getFunctionDefinition (\ReflectionFunction|\ReflectionMethod $reflection) : array {
        $attr = get_attribute(AiFunction::class, $reflection);

        $definition  = [
            "name" => $attr?->name ?? $reflection->getName() ?? throw new \IntlException("Function name is required. Provide name in AiFunction() attribute."),
            "description" => $attr !== null ? $attr->desc : (string)$reflection->getDocComment(),
            "parameters" => [
                "type" => "object",
                "properties" => [],
                "required" => [],
            ],
        ];

        foreach ($reflection->getParameters() as $parameter) {
            $attr = get_attribute(AiParam::class, $parameter);
            $definition["parameters"]["properties"][$parameter->getName()] = [
                "type" => $attr?->type ?? "string",
                "description" => $attr?->desc ?? ""
            ];
            if ( ! $parameter->isOptional()) {
                $definition["parameters"]["required"][] = $parameter->getName();
            }
        }

        if ($definition["parameters"]["properties"] === [])
            $definition["parameters"]["properties"] = new \stdClass();

        return $definition;

    }
}

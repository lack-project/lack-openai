<?php

namespace Lack\OpenAi\Helper;

class JsonSchemaGenerator
{
    public function convertToJsonSchema(string $classString): string
    {
        $reflectionClass = new \ReflectionClass($classString);
        $properties = $reflectionClass->getProperties();
        $schema = [
            'title' => $reflectionClass->getName(),
            'type' => 'object',
            'properties' => []
        ];

        foreach ($properties as $property) {
            $docComment = $property->getDocComment();
            preg_match('/@type\s+([^\s]+)/', $docComment, $matches);
            $type = $matches[1] ?? 'string'; // Default to string if no type is defined
            $description = $this->extractDescription($docComment);

            $schema['properties'][$property->getName()] = $this->parseType($type, $description);
        }

        return json_encode($schema, JSON_PRETTY_PRINT);
    }

    private function parseType(string $type, string $description = ''): array
    {
        $schema = ['description' => $description];

        if (str_contains($type, '[]')) {
            $schema['type'] = 'array';
            $itemType = str_replace('[]', '', $type);
            $schema['items'] = $this->parseType($itemType);
        } elseif (str_contains($type, '|')) {
            $types = explode('|', $type);
            $schema['oneOf'] = array_map(fn($t) => $this->parseType($t), $types);
        } elseif (class_exists($type)) {
            $schema = $this->convertToJsonSchema($type);
        } else {
            $schema['type'] = $type;
        }

        return $schema;
    }

    private function extractDescription(string $docComment): string
    {
        // Remove /** */, split by new line, and filter out empty lines and lines not starting with an asterisk
        $lines = array_filter(
            explode("\n", str_replace(['/**', '*/'], '', $docComment)),
            fn($line) => trim($line) && strpos(trim($line), '*') === 0
        );

        // Remove lines that are just part of the type definition or empty
        $lines = array_filter($lines, fn($line) => !preg_match('/@(\w+)/', $line));

        // Remove asterisks from the beginning of lines and trim
        return trim(implode("\n", array_map(fn($line) => trim(ltrim($line, '* ')), $lines)));
    }
}

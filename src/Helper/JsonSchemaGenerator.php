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

            preg_match('/@(type|var)\s+(.*)/', $docComment, $matches);


            $type = $matches[2] ?? 'string'; // Default to string if no type is defined
            $type = trim ($type);
            $description = $this->extractDescription($docComment);

            $schema['properties'][$property->getName()] = $this->parseType($type, $description, $reflectionClass->getNamespaceName());
        }

        return json_encode($schema, JSON_PRETTY_PRINT);
    }

    private function parseType(string $type, string $description = '', string $namespace = ''): array
    {
        $schema = ['description' => $description];

        if (str_contains($type, '[]')) {
            $schema['type'] = 'array';
            $itemType = str_replace('[]', '', $type);
            $schema['items'] = $this->parseType($itemType, '', $namespace);
        } elseif (str_contains($type, '|')) {
            $types = explode('|', $type);
            $schema['oneOf'] = array_map(fn($t) => $this->parseType($t, '', $namespace), $types);
        } elseif (preg_match('/array<([a-zA-Z0-9_\\\\]+)\s*,\s*([a-zA-Z0-9_\\\\]+)>/', $type, $matches)) {
            $schema['type'] = 'object';
            $schema['additionalProperties'] = $this->parseType($matches[2], '', $namespace);
        } else {
            $fullType = $this->getFullQualifiedClassName($type, $namespace);
            if (class_exists($fullType)) {
                $schema = json_decode($this->convertToJsonSchema($fullType), true);
            } else {

                $schema['type'] = $this->mapPhpTypeToJsonType($type);
            }
        }

        return $schema;
    }

    private function getFullQualifiedClassName(string $type, string $namespace): string
    {
        if (class_exists($type)) {
            return $type;
        }

        $fullType = $namespace . '\\' . $type;
        if (class_exists($fullType)) {
            return $fullType;
        }

        return $type;
    }

    private function mapPhpTypeToJsonType(string $phpType): string
    {
        $typeMappings = [
            'int' => 'integer',
            'bool' => 'boolean',
            'float' => 'number',
            'string' => 'string',
            'array' => 'array',
            'object' => 'object',
            'null' => 'null',
        ];

        return $typeMappings[$phpType] ?? throw new \InvalidArgumentException("Unrecoginized type '$phpType'. Make sure to use full namespace for Class Types."); // Default to string if no mapping is found
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

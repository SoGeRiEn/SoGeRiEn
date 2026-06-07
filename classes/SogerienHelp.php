<?php
declare(strict_types=1);

trait SogerienClassHelp
{
    /**
     * AI-first class manual.
     *
     * Agents must call help() before reading the class internals. If a class is
     * changed, its public API is reflected here automatically.
     *
     * @return array<string,mixed>
     */
    public function help(): array
    {
        return SogerienHelp::for_object($this);
    }
}

final class SogerienHelp
{
    /**
     * @return array<string,mixed>
     */
    public function help(): array
    {
        return self::for_class(self::class);
    }

    /**
     * @return array<string,mixed>
     */
    public static function for_object(object $object): array
    {
        return self::for_class($object::class);
    }

    /**
     * @return array<string,mixed>
     */
    public static function for_class(string $class): array
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException('Class not found: ' . $class);
        }

        $reflection = new ReflectionClass($class);

        return [
            'class' => $reflection->getName(),
            'file' => (string)$reflection->getFileName(),
            'rules' => self::rules(),
            'usage_order' => [
                'Call help() first.',
                'Use public methods/properties listed in help().',
                'Read class internals only when help() does not answer the task.',
                'After changing class behavior, make sure help() still shows the change.',
            ],
            'public_properties' => self::public_properties($reflection),
            'public_methods' => self::public_methods($reflection),
        ];
    }

    /**
     * @return array<int,string>
     */
    private static function rules(): array
    {
        return [
            'Sogerien is a Universal Engine, not MVC.',
            'Use existing classes first.',
            'Extend universal reusable classes only when existing API is missing.',
            'Keep one-off code in page/.',
            'Move repeated cross-project code to core.',
            'Use declare(strict_types=1) and typed functions.',
            'Store data through universal table sogerien unless the project already has a stricter reason.',
            'Prefer associative JSON structures that are isset-friendly.',
            'Do not write code that does not fit Sogerien style.',
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function public_properties(ReflectionClass $reflection): array
    {
        $properties = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $properties[] = [
                'name' => $property->getName(),
                'type' => self::type_to_string($property->getType()),
            ];
        }

        return $properties;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function public_methods(ReflectionClass $reflection): array
    {
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $methods[] = [
                'name' => $method->getName(),
                'static' => $method->isStatic(),
                'returns' => self::type_to_string($method->getReturnType()),
                'params' => self::method_params($method),
            ];
        }

        usort(
            $methods,
            static fn(array $a, array $b): int => strcmp((string)$a['name'], (string)$b['name'])
        );

        return $methods;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function method_params(ReflectionMethod $method): array
    {
        $params = [];

        foreach ($method->getParameters() as $param) {
            $params[] = [
                'name' => $param->getName(),
                'type' => self::type_to_string($param->getType()),
                'optional' => $param->isOptional(),
                'default' => self::param_default($param),
            ];
        }

        return $params;
    }

    private static function param_default(ReflectionParameter $param): mixed
    {
        if (!$param->isDefaultValueAvailable()) {
            return null;
        }

        if ($param->isDefaultValueConstant()) {
            return $param->getDefaultValueConstantName();
        }

        return $param->getDefaultValue();
    }

    private static function type_to_string(?ReflectionType $type): string
    {
        if ($type === null) {
            return 'mixed';
        }

        return (string)$type;
    }
}

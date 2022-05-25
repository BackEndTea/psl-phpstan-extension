<?php

declare(strict_types=1);

namespace PHPStan\Type\Psl;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\ErrorType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;
use Psl\Type\TypeInterface;

use function array_keys;
use function array_map;
use function array_values;
use function is_string;

class PSLTypeSpecifyingExtension implements DynamicFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return $functionReflection->getName() === 'Psl\Type\shape';
    }

    public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): ?Type
    {
        $arg = $scope->getType($functionCall->getArgs()[0]->value);

        if (! $arg instanceof ConstantArrayType) {
            return null;
        }

        $typeInterfaceType = new ObjectType(TypeInterface::class);

        $properties   = [];
        $optionalKeys = [];
        foreach ($arg->getKeyTypes() as $i => $key) {
            $realKey   = $key->getValue();
            $valueType = $arg->getOffsetValueType($key);
			recheck:

			if($valueType instanceof GenericObjectType && $valueType->accepts($typeInterfaceType,$scope->isDeclareStrictTypes())->yes()) {
				$properties[$realKey] = $valueType->getTypes()[0];
				continue;
			}

			if ($valueType instanceof UnionType) {
				$valueType      = $valueType->getTypes()[0];
				$optionalKeys[] = $i;
				goto recheck;
			}

			return new ErrorType();
        }

        $keys = array_map(
            static fn ($key) => is_string($key) ? new ConstantStringType($key) : new ConstantIntegerType($key),
            array_keys($properties)
        );

        return new GenericObjectType(
            TypeInterface::class,
            [
                new ConstantArrayType(
                    $keys,
                    array_values($properties),
                    [0],
                    $optionalKeys
                ),
            ]
        );
    }
}

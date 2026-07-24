<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\CodeQuality\Rector\Class_\CompleteDynamicPropertiesRector;
use Rector\CodeQuality\Rector\Isset_\IssetOnPropertyObjectToPropertyExistsRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\For_\RemoveDeadIfForeachForRector;
use Rector\DeadCode\Rector\For_\RemoveDeadLoopRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveDuplicatedReturnSelfDocblockRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveMixedDocblockOverruledByNativeTypeRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveReturnTagIncompatibleWithNativeTypeRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedConstructorParamRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessUnionReturnDocblockRector;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\Php70\Rector\StmtsAwareInterface\IfIssetToCoalescingRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;
use Rector\TypeDeclaration\Rector\Class_\TypedPropertyFromCreateMockAssignRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ObjectParamTypeByMethodCallTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnNeverTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromMockObjectRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictFluentReturnRector;
use Rector\TypeDeclaration\Rector\FuncCall\AddArrayFunctionClosureParamTypeRector;
use Rector\TypeDeclaration\Rector\FunctionLike\AddClosureParamTypeForArrayMapRector;
use Rector\TypeDeclaration\Rector\FunctionLike\AddClosureParamTypeForArrayReduceRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictSetUpRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        ReadOnlyPropertyRector::class,
        EncapsedStringsToSprintfRector::class,
        DisallowedEmptyRuleFixerRector::class,
        ClassPropertyAssignToConstructorPromotionRector::class,
        AddClosureParamTypeForArrayMapRector::class,
        AddClosureParamTypeForArrayReduceRector::class,
        AddArrayFunctionClosureParamTypeRector::class,
        RemoveDeadLoopRector::class,
        RemoveDeadIfForeachForRector::class,
        RemoveUselessReturnTagRector::class,
        RemoveUselessParamTagRector::class,
        RemoveUselessVarTagRector::class,
        RemoveUnusedConstructorParamRector::class,
        RemoveUselessUnionReturnDocblockRector::class,
        RemoveDuplicatedReturnSelfDocblockRector::class,
        RemoveMixedDocblockOverruledByNativeTypeRector::class,
        RemoveReturnTagIncompatibleWithNativeTypeRector::class,
        ReturnTypeFromStrictFluentReturnRector::class,
        ReturnNeverTypeRector::class,
        TypedPropertyFromCreateMockAssignRector::class,
        TypedPropertyFromStrictSetUpRector::class,
        ReturnTypeFromMockObjectRector::class,
        TypedPropertyFromAssignsRector::class,
        ObjectParamTypeByMethodCallTypeRector::class,
        CompleteDynamicPropertiesRector::class,
        SafeDeclareStrictTypesRector::class,
        IssetOnPropertyObjectToPropertyExistsRector::class,
        IfIssetToCoalescingRector::class,
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        earlyReturn: true,
    )
    ->withPhpSets(php81: true);

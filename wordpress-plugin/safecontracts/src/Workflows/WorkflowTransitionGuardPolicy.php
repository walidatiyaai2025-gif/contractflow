<?php

declare(strict_types=1);

namespace SafeContracts\Workflows;

use InvalidArgumentException;

final class WorkflowTransitionGuardPolicy
{
    public const DYNAMIC_FIELDS_READY = 'dynamic_fields_ready';
    public const MAX_GUARDS_PER_TRANSITION = 8;

    /** @return list<string> */
    public static function guardTypes(): array
    {
        return [self::DYNAMIC_FIELDS_READY];
    }

    /** @return list<string> */
    public static function normalizeGuardTypes(array $guardTypes): array
    {
        if (count($guardTypes) > self::MAX_GUARDS_PER_TRANSITION) {
            throw new InvalidArgumentException('Workflow transition guard count exceeds the supported limit.');
        }
        $normalized = [];
        foreach ($guardTypes as $guardType) {
            if (! is_string($guardType)) {
                throw new InvalidArgumentException('Workflow transition guards accept guard-type strings only; parameters are not supported.');
            }
            $guardType = strtolower(trim($guardType));
            if (! in_array($guardType, self::guardTypes(), true)) {
                throw new InvalidArgumentException('Workflow transition guard type is not supported.');
            }
            if (in_array($guardType, $normalized, true)) {
                throw new InvalidArgumentException('Workflow transition guard types must be unique per transition.');
            }
            $normalized[] = $guardType;
        }
        return $normalized;
    }
}

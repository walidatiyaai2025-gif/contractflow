<?php

declare(strict_types=1);

namespace SafeContracts\Approvals;

use InvalidArgumentException;
use SafeContracts\Tenancy\TenantRolePolicy;
use SafeContracts\Workflows\WorkflowDefinitionPolicy;

final class ApprovalRoutePolicy
{
    public const POLICY_ALL = 'all';
    public const POLICY_QUORUM = 'quorum';
    public const SELECTOR_TENANT_USER = 'tenant_user';
    public const SELECTOR_TENANT_ROLE = 'tenant_role';
    public const MAX_STAGES = 32;
    public const MAX_SELECTORS_PER_STAGE = 64;
    public const MAX_SELECTORS_PER_ROUTE = 256;
    public const MAX_NAME_BYTES = 191;

    /** @return list<string> */
    public static function decisionPolicies(): array
    {
        return [self::POLICY_ALL, self::POLICY_QUORUM];
    }

    /** @return list<string> */
    public static function selectorTypes(): array
    {
        return [self::SELECTOR_TENANT_USER, self::SELECTOR_TENANT_ROLE];
    }

    /**
     * @param list<mixed> $stagesInput
     * @return list<array{
     *   stage_code:string,
     *   name:string,
     *   position_no:int,
     *   decision_policy:string,
     *   required_approvals:int,
     *   selectors:list<array{position_no:int,selector_type:string,selector_user_id:?int,selector_role_code:?string,selector_key:string}>
     * }>
     */
    public static function normalizeRoute(array $stagesInput): array
    {
        if (! array_is_list($stagesInput)) {
            throw new InvalidArgumentException('Approval Route stages must be an ordered list.');
        }
        if (count($stagesInput) > self::MAX_STAGES) {
            throw new InvalidArgumentException('Approval Route stage count exceeds the supported limit.');
        }

        $normalized = [];
        $stageCodes = [];
        $totalSelectors = 0;
        foreach ($stagesInput as $index => $input) {
            if (! is_array($input) || array_is_list($input)) {
                throw new InvalidArgumentException('Each Approval Route stage must be an object.');
            }
            self::assertKeys($input, ['stage_code', 'name', 'decision_policy', 'required_approvals', 'selectors']);
            $stageCode = WorkflowDefinitionPolicy::normalizeCode((string) ($input['stage_code'] ?? ''), 'Approval stage code');
            if (isset($stageCodes[$stageCode])) {
                throw new InvalidArgumentException('Approval stage codes must be unique within a route.');
            }
            $stageCodes[$stageCode] = true;

            $name = self::text($input['name'] ?? '', 'Approval stage name');
            $decisionPolicy = strtolower(trim((string) ($input['decision_policy'] ?? '')));
            if (! in_array($decisionPolicy, self::decisionPolicies(), true)) {
                throw new InvalidArgumentException('Approval stage decision policy is not supported.');
            }
            $selectors = self::normalizeSelectors($input['selectors'] ?? null);
            $selectorCount = count($selectors);
            if ($selectorCount < 1) {
                throw new InvalidArgumentException('Approval stage must contain at least one selector.');
            }
            $totalSelectors += $selectorCount;
            if ($totalSelectors > self::MAX_SELECTORS_PER_ROUTE) {
                throw new InvalidArgumentException('Approval Route selector count exceeds the supported limit.');
            }

            $required = $input['required_approvals'] ?? null;
            if (! is_int($required)) {
                throw new InvalidArgumentException('Approval stage required_approvals must be an integer.');
            }
            if ($decisionPolicy === self::POLICY_ALL) {
                if ($required !== 0) {
                    throw new InvalidArgumentException('Approval stage policy all requires canonical required_approvals = 0.');
                }
            } elseif ($required < 1 || $required > $selectorCount) {
                throw new InvalidArgumentException('Approval stage quorum must be between 1 and the selector count.');
            }

            $normalized[] = [
                'stage_code' => $stageCode,
                'name' => $name,
                'position_no' => $index + 1,
                'decision_policy' => $decisionPolicy,
                'required_approvals' => $decisionPolicy === self::POLICY_ALL ? 0 : $required,
                'selectors' => $selectors,
            ];
        }
        return $normalized;
    }

    /** @return list<array{position_no:int,selector_type:string,selector_user_id:?int,selector_role_code:?string,selector_key:string}> */
    private static function normalizeSelectors(mixed $selectorsInput): array
    {
        if (! is_array($selectorsInput) || ! array_is_list($selectorsInput)) {
            throw new InvalidArgumentException('Approval stage selectors must be an ordered list.');
        }
        if (count($selectorsInput) > self::MAX_SELECTORS_PER_STAGE) {
            throw new InvalidArgumentException('Approval stage selector count exceeds the supported limit.');
        }
        $selectors = [];
        $keys = [];
        foreach ($selectorsInput as $index => $selector) {
            if (! is_array($selector) || array_is_list($selector)) {
                throw new InvalidArgumentException('Each Approval selector must be an object.');
            }
            if (! array_key_exists('selector_type', $selector)) {
                throw new InvalidArgumentException('Approval selector is missing selector_type.');
            }
            $type = strtolower(trim((string) $selector['selector_type']));
            if (! in_array($type, self::selectorTypes(), true)) {
                throw new InvalidArgumentException('Approval selector type is not supported.');
            }

            $userId = null;
            $roleCode = null;
            if ($type === self::SELECTOR_TENANT_USER) {
                self::assertKeys($selector, ['selector_type', 'user_id']);
                if (! is_int($selector['user_id']) || $selector['user_id'] <= 0) {
                    throw new InvalidArgumentException('Approval tenant_user selector requires a positive integer user_id.');
                }
                $userId = $selector['user_id'];
                $key = 'user:' . $userId;
            } else {
                self::assertKeys($selector, ['selector_type', 'role_code']);
                if (! is_string($selector['role_code'])) {
                    throw new InvalidArgumentException('Approval tenant_role selector requires a role_code string.');
                }
                $roleCode = TenantRolePolicy::normalize($selector['role_code']);
                if (! TenantRolePolicy::isAssignable($roleCode)) {
                    throw new InvalidArgumentException('Approval tenant_role selector requires an assignable Enterprise tenant role.');
                }
                $key = 'role:' . $roleCode;
            }
            if (isset($keys[$key])) {
                throw new InvalidArgumentException('Approval selectors must be unique within a stage.');
            }
            $keys[$key] = true;
            $selectors[] = [
                'position_no' => $index + 1,
                'selector_type' => $type,
                'selector_user_id' => $userId,
                'selector_role_code' => $roleCode,
                'selector_key' => $key,
            ];
        }
        return $selectors;
    }

    /** @param array<string,mixed> $input @param list<string> $allowed */
    private static function assertKeys(array $input, array $allowed): void
    {
        foreach (array_keys($input) as $key) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('Unsupported Approval Route property.');
            }
        }
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $input)) {
                throw new InvalidArgumentException('Approval Route object is missing a required property.');
            }
        }
    }

    private static function text(mixed $value, string $label): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException("{$label} must be a string.");
        }
        $value = trim(strip_tags($value));
        if ($value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }
        if (strlen($value) > self::MAX_NAME_BYTES) {
            throw new InvalidArgumentException("{$label} is too long.");
        }
        return $value;
    }
}

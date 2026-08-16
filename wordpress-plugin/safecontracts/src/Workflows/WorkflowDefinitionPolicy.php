<?php

declare(strict_types=1);

namespace SafeContracts\Workflows;

use InvalidArgumentException;

final class WorkflowDefinitionPolicy
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const VERSION_DRAFT = 'draft';
    public const VERSION_PUBLISHED = 'published';
    public const MAX_STATES = 64;
    public const MAX_TRANSITIONS = 256;
    public const MAX_CODE_BYTES = 100;
    public const MAX_NAME_BYTES = 191;
    public const MAX_DESCRIPTION_BYTES = 5000;
    public const MAX_SORT_ORDER = 1000000;

    /** @return list<string> */
    public static function statuses(): array
    {
        return [self::STATUS_ACTIVE, self::STATUS_INACTIVE];
    }

    public static function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if (! in_array($status, self::statuses(), true)) {
            throw new InvalidArgumentException('Workflow status is not supported.');
        }
        return $status;
    }

    public static function normalizeCode(string $code, string $label = 'Workflow code'): string
    {
        $code = strtolower(trim($code));
        $code = preg_replace('/\s+/', '_', $code) ?? '';
        if ($code === '' || strlen($code) > self::MAX_CODE_BYTES || preg_match('/^[a-z0-9][a-z0-9._-]*$/', $code) !== 1) {
            throw new InvalidArgumentException("{$label} must be 1-100 lowercase machine-code characters using letters, numbers, dot, underscore or hyphen.");
        }
        return $code;
    }

    /**
     * @param list<mixed> $statesInput
     * @param list<mixed> $transitionsInput
     * @return array{states:list<array<string,mixed>>,transitions:list<array<string,mixed>>,initial_state_code:string}
     */
    public static function normalizeGraph(array $statesInput, array $transitionsInput): array
    {
        if (! array_is_list($statesInput) || ! array_is_list($transitionsInput)) {
            throw new InvalidArgumentException('Workflow states and transitions must be ordered lists.');
        }
        $stateCount = count($statesInput);
        if ($stateCount < 1 || $stateCount > self::MAX_STATES) {
            throw new InvalidArgumentException('Workflow graph must contain between 1 and 64 states.');
        }
        if (count($transitionsInput) > self::MAX_TRANSITIONS) {
            throw new InvalidArgumentException('Workflow graph must contain no more than 256 transitions.');
        }

        $states = [];
        $statesByCode = [];
        $initialCodes = [];
        foreach ($statesInput as $input) {
            if (! is_array($input) || array_is_list($input)) {
                throw new InvalidArgumentException('Each Workflow state must be an object.');
            }
            self::assertKeys($input, ['state_code', 'name', 'description', 'sort_order', 'is_initial', 'is_terminal']);
            $code = self::normalizeCode((string) ($input['state_code'] ?? ''), 'Workflow state code');
            if (isset($statesByCode[$code])) {
                throw new InvalidArgumentException('Workflow state codes must be unique within the version.');
            }
            $isInitial = self::boolean($input['is_initial'] ?? null, 'Workflow state is_initial');
            $isTerminal = self::boolean($input['is_terminal'] ?? null, 'Workflow state is_terminal');
            $state = [
                'state_code' => $code,
                'name' => self::text($input['name'] ?? '', self::MAX_NAME_BYTES, true, 'Workflow state name'),
                'description' => self::text($input['description'] ?? '', self::MAX_DESCRIPTION_BYTES, false, 'Workflow state description'),
                'sort_order' => self::sortOrder($input['sort_order'] ?? null, 'Workflow state sort_order'),
                'is_initial' => $isInitial,
                'is_terminal' => $isTerminal,
            ];
            if ($isInitial) {
                $initialCodes[] = $code;
            }
            $statesByCode[$code] = $state;
            $states[] = $state;
        }
        if (count($initialCodes) !== 1) {
            throw new InvalidArgumentException('Workflow graph must contain exactly one initial state.');
        }
        $initialCode = $initialCodes[0];

        $transitions = [];
        $transitionCodes = [];
        $adjacency = [];
        foreach ($transitionsInput as $input) {
            if (! is_array($input) || array_is_list($input)) {
                throw new InvalidArgumentException('Each Workflow transition must be an object.');
            }
            self::assertKeys($input, ['transition_code', 'source_state_code', 'destination_state_code', 'name', 'description', 'sort_order']);
            $code = self::normalizeCode((string) ($input['transition_code'] ?? ''), 'Workflow transition code');
            if (isset($transitionCodes[$code])) {
                throw new InvalidArgumentException('Workflow transition codes must be unique within the version.');
            }
            $source = self::normalizeCode((string) ($input['source_state_code'] ?? ''), 'Workflow transition source state code');
            $destination = self::normalizeCode((string) ($input['destination_state_code'] ?? ''), 'Workflow transition destination state code');
            if (! isset($statesByCode[$source]) || ! isset($statesByCode[$destination])) {
                throw new InvalidArgumentException('Workflow transition endpoints must exist in the same Workflow Version.');
            }
            if ($source === $destination) {
                throw new InvalidArgumentException('Workflow self-transitions are not supported in this foundation.');
            }
            if ((bool) $statesByCode[$source]['is_terminal']) {
                throw new InvalidArgumentException('Workflow terminal states cannot have outgoing transitions.');
            }
            $transition = [
                'transition_code' => $code,
                'source_state_code' => $source,
                'destination_state_code' => $destination,
                'name' => self::text($input['name'] ?? '', self::MAX_NAME_BYTES, true, 'Workflow transition name'),
                'description' => self::text($input['description'] ?? '', self::MAX_DESCRIPTION_BYTES, false, 'Workflow transition description'),
                'sort_order' => self::sortOrder($input['sort_order'] ?? null, 'Workflow transition sort_order'),
            ];
            $transitionCodes[$code] = true;
            $adjacency[$source][] = $destination;
            $transitions[] = $transition;
        }

        $reachable = [$initialCode => true];
        $queue = [$initialCode];
        $cursor = 0;
        while ($cursor < count($queue)) {
            if (count($reachable) > self::MAX_STATES) {
                throw new InvalidArgumentException('Workflow reachability traversal exceeded the bounded state limit.');
            }
            $node = $queue[$cursor++];
            foreach ($adjacency[$node] ?? [] as $destination) {
                if (! isset($reachable[$destination])) {
                    $reachable[$destination] = true;
                    $queue[] = $destination;
                }
            }
        }
        if (count($reachable) !== $stateCount) {
            throw new InvalidArgumentException('Every non-initial Workflow state must be reachable from the initial state.');
        }

        return [
            'states' => $states,
            'transitions' => $transitions,
            'initial_state_code' => $initialCode,
        ];
    }

    /** @param array<string,mixed> $input @param list<string> $allowed */
    private static function assertKeys(array $input, array $allowed): void
    {
        foreach (array_keys($input) as $key) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('Unsupported Workflow graph property.');
            }
        }
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $input)) {
                throw new InvalidArgumentException('Workflow graph object is missing a required property.');
            }
        }
    }

    private static function boolean(mixed $value, string $label): bool
    {
        if (! is_bool($value)) {
            throw new InvalidArgumentException("{$label} must be a boolean.");
        }
        return $value;
    }

    private static function sortOrder(mixed $value, string $label): int
    {
        if (! is_int($value) || $value < 0 || $value > self::MAX_SORT_ORDER) {
            throw new InvalidArgumentException("{$label} must be an integer between 0 and 1000000.");
        }
        return $value;
    }

    private static function text(mixed $value, int $max, bool $required, string $label): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException("{$label} must be a string.");
        }
        $value = trim(strip_tags($value));
        if ($required && $value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }
        if (strlen($value) > $max) {
            throw new InvalidArgumentException("{$label} is too long.");
        }
        return $value;
    }
}

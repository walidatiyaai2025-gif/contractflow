<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use InvalidArgumentException;
use WP_REST_Request;

final class ApiListQuery
{
    public const BOUNDED_WINDOW = 500;

    /**
     * @param list<string> $filterFields
     * @param list<string> $allowedSorts
     * @return array{filters:array<string,mixed>,page:int,per_page:int,sort:string,order:string}
     */
    public static function parse(
        WP_REST_Request $request,
        array $filterFields,
        array $allowedSorts,
        string $defaultSort,
        string $defaultOrder = 'asc'
    ): array {
        if (! in_array($defaultSort, $allowedSorts, true)) {
            throw new InvalidArgumentException('Default REST sort field is not allowed.');
        }
        if (! in_array($defaultOrder, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Default REST sort order is invalid.');
        }

        $allowed = array_values(array_unique(array_merge($filterFields, ['page', 'per_page', 'sort', 'order'])));
        $params = ApiAbuseGuard::safeParams($request, $allowed);
        $base = ApiRequest::listQuery($request);
        $sort = ApiAbuseGuard::optionalString($params, 'sort', $defaultSort);
        if (! in_array($sort, $allowedSorts, true)) {
            throw new InvalidArgumentException('sort is not supported for this endpoint.');
        }
        $order = strtolower(ApiAbuseGuard::optionalString($params, 'order', $defaultOrder, 8));
        if (! in_array($order, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('order must be asc or desc.');
        }

        return [
            'filters' => $base['filters'],
            'page' => $base['page'],
            'per_page' => $base['per_page'],
            'sort' => $sort,
            'order' => $order,
        ];
    }

    /**
     * @param list<string> $allowedSorts
     * @param list<string> $extraAllowed
     * @return array{page:int,per_page:int,sort:string,order:string}
     */
    public static function pagination(
        WP_REST_Request $request,
        array $allowedSorts,
        string $defaultSort,
        string $defaultOrder = 'asc',
        array $extraAllowed = []
    ): array {
        if (! in_array($defaultSort, $allowedSorts, true)) {
            throw new InvalidArgumentException('Default REST sort field is not allowed.');
        }
        $params = ApiAbuseGuard::safeParams(
            $request,
            array_values(array_unique(array_merge($extraAllowed, ['page', 'per_page', 'sort', 'order'])))
        );
        $page = ApiRequest::pagination($request);
        $sort = ApiAbuseGuard::optionalString($params, 'sort', $defaultSort);
        if (! in_array($sort, $allowedSorts, true)) {
            throw new InvalidArgumentException('sort is not supported for this endpoint.');
        }
        $order = strtolower(ApiAbuseGuard::optionalString($params, 'order', $defaultOrder, 8));
        if (! in_array($order, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('order must be asc or desc.');
        }
        return [
            'page' => $page['page'],
            'per_page' => $page['per_page'],
            'sort' => $sort,
            'order' => $order,
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    public static function sortRows(array $rows, string $field, string $order): array
    {
        $decorated = [];
        foreach ($rows as $index => $row) {
            $decorated[] = ['index' => $index, 'row' => $row];
        }

        usort($decorated, static function (array $left, array $right) use ($field, $order): int {
            $comparison = self::compare($left['row'][$field] ?? null, $right['row'][$field] ?? null);
            if ($comparison === 0) {
                $leftId = $left['row']['id'] ?? $left['row']['payment_id'] ?? null;
                $rightId = $right['row']['id'] ?? $right['row']['payment_id'] ?? null;
                $comparison = self::compare($leftId, $rightId);
            }
            if ($comparison === 0) {
                $comparison = $left['index'] <=> $right['index'];
            }
            return $order === 'desc' ? -$comparison : $comparison;
        });

        return array_values(array_map(static fn (array $item): array => $item['row'], $decorated));
    }

    private static function compare(mixed $left, mixed $right): int
    {
        if ($left === $right) {
            return 0;
        }
        if ($left === null) {
            return 1;
        }
        if ($right === null) {
            return -1;
        }
        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left <=> (float) $right;
        }
        return strnatcasecmp((string) $left, (string) $right);
    }
}

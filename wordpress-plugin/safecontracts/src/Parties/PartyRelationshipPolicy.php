<?php

declare(strict_types=1);

namespace SafeContracts\Parties;

final class PartyRelationshipPolicy
{
    public const PARENT_OF = 'parent_of';
    public const REPRESENTS = 'represents';
    public const GUARANTEES_FOR = 'guarantees_for';
    public const CONTACT_FOR = 'contact_for';
    public const OWNS = 'owns';
    public const AFFILIATED_WITH = 'affiliated_with';

    /**
     * @return array<string,array{inverse_code:string,symmetric:bool}>
     */
    public static function definitions(): array
    {
        return [
            self::PARENT_OF => [
                'inverse_code' => 'child_of',
                'symmetric' => false,
            ],
            self::REPRESENTS => [
                'inverse_code' => 'represented_by',
                'symmetric' => false,
            ],
            self::GUARANTEES_FOR => [
                'inverse_code' => 'guaranteed_by',
                'symmetric' => false,
            ],
            self::CONTACT_FOR => [
                'inverse_code' => 'has_contact',
                'symmetric' => false,
            ],
            self::OWNS => [
                'inverse_code' => 'owned_by',
                'symmetric' => false,
            ],
            self::AFFILIATED_WITH => [
                'inverse_code' => self::AFFILIATED_WITH,
                'symmetric' => true,
            ],
        ];
    }

    public static function normalize(string $code): string
    {
        return strtolower(trim($code));
    }

    public static function isSupported(string $code): bool
    {
        return isset(self::definitions()[self::normalize($code)]);
    }

    public static function isSymmetric(string $code): bool
    {
        $definition = self::definitions()[self::normalize($code)] ?? null;
        return is_array($definition) && $definition['symmetric'] === true;
    }

    public static function inverseCode(string $code): ?string
    {
        $definition = self::definitions()[self::normalize($code)] ?? null;
        return is_array($definition) ? $definition['inverse_code'] : null;
    }
}

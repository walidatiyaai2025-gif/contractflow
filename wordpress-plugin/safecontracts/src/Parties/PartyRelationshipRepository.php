<?php

declare(strict_types=1);

namespace SafeContracts\Parties;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class PartyRelationshipRepository
{
    /** @return list<array<string,mixed>> */
    public function outgoing(int $partyId): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $relationships = $wpdb->prefix . 'safecontracts_party_relationships';
        $parties = $wpdb->prefix . 'safecontracts_parties';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.id, r.source_party_id, r.target_party_id, r.relationship_code, r.status, r.valid_from, r.valid_to, r.metadata_json, r.assigned_by, r.revoked_by, r.created_at, r.updated_at, r.revoked_at
             FROM {$relationships} r
             INNER JOIN {$parties} source_party ON source_party.id = r.source_party_id AND source_party.tenant_id = r.tenant_id
             INNER JOIN {$parties} target_party ON target_party.id = r.target_party_id AND target_party.tenant_id = r.tenant_id
             WHERE r.tenant_id = %d AND r.source_party_id = %d AND r.status = 'active'
             ORDER BY r.relationship_code ASC, r.target_party_id ASC, r.id ASC",
            $tenantId,
            $partyId
        ), ARRAY_A);

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /** @return list<array<string,mixed>> */
    public function incoming(int $partyId): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $relationships = $wpdb->prefix . 'safecontracts_party_relationships';
        $parties = $wpdb->prefix . 'safecontracts_parties';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.id, r.source_party_id, r.target_party_id, r.relationship_code, r.status, r.valid_from, r.valid_to, r.metadata_json, r.assigned_by, r.revoked_by, r.created_at, r.updated_at, r.revoked_at
             FROM {$relationships} r
             INNER JOIN {$parties} source_party ON source_party.id = r.source_party_id AND source_party.tenant_id = r.tenant_id
             INNER JOIN {$parties} target_party ON target_party.id = r.target_party_id AND target_party.tenant_id = r.tenant_id
             WHERE r.tenant_id = %d AND r.target_party_id = %d AND r.status = 'active'
             ORDER BY r.relationship_code ASC, r.source_party_id ASC, r.id ASC",
            $tenantId,
            $partyId
        ), ARRAY_A);

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    public function assign(
        int $sourcePartyId,
        int $targetPartyId,
        string $relationshipCode,
        string $validFrom,
        string $validTo,
        string $metadataJson,
        int $actorId
    ): void {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_party_relationships';
        $validFromSql = $this->nullableSql($wpdb, $validFrom);
        $validToSql = $this->nullableSql($wpdb, $validTo);
        $metadataSql = $this->nullableSql($wpdb, $metadataJson);

        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (tenant_id, source_party_id, target_party_id, relationship_code, status, valid_from, valid_to, metadata_json, assigned_by, revoked_by, created_at, updated_at, revoked_at)
             VALUES (%d, %d, %d, %s, 'active', {$validFromSql}, {$validToSql}, {$metadataSql}, %d, NULL, UTC_TIMESTAMP(), UTC_TIMESTAMP(), NULL)
             ON DUPLICATE KEY UPDATE
                valid_from = IF(status = 'active', valid_from, VALUES(valid_from)),
                valid_to = IF(status = 'active', valid_to, VALUES(valid_to)),
                metadata_json = IF(status = 'active', metadata_json, VALUES(metadata_json)),
                assigned_by = IF(status = 'active', assigned_by, VALUES(assigned_by)),
                revoked_by = IF(status = 'active', revoked_by, NULL),
                revoked_at = IF(status = 'active', revoked_at, NULL),
                updated_at = IF(status = 'active', updated_at, UTC_TIMESTAMP()),
                status = 'active'",
            $tenantId,
            $sourcePartyId,
            $targetPartyId,
            $relationshipCode,
            $actorId
        );

        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to assign Enterprise Party relationship.');
        }
    }

    public function revoke(int $sourcePartyId, int $targetPartyId, string $relationshipCode, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_party_relationships';
        $sql = $wpdb->prepare(
            "UPDATE {$table}
             SET revoked_by = IF(status = 'active', %d, revoked_by),
                 revoked_at = IF(status = 'active', UTC_TIMESTAMP(), revoked_at),
                 updated_at = IF(status = 'active', UTC_TIMESTAMP(), updated_at),
                 status = 'inactive'
             WHERE tenant_id = %d AND source_party_id = %d AND target_party_id = %d AND relationship_code = %s",
            $actorId,
            $tenantId,
            $sourcePartyId,
            $targetPartyId,
            $relationshipCode
        );

        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to revoke Enterprise Party relationship.');
        }
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Party relationship access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }

    private function nullableSql(object $wpdb, string $value): string
    {
        $value = trim($value);
        return $value === '' ? 'NULL' : $wpdb->prepare('%s', $value);
    }
}

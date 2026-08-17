<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantAuthorization;
use SafeContracts\Tenancy\TenantContextStore;
use UnexpectedValueException;

final class ContractFinancialScheduleCoverageService
{
    public function __construct(private ?ContractFinancialScheduleCoverageRepository $repository = null)
    {
        $this->repository ??= new ContractFinancialScheduleCoverageRepository();
    }

    /**
     * @return array{
     *   currency:string,
     *   contract_net_value:string,
     *   scheduled_total:string,
     *   voided_scheduled_total:string,
     *   schedule_delta:string,
     *   coverage_state:string,
     *   scheduled_entry_count:int,
     *   voided_entry_count:int
     * }
     */
    public function reconcile(int $contractId): array
    {
        if ($contractId <= 0) {
            throw new InvalidArgumentException('Contract ID must be positive.');
        }
        $this->authorize();

        $snapshot = $this->repository->snapshot($contractId, function (array $lockedContract): void {
            $this->assertScope($lockedContract);
        });
        $financial = ContractFinancialReconciliationCalculator::reconcile($snapshot['financial']);
        $currency = CurrencyCode::from($financial['currency']);
        $zero = Money::of('0', $currency);
        $scheduled = $zero;
        $voidedScheduled = $zero;
        $scheduledCount = 0;
        $voidedCount = 0;

        foreach ($snapshot['schedules'] as $schedule) {
            if (! is_array($schedule)) {
                throw new UnexpectedValueException('Enterprise schedule coverage contains an invalid schedule snapshot row.');
            }
            $state = ContractFinancialPaymentSchedulePolicy::normalizeState($schedule['state'] ?? null);
            $money = Money::of($schedule['amount'] ?? null, $schedule['currency'] ?? null);
            if (! $money->currency()->equals($currency) || $money->compare($zero) <= 0) {
                throw new UnexpectedValueException('Enterprise schedule coverage contains an invalid schedule amount or currency.');
            }
            if ($state === ContractFinancialPaymentSchedulePolicy::STATE_VOIDED) {
                $voidedScheduled = $voidedScheduled->add($money);
                $voidedCount++;
                continue;
            }
            if ($state !== ContractFinancialPaymentSchedulePolicy::STATE_SCHEDULED) {
                throw new UnexpectedValueException('Enterprise schedule coverage contains an unsupported current schedule state.');
            }
            $scheduled = $scheduled->add($money);
            $scheduledCount++;
        }

        $contractNet = Money::of($financial['net_value'], $currency);
        $delta = $scheduled->subtract($contractNet);

        return [
            'currency' => $currency->value(),
            'contract_net_value' => $contractNet->amount(),
            'scheduled_total' => $scheduled->amount(),
            'voided_scheduled_total' => $voidedScheduled->amount(),
            'schedule_delta' => $delta->amount(),
            'coverage_state' => ContractFinancialScheduleCoveragePolicy::derive($scheduled, $contractNet),
            'scheduled_entry_count' => $scheduledCount,
            'voided_entry_count' => $voidedCount,
        ];
    }

    private function authorize(): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise schedule coverage requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can(Capabilities::ACCESS) || ! TenantAuthorization::allowsCapability(Capabilities::ACCESS)) {
            throw new DomainException('The current tenant role does not allow Enterprise schedule coverage access.');
        }
        if (get_current_user_id() <= 0) {
            throw new DomainException('An authenticated tenant user is required.');
        }
    }

    /** @param array<string,mixed> $contract */
    private function assertScope(array $contract): void
    {
        if (current_user_can(Capabilities::VIEW_ALL)) {
            return;
        }
        $accountantUserId = $this->nullableInt($contract['accountant_user_id'] ?? null);
        if (current_user_can(Capabilities::VIEW_ASSIGNED)
            && $accountantUserId !== null
            && $accountantUserId === get_current_user_id()) {
            return;
        }
        throw new DomainException('Contract is outside the current user data scope.');
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }
}

<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\QuotationStatus;
use Illuminate\Support\Collection;

class QuotationWorkflowService
{
    /**
     * @var array<string, list<string>>
     */
    private array $transitions = [
        'Requested for Quotation' => ['Drafted', 'Rejected'],
        'Drafted' => ['Sent', 'Awaiting Customer Response', 'Rejected'],
        'Sent' => ['Awaiting Customer Response', 'Under Negotiation', 'Accepted', 'Rejected'],
        'Awaiting Customer Response' => ['Under Negotiation', 'Sent', 'Accepted', 'Rejected'],
        'Under Negotiation' => ['Approved', 'Sent', 'Rejected'],
        'Approved' => ['Accepted', 'Rejected'],
        'Accepted' => [],
        'Rejected' => ['Drafted'],
    ];

    public function isTerminal(string $name): bool
    {
        return in_array($name, ['Accepted', 'Rejected'], true);
    }

    public function isValidTransition(string $from, string $to, bool $fromIsSystem, bool $toIsSystem): bool
    {
        if ($this->isTerminal($from)) {
            return in_array($to, $this->transitions[$from] ?? [], true);
        }

        if (! $fromIsSystem || ! $toIsSystem) {
            return ! $this->isTerminal($to);
        }

        return in_array($to, $this->transitions[$from] ?? [], true);
    }

    /**
     * @param  Collection<int, QuotationStatus>  $allStatuses
     * @return Collection<int, QuotationStatus>
     */
    public function getAllowedNext(string $currentName, Collection $allStatuses): Collection
    {
        $current = $allStatuses->firstWhere('name', $currentName);

        if ($current === null) {
            return collect();
        }

        $fromIsSystem = (bool) $current->is_system;

        return $allStatuses
            ->filter(function (QuotationStatus $status) use ($currentName, $fromIsSystem): bool {
                if ($status->name === $currentName) {
                    return false;
                }

                return $this->isValidTransition(
                    $currentName,
                    $status->name,
                    $fromIsSystem,
                    (bool) $status->is_system
                );
            })
            ->sortBy('sort_order')
            ->values();
    }

    public function validateTransition(Quotation $quotation, QuotationStatus $targetStatus): ?string
    {
        $quotation->loadMissing('status');
        $current = $quotation->status;

        if ($current === null) {
            return 'Quotation has no current status.';
        }

        if ($this->isTerminal($current->name) && ($this->transitions[$current->name] ?? []) === []) {
            return 'Cannot change status of a terminal quotation.';
        }

        if (! $this->isValidTransition(
            $current->name,
            $targetStatus->name,
            (bool) $current->is_system,
            (bool) $targetStatus->is_system
        )) {
            return "Cannot transition from \"{$current->name}\" to \"{$targetStatus->name}\".";
        }

        return null;
    }
}

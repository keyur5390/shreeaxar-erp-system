<?php

namespace Tests\Unit;

use App\Models\QuotationStatus;
use App\Services\QuotationWorkflowService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class QuotationWorkflowServiceTest extends TestCase
{
    private QuotationWorkflowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new QuotationWorkflowService();
    }

    public function test_terminal_statuses_are_identified(): void
    {
        $this->assertTrue($this->service->isTerminal('Accepted'));
        $this->assertTrue($this->service->isTerminal('Rejected'));
        $this->assertFalse($this->service->isTerminal('Drafted'));
    }

    public function test_system_status_transitions_follow_defined_map(): void
    {
        $this->assertTrue($this->service->isValidTransition('Drafted', 'Sent', true, true));
        $this->assertTrue($this->service->isValidTransition('Rejected', 'Drafted', true, true));
        $this->assertFalse($this->service->isValidTransition('Drafted', 'Accepted', true, true));
        $this->assertFalse($this->service->isValidTransition('Accepted', 'Drafted', true, true));
    }

    public function test_custom_statuses_allow_non_terminal_to_non_terminal(): void
    {
        $this->assertTrue($this->service->isValidTransition('Drafted', 'Custom Review', true, false));
        $this->assertTrue($this->service->isValidTransition('Custom Review', 'Custom Hold', false, false));
        $this->assertFalse($this->service->isValidTransition('Drafted', 'Accepted', true, false));
        $this->assertFalse($this->service->isValidTransition('Custom Review', 'Rejected', false, true));
    }

    public function test_get_allowed_next_for_drafted_system_status(): void
    {
        $statuses = $this->makeStatuses([
            ['name' => 'Drafted', 'is_system' => true, 'sort_order' => 1],
            ['name' => 'Sent', 'is_system' => true, 'sort_order' => 2],
            ['name' => 'Rejected', 'is_system' => true, 'sort_order' => 3],
            ['name' => 'Custom Hold', 'is_system' => false, 'sort_order' => 4],
        ]);

        $allowed = $this->service->getAllowedNext('Drafted', $statuses);

        $this->assertSame(['Sent', 'Rejected', 'Custom Hold'], $allowed->pluck('name')->all());
    }

    public function test_terminal_rejected_can_reopen_to_drafted(): void
    {
        $statuses = $this->makeStatuses([
            ['name' => 'Rejected', 'is_system' => true, 'sort_order' => 1],
            ['name' => 'Drafted', 'is_system' => true, 'sort_order' => 2],
        ]);

        $allowed = $this->service->getAllowedNext('Rejected', $statuses);

        $this->assertSame(['Drafted'], $allowed->pluck('name')->all());
    }

    public function test_terminal_accepted_returns_no_allowed_next(): void
    {
        $statuses = $this->makeStatuses([
            ['name' => 'Accepted', 'is_system' => true, 'sort_order' => 1],
            ['name' => 'Drafted', 'is_system' => true, 'sort_order' => 2],
        ]);

        $allowed = $this->service->getAllowedNext('Accepted', $statuses);

        $this->assertCount(0, $allowed);
    }

    /**
     * @param  list<array{name: string, is_system: bool, sort_order: int}>  $rows
     * @return Collection<int, QuotationStatus>
     */
    private function makeStatuses(array $rows): Collection
    {
        return collect($rows)->map(function (array $row): QuotationStatus {
            $status = new QuotationStatus();
            $status->forceFill([
                'id' => (string) str()->uuid(),
                'name' => $row['name'],
                'color' => '#000000',
                'is_system' => $row['is_system'],
                'is_default' => false,
                'sort_order' => $row['sort_order'],
            ]);

            return $status;
        });
    }
}

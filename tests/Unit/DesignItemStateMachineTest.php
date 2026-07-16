<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Models\DesignItem;
use Tests\TestCase;

class DesignItemStateMachineTest extends TestCase
{
    public function test_forward_chain_transitions_are_allowed(): void
    {
        $this->assertTrue(DesignItem::canTransition(DesignItem::STATUS_DRAFT, DesignItem::STATUS_INTERNAL_REVIEW));
        $this->assertTrue(DesignItem::canTransition(DesignItem::STATUS_INTERNAL_REVIEW, DesignItem::STATUS_SENT_TO_CLIENT));
        $this->assertTrue(DesignItem::canTransition(DesignItem::STATUS_SENT_TO_CLIENT, DesignItem::STATUS_APPROVED));
        $this->assertTrue(DesignItem::canTransition(DesignItem::STATUS_APPROVED, DesignItem::STATUS_FINAL));
    }

    public function test_loop_back_transitions_are_allowed(): void
    {
        $this->assertTrue(DesignItem::canTransition(DesignItem::STATUS_SENT_TO_CLIENT, DesignItem::STATUS_REVISION_REQUESTED));
        $this->assertTrue(DesignItem::canTransition(DesignItem::STATUS_REVISION_REQUESTED, DesignItem::STATUS_INTERNAL_REVIEW));
        $this->assertTrue(DesignItem::canTransition(DesignItem::STATUS_INTERNAL_REVIEW, DesignItem::STATUS_DRAFT));
        $this->assertTrue(
            DesignItem::canTransition(DesignItem::STATUS_APPROVED, DesignItem::STATUS_REVISION_REQUESTED),
            'late change requests after approval must be a valid loop-back, not a dead end'
        );
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $this->assertFalse(DesignItem::canTransition(DesignItem::STATUS_DRAFT, DesignItem::STATUS_APPROVED));
        $this->assertFalse(DesignItem::canTransition(DesignItem::STATUS_DRAFT, DesignItem::STATUS_FINAL));
        $this->assertFalse(DesignItem::canTransition(DesignItem::STATUS_FINAL, DesignItem::STATUS_DRAFT));
    }

    public function test_final_is_terminal(): void
    {
        $this->assertSame([], DesignItem::TRANSITIONS[DesignItem::STATUS_FINAL]);
    }
}

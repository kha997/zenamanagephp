<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Submittal;
use PHPUnit\Framework\TestCase;

class SubmittalStateMachineTest extends TestCase
{
    public function test_transition_matrix(): void
    {
        $this->assertTrue(Submittal::canTransition('draft', 'submitted'));
        $this->assertTrue(Submittal::canTransition('submitted', 'approved'));
        $this->assertTrue(Submittal::canTransition('submitted', 'rejected'));
        $this->assertTrue(Submittal::canTransition('rejected', 'revising'));
        $this->assertTrue(Submittal::canTransition('revising', 'submitted'));

        $this->assertFalse(Submittal::canTransition('approved', 'submitted'));
        $this->assertFalse(Submittal::canTransition('approved', 'revising'));
        $this->assertFalse(Submittal::canTransition('rejected', 'submitted'));
        $this->assertFalse(Submittal::canTransition('draft', 'approved'));
        $this->assertFalse(Submittal::canTransition('revising', 'revising'));
        $this->assertFalse(Submittal::canTransition('draft', 'revising'));
    }

    public function test_status_revised_constant_no_longer_exists(): void
    {
        $this->assertFalse(defined(Submittal::class . '::STATUS_REVISED'));
        $this->assertSame('revising', Submittal::STATUS_REVISING);
    }
}

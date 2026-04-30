<?php

declare(strict_types=1);

namespace BarePitch\Tests\Unit\Domain;

use BarePitch\Domain\MatchStatus;
use PHPUnit\Framework\TestCase;

/**
 * Tests the MatchStatus enum's canTransitionTo() guard method.
 *
 * Valid transitions (single-step forward only):
 *   planned  → prepared
 *   prepared → active
 *   active   → finished
 *
 * Everything else must return false.
 */
class MatchStatusTest extends TestCase
{
    // ----------------------------------------------------------------
    // Valid transitions
    // ----------------------------------------------------------------

    public function testPlannedCanTransitionToPrepared(): void
    {
        $this->assertTrue(MatchStatus::Planned->canTransitionTo(MatchStatus::Prepared));
    }

    public function testPreparedCanTransitionToActive(): void
    {
        $this->assertTrue(MatchStatus::Prepared->canTransitionTo(MatchStatus::Active));
    }

    public function testActiveCanTransitionToFinished(): void
    {
        $this->assertTrue(MatchStatus::Active->canTransitionTo(MatchStatus::Finished));
    }

    // ----------------------------------------------------------------
    // Invalid transitions: skipping a step
    // ----------------------------------------------------------------

    public function testPlannedCannotTransitionToActive(): void
    {
        $this->assertFalse(MatchStatus::Planned->canTransitionTo(MatchStatus::Active));
    }

    public function testPlannedCannotTransitionToFinished(): void
    {
        $this->assertFalse(MatchStatus::Planned->canTransitionTo(MatchStatus::Finished));
    }

    public function testPreparedCannotTransitionToFinished(): void
    {
        $this->assertFalse(MatchStatus::Prepared->canTransitionTo(MatchStatus::Finished));
    }

    public function testPreparedCannotTransitionToPlanned(): void
    {
        $this->assertFalse(MatchStatus::Prepared->canTransitionTo(MatchStatus::Planned));
    }

    // ----------------------------------------------------------------
    // Invalid transitions: backwards
    // ----------------------------------------------------------------

    public function testActiveCannotTransitionToPlanned(): void
    {
        $this->assertFalse(MatchStatus::Active->canTransitionTo(MatchStatus::Planned));
    }

    public function testActiveCannotTransitionToPrepared(): void
    {
        $this->assertFalse(MatchStatus::Active->canTransitionTo(MatchStatus::Prepared));
    }

    // ----------------------------------------------------------------
    // Finished is terminal
    // ----------------------------------------------------------------

    public function testFinishedCannotTransitionToAnything(): void
    {
        foreach (MatchStatus::cases() as $next) {
            $this->assertFalse(
                MatchStatus::Finished->canTransitionTo($next),
                "Expected finished → {$next->value} to be rejected."
            );
        }
    }

    // ----------------------------------------------------------------
    // Self-transitions are invalid
    // ----------------------------------------------------------------

    public function testNoStatusCanTransitionToItself(): void
    {
        foreach (MatchStatus::cases() as $status) {
            $this->assertFalse(
                $status->canTransitionTo($status),
                "Expected {$status->value} → {$status->value} to be rejected."
            );
        }
    }
}

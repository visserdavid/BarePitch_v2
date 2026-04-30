<?php

declare(strict_types=1);

namespace BarePitch\Tests\Unit\Domain;

use BarePitch\Domain\EventOutcome;
use BarePitch\Domain\EventType;
use BarePitch\Domain\ScoreCalculator;
use BarePitch\Domain\TeamSide;
use PHPUnit\Framework\TestCase;

/**
 * Tests ScoreCalculator::calculate() against a variety of event combinations.
 *
 * The calculator works purely on in-memory event arrays — no DB required.
 */
class ScoreCalculatorTest extends TestCase
{
    // ----------------------------------------------------------------
    // Empty events
    // ----------------------------------------------------------------

    public function testNoEventsReturnsZeroZero(): void
    {
        $score = ScoreCalculator::calculate([]);
        $this->assertSame(['home' => 0, 'away' => 0], $score);
    }

    // ----------------------------------------------------------------
    // Goal events
    // ----------------------------------------------------------------

    public function testOwnGoalIncrementsHomeScore(): void
    {
        $events = [
            $this->makeEvent(EventType::Goal, TeamSide::Own, EventOutcome::None),
        ];

        $score = ScoreCalculator::calculate($events);

        $this->assertSame(1, $score['home']);
        $this->assertSame(0, $score['away']);
    }

    public function testOpponentGoalIncrementsAwayScore(): void
    {
        $events = [
            $this->makeEvent(EventType::Goal, TeamSide::Opponent, EventOutcome::None),
        ];

        $score = ScoreCalculator::calculate($events);

        $this->assertSame(0, $score['home']);
        $this->assertSame(1, $score['away']);
    }

    public function testMultipleGoalsAccumulateCorrectly(): void
    {
        $events = [
            $this->makeEvent(EventType::Goal, TeamSide::Own,      EventOutcome::None),
            $this->makeEvent(EventType::Goal, TeamSide::Own,      EventOutcome::None),
            $this->makeEvent(EventType::Goal, TeamSide::Opponent, EventOutcome::None),
        ];

        $score = ScoreCalculator::calculate($events);

        $this->assertSame(2, $score['home']);
        $this->assertSame(1, $score['away']);
    }

    // ----------------------------------------------------------------
    // Penalty events
    // ----------------------------------------------------------------

    public function testScoredPenaltyCountsAsGoal(): void
    {
        $events = [
            $this->makeEvent(EventType::Penalty, TeamSide::Own, EventOutcome::Scored),
        ];

        $score = ScoreCalculator::calculate($events);

        $this->assertSame(1, $score['home']);
        $this->assertSame(0, $score['away']);
    }

    public function testMissedPenaltyDoesNotChangeScore(): void
    {
        $events = [
            $this->makeEvent(EventType::Penalty, TeamSide::Own, EventOutcome::Missed),
        ];

        $score = ScoreCalculator::calculate($events);

        $this->assertSame(0, $score['home']);
        $this->assertSame(0, $score['away']);
    }

    public function testPenaltyWithOutcomeNoneDoesNotCountAsGoal(): void
    {
        // outcome='none' on a penalty means it was not explicitly scored — treat as no goal
        $events = [
            $this->makeEvent(EventType::Penalty, TeamSide::Own, EventOutcome::None),
        ];

        $score = ScoreCalculator::calculate($events);

        $this->assertSame(0, $score['home']);
        $this->assertSame(0, $score['away']);
    }

    // ----------------------------------------------------------------
    // Non-score event types are ignored
    // ----------------------------------------------------------------

    public function testYellowCardDoesNotAffectScore(): void
    {
        $events = [
            $this->makeEvent(EventType::YellowCard, TeamSide::Own, EventOutcome::None),
        ];

        $score = ScoreCalculator::calculate($events);
        $this->assertSame(['home' => 0, 'away' => 0], $score);
    }

    public function testRedCardDoesNotAffectScore(): void
    {
        $events = [
            $this->makeEvent(EventType::RedCard, TeamSide::Opponent, EventOutcome::None),
        ];

        $score = ScoreCalculator::calculate($events);
        $this->assertSame(['home' => 0, 'away' => 0], $score);
    }

    public function testNoteEventDoesNotAffectScore(): void
    {
        $events = [
            $this->makeEvent(EventType::Note, TeamSide::Own, EventOutcome::None),
        ];

        $score = ScoreCalculator::calculate($events);
        $this->assertSame(['home' => 0, 'away' => 0], $score);
    }

    /**
     * Penalty shootout goals are stored with a separate shootout_goals_* column
     * in the match table and are NOT included in the regular score via events.
     * The domain uses the same goal/penalty event types for all phases, but the
     * ScoreCalculator is only passed regular-time score events (the repository
     * filters by period). This test verifies the calculator itself is neutral —
     * it scores what it is given regardless of period context.
     */
    public function testMixedRegularAndNonScoringEventsProduceCorrectTotal(): void
    {
        $events = [
            $this->makeEvent(EventType::Goal,       TeamSide::Own,      EventOutcome::None),
            $this->makeEvent(EventType::Penalty,    TeamSide::Own,      EventOutcome::Scored),
            $this->makeEvent(EventType::Penalty,    TeamSide::Own,      EventOutcome::Missed),
            $this->makeEvent(EventType::Goal,       TeamSide::Opponent, EventOutcome::None),
            $this->makeEvent(EventType::YellowCard, TeamSide::Own,      EventOutcome::None),
        ];

        $score = ScoreCalculator::calculate($events);

        // 1 goal + 1 scored penalty = 2 home; 1 opponent goal = 1 away
        $this->assertSame(2, $score['home']);
        $this->assertSame(1, $score['away']);
    }

    // ----------------------------------------------------------------
    // Helper
    // ----------------------------------------------------------------

    private function makeEvent(EventType $type, TeamSide $side, EventOutcome $outcome): array
    {
        return [
            'event_type' => $type->value,
            'team_side'  => $side->value,
            'outcome'    => $outcome->value,
        ];
    }
}

<?php

declare(strict_types=1);

namespace BarePitch\Tests\Unit\Domain;

use BarePitch\Domain\AttendanceStatus;
use BarePitch\Domain\PreparationChecker;
use PHPUnit\Framework\TestCase;

/**
 * Tests PreparationChecker::check() in isolation.
 *
 * The checker is a pure function — no DB required.
 */
class PreparationCheckerTest extends TestCase
{
    // ----------------------------------------------------------------
    // Valid preparation
    // ----------------------------------------------------------------

    public function testValidPreparationReturnsNoErrors(): void
    {
        [$match, $selections, $lineupSlots, $formationPositions] = $this->buildValidFixture();

        $errors = PreparationChecker::check($match, $selections, $lineupSlots, $formationPositions);

        $this->assertEmpty($errors, 'Expected no errors for a valid preparation.');
    }

    // ----------------------------------------------------------------
    // Fewer than 11 present players
    // ----------------------------------------------------------------

    public function testFewerThan11PresentPlayersFails(): void
    {
        [$match, $selections, $lineupSlots, $formationPositions] = $this->buildValidFixture();

        // Mark two starters as absent so we have only 9 present
        $selections[0]['attendance_status'] = AttendanceStatus::Absent->value;
        $selections[1]['attendance_status'] = AttendanceStatus::Absent->value;

        $errors = PreparationChecker::check($match, $selections, $lineupSlots, $formationPositions);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('11', $errors[0]);
    }

    public function testExactly10PresentPlayersFails(): void
    {
        [$match, $selections, $lineupSlots, $formationPositions] = $this->buildValidFixture();

        // Make one player absent — leaves exactly 10 present
        $selections[10]['attendance_status'] = AttendanceStatus::Absent->value;

        $errors = PreparationChecker::check($match, $selections, $lineupSlots, $formationPositions);

        $errorMessages = implode(' ', $errors);
        $this->assertStringContainsString('11', $errorMessages);
    }

    // ----------------------------------------------------------------
    // Injured starter
    // ----------------------------------------------------------------

    public function testInjuredStarterFails(): void
    {
        [$match, $selections, $lineupSlots, $formationPositions] = $this->buildValidFixture();

        // Mark the first starter (selection id=1) as injured
        $selections[0]['attendance_status'] = AttendanceStatus::Injured->value;

        $errors = PreparationChecker::check($match, $selections, $lineupSlots, $formationPositions);

        $this->assertNotEmpty($errors);
        $errorMessages = implode(' ', $errors);
        $this->assertStringContainsString('present', strtolower($errorMessages));
    }

    // ----------------------------------------------------------------
    // No formation
    // ----------------------------------------------------------------

    public function testEmptyFormationPositionsFails(): void
    {
        [$match, $selections, $lineupSlots] = $this->buildValidFixture();

        $errors = PreparationChecker::check($match, $selections, $lineupSlots, []);

        $this->assertNotEmpty($errors);
        $errorMessages = implode(' ', $errors);
        $this->assertStringContainsString('formation', strtolower($errorMessages));
    }

    public function testEmptyFormationShortCircuitsLineupCheck(): void
    {
        // When there is no formation, the lineup completeness check cannot run.
        // Only the formation-missing error should appear, not a lineup error.
        [$match, $selections, $lineupSlots] = $this->buildValidFixture();

        $errors = PreparationChecker::check($match, $selections, $lineupSlots, []);

        // Should be exactly one error — the formation error — not a lineup error too
        $this->assertCount(1, $errors);
    }

    // ----------------------------------------------------------------
    // Incomplete lineup (not all formation positions filled)
    // ----------------------------------------------------------------

    public function testIncompleteLineupFails(): void
    {
        [$match, $selections, $lineupSlots, $formationPositions] = $this->buildValidFixture();

        // Remove the last lineup slot so one formation position is unfilled
        array_pop($lineupSlots);

        $errors = PreparationChecker::check($match, $selections, $lineupSlots, $formationPositions);

        $this->assertNotEmpty($errors);
        $errorMessages = implode(' ', $errors);
        $this->assertStringContainsString('formation position', strtolower($errorMessages));
    }

    // ----------------------------------------------------------------
    // Fixture builder
    // ----------------------------------------------------------------

    /**
     * Builds a consistent set of valid preparation data:
     * - 11 present players (ids 1–11)
     * - 11 formation positions (ids 1–11)
     * - 11 lineup slots linking each selection to a position
     *
     * Returns [$match, $selections, $lineupSlots, $formationPositions].
     */
    private function buildValidFixture(): array
    {
        $match = [
            'id'      => 1,
            'team_id' => 1,
            'status'  => 'planned',
        ];

        $selections = [];
        for ($i = 1; $i <= 11; $i++) {
            $selections[] = [
                'id'                => $i,
                'match_id'          => 1,
                'player_id'         => $i,
                'attendance_status' => AttendanceStatus::Present->value,
                'is_starting'       => 1,
            ];
        }

        $formationPositions = [];
        for ($i = 1; $i <= 11; $i++) {
            $formationPositions[] = [
                'id'           => $i,
                'formation_id' => 1,
                'label'        => 'Position ' . $i,
                'line_key'     => 'MID',
                'grid_row'     => $i,
                'grid_col'     => 1,
                'sort_order'   => $i,
            ];
        }

        $lineupSlots = [];
        for ($i = 1; $i <= 11; $i++) {
            $lineupSlots[] = [
                'id'                   => $i,
                'match_id'             => 1,
                'match_selection_id'   => $i,
                'formation_position_id' => $i,
                'grid_row'             => $i,
                'grid_col'             => 1,
            ];
        }

        return [$match, $selections, $lineupSlots, $formationPositions];
    }
}

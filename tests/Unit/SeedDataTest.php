<?php

declare(strict_types=1);

namespace BarePitch\Tests\Unit;

use PHPUnit\Framework\TestCase;

class SeedDataTest extends TestCase
{
    public function testV010SeedCreatesEighteenPlayersAndSeasonContexts(): void
    {
        $sql = file_get_contents(__DIR__ . '/../../database/seeds/seed_v010.sql');
        $this->assertIsString($sql);

        preg_match('/INSERT IGNORE INTO player .*?VALUES\s*(.*?);/s', $sql, $playerMatch);
        preg_match('/INSERT IGNORE INTO player_season_context .*?VALUES\s*(.*?);/s', $sql, $contextMatch);

        $this->assertCount(2, $playerMatch);
        $this->assertCount(2, $contextMatch);
        $this->assertSame(18, preg_match_all('/^\s*\(\d+,/m', $playerMatch[1]));
        $this->assertSame(18, preg_match_all('/^\s*\(\d+,/m', $contextMatch[1]));
        $this->assertStringContainsString("(17, 'Player', '17', 'Player 17'", $playerMatch[1]);
        $this->assertStringContainsString("(18, 'Player', '18', 'Player 18'", $playerMatch[1]);
    }
}

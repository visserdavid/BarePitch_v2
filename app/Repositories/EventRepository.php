<?php

declare(strict_types=1);

namespace BarePitch\Repositories;

class EventRepository
{
    public function __construct(private readonly \PDO $pdo) {}

    public function findByMatch(int $matchId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT me.*,
                    ms.player_id,
                    p.display_name AS player_name,
                    ap.display_name AS assist_name
             FROM match_event me
             LEFT JOIN match_selection ms  ON ms.id = me.player_selection_id
             LEFT JOIN player p            ON p.id  = ms.player_id
             LEFT JOIN match_selection ams ON ams.id = me.assist_selection_id
             LEFT JOIN player ap           ON ap.id  = ams.player_id
             WHERE me.match_id = ?
             ORDER BY me.match_second ASC, me.id ASC'
        );
        $stmt->execute([$matchId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM match_event WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByMatchAndType(int $matchId, string $eventType): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM match_event WHERE match_id = ? AND event_type = ? ORDER BY match_second ASC'
        );
        $stmt->execute([$matchId, $eventType]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO match_event
             (match_id, period_id, event_type, team_side, player_selection_id,
              assist_selection_id, zone_code, outcome, minute_display, match_second,
              note_text, created_by_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['match_id'],
            $data['period_id'] ?? null,
            $data['event_type'],
            $data['team_side'] ?? 'own',
            $data['player_selection_id'] ?? null,
            $data['assist_selection_id'] ?? null,
            $data['zone_code'] ?? null,
            $data['outcome'] ?? 'none',
            $data['minute_display'] ?? null,
            $data['match_second'] ?? null,
            $data['note_text'] ?? null,
            $data['created_by_user_id'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** Returns only score-relevant events (goals and scored penalties) */
    public function getScoreEvents(int $matchId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM match_event
             WHERE match_id = ?
               AND (
                 event_type = 'goal'
                 OR (event_type = 'penalty' AND outcome = 'scored')
               )
             ORDER BY match_second ASC, id ASC"
        );
        $stmt->execute([$matchId]);
        return $stmt->fetchAll();
    }
}

-- BarePitch v0.1.0 Seed Data
-- Run via: php scripts/seed.php

SET FOREIGN_KEY_CHECKS = 0;

-- Club
INSERT IGNORE INTO club (id, name, is_active) VALUES
  (1, 'FC Demo', 1);

-- Season
INSERT IGNORE INTO season (id, club_id, label, starts_on, ends_on, is_active) VALUES
  (1, 1, '2025-2026', '2025-08-01', '2026-06-30', 1);

-- Phase
INSERT IGNORE INTO phase (id, season_id, number, label, starts_on, ends_on) VALUES
  (1, 1, 1, 'Competitie fase 1', '2025-09-01', '2026-01-31');

-- Team
INSERT IGNORE INTO team (id, club_id, season_id, name, max_match_players, livestream_hours_after_match, is_active) VALUES
  (1, 1, 1, 'FC Demo U21', 18, 24, 1);

-- User (admin + coach)
INSERT IGNORE INTO user (id, first_name, last_name, email, locale, is_administrator, is_active) VALUES
  (1, 'Admin', 'User', 'admin@demo.test', 'en', 1, 1);

-- User team role (also give the admin a coach role so policy checks pass easily)
INSERT IGNORE INTO user_team_role (id, user_id, team_id, role_key) VALUES
  (1, 1, 1, 'coach');

-- Formation: 4-3-3
INSERT IGNORE INTO formation (id, team_id, name, grid_rows, grid_cols, is_active) VALUES
  (1, 1, '4-3-3', 10, 11, 1);

-- Formation positions (11 field positions)
-- Grid layout: row=9 is defensive end (GK), row=1 is attacking end (FWD)
INSERT IGNORE INTO formation_position (id, formation_id, label, line_key, grid_row, grid_col, sort_order) VALUES
  -- GK
  (1,  1, 'GK',  'GK',  9, 6,  1),
  -- DEF (4)
  (2,  1, 'LB',  'DEF', 7, 2,  2),
  (3,  1, 'CB',  'DEF', 7, 4,  3),
  (4,  1, 'CB',  'DEF', 7, 7,  4),
  (5,  1, 'RB',  'DEF', 7, 9,  5),
  -- MID (3)
  (6,  1, 'LM',  'MID', 5, 3,  6),
  (7,  1, 'CM',  'MID', 5, 6,  7),
  (8,  1, 'RM',  'MID', 5, 8,  8),
  -- FWD (3)
  (9,  1, 'LW',  'FWD', 2, 2,  9),
  (10, 1, 'ST',  'FWD', 2, 6, 10),
  (11, 1, 'RW',  'FWD', 2, 9, 11);

-- 16 Players with season context
-- Player 1: GK (squad #1)
INSERT IGNORE INTO player (id, first_name, last_name, display_name, is_active) VALUES
  (1,  'Player',  '1',  'Player 1',  1),
  (2,  'Player',  '2',  'Player 2',  1),
  (3,  'Player',  '3',  'Player 3',  1),
  (4,  'Player',  '4',  'Player 4',  1),
  (5,  'Player',  '5',  'Player 5',  1),
  (6,  'Player',  '6',  'Player 6',  1),
  (7,  'Player',  '7',  'Player 7',  1),
  (8,  'Player',  '8',  'Player 8',  1),
  (9,  'Player',  '9',  'Player 9',  1),
  (10, 'Player', '10', 'Player 10',  1),
  (11, 'Player', '11', 'Player 11',  1),
  (12, 'Player', '12', 'Player 12',  1),
  (13, 'Player', '13', 'Player 13',  1),
  (14, 'Player', '14', 'Player 14',  1),
  (15, 'Player', '15', 'Player 15',  1),
  (16, 'Player', '16', 'Player 16',  1);

-- Player season contexts: team_id=1, season_id=1
-- Positions: 1xGK, 4xDEF, 5xMID, 6xFWD
INSERT IGNORE INTO player_season_context (id, player_id, season_id, team_id, preferred_line, squad_number) VALUES
  (1,   1, 1, 1, 'GK',  1),
  (2,   2, 1, 1, 'DEF', 2),
  (3,   3, 1, 1, 'DEF', 3),
  (4,   4, 1, 1, 'DEF', 4),
  (5,   5, 1, 1, 'DEF', 5),
  (6,   6, 1, 1, 'MID', 6),
  (7,   7, 1, 1, 'MID', 7),
  (8,   8, 1, 1, 'MID', 8),
  (9,   9, 1, 1, 'MID', 9),
  (10, 10, 1, 1, 'MID', 10),
  (11, 11, 1, 1, 'FWD', 11),
  (12, 12, 1, 1, 'FWD', 12),
  (13, 13, 1, 1, 'FWD', 13),
  (14, 14, 1, 1, 'FWD', 14),
  (15, 15, 1, 1, 'FWD', 15),
  (16, 16, 1, 1, 'FWD', 16);

SET FOREIGN_KEY_CHECKS = 1;

-- BarePitch v0.1.0 Initial Schema
-- Run via: php scripts/migrate.php

SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------
-- club
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS club (
  id         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  name       VARCHAR(120)    NOT NULL,
  is_active  TINYINT(1)      NOT NULL DEFAULT 1,
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_club_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- season
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS season (
  id         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  club_id    INT UNSIGNED    NOT NULL,
  label      VARCHAR(120)    NOT NULL,
  starts_on  DATE            NOT NULL,
  ends_on    DATE            NOT NULL,
  is_active  TINYINT(1)      NOT NULL DEFAULT 1,
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_season_club_label (club_id, label),
  CONSTRAINT fk_season_club FOREIGN KEY (club_id) REFERENCES club(id),
  CONSTRAINT chk_season_dates CHECK (starts_on <= ends_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- phase
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS phase (
  id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  season_id   INT UNSIGNED    NOT NULL,
  number      SMALLINT UNSIGNED NOT NULL,
  label       VARCHAR(120)    NOT NULL,
  starts_on   DATE            NULL,
  ends_on     DATE            NULL,
  focus_text  VARCHAR(500)    NULL,
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_phase_season_number (season_id, number),
  CONSTRAINT fk_phase_season FOREIGN KEY (season_id) REFERENCES season(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- team
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS team (
  id                             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  club_id                        INT UNSIGNED    NOT NULL,
  season_id                      INT UNSIGNED    NOT NULL,
  name                           VARCHAR(120)    NOT NULL,
  max_match_players              TINYINT UNSIGNED NOT NULL DEFAULT 18,
  livestream_hours_after_match   TINYINT UNSIGNED NOT NULL DEFAULT 24,
  is_active                      TINYINT(1)      NOT NULL DEFAULT 1,
  created_at                     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_team_club_season_name (club_id, season_id, name),
  CONSTRAINT fk_team_club   FOREIGN KEY (club_id)   REFERENCES club(id),
  CONSTRAINT fk_team_season FOREIGN KEY (season_id) REFERENCES season(id),
  CONSTRAINT chk_team_max_players CHECK (max_match_players >= 11),
  CONSTRAINT chk_team_livestream  CHECK (livestream_hours_after_match BETWEEN 1 AND 72)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- user
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS user (
  id               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  first_name       VARCHAR(80)     NOT NULL,
  last_name        VARCHAR(80)     NOT NULL,
  email            VARCHAR(254)    NOT NULL,
  locale           VARCHAR(10)     NOT NULL DEFAULT 'en',
  is_administrator TINYINT(1)      NOT NULL DEFAULT 0,
  is_active        TINYINT(1)      NOT NULL DEFAULT 1,
  created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deactivated_at   DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- user_team_role
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_team_role (
  id         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED    NOT NULL,
  team_id    INT UNSIGNED    NOT NULL,
  role_key   ENUM('coach','trainer','team_manager') NOT NULL,
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_team_role (user_id, team_id, role_key),
  CONSTRAINT fk_utr_user FOREIGN KEY (user_id) REFERENCES user(id),
  CONSTRAINT fk_utr_team FOREIGN KEY (team_id) REFERENCES team(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- player
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS player (
  id             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  first_name     VARCHAR(80)     NOT NULL,
  last_name      VARCHAR(80)     NOT NULL,
  display_name   VARCHAR(120)    NULL,
  is_active      TINYINT(1)      NOT NULL DEFAULT 1,
  created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deactivated_at DATETIME        NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- player_season_context
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS player_season_context (
  id               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  player_id        INT UNSIGNED    NOT NULL,
  season_id        INT UNSIGNED    NOT NULL,
  team_id          INT UNSIGNED    NULL,
  preferred_line   ENUM('GK','DEF','MID','FWD') NULL,
  preferred_foot   ENUM('left','right','both')  NULL,
  squad_number     TINYINT UNSIGNED NULL,
  is_guest_eligible TINYINT(1)     NOT NULL DEFAULT 0,
  created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_psc_player_season (player_id, season_id),
  CONSTRAINT fk_psc_player FOREIGN KEY (player_id) REFERENCES player(id),
  CONSTRAINT fk_psc_season FOREIGN KEY (season_id) REFERENCES season(id),
  CONSTRAINT fk_psc_team   FOREIGN KEY (team_id)   REFERENCES team(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- formation
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS formation (
  id         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  team_id    INT UNSIGNED    NOT NULL,
  name       VARCHAR(120)    NOT NULL,
  grid_rows  TINYINT UNSIGNED NOT NULL DEFAULT 10,
  grid_cols  TINYINT UNSIGNED NOT NULL DEFAULT 11,
  is_active  TINYINT(1)      NOT NULL DEFAULT 1,
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_formation_team FOREIGN KEY (team_id) REFERENCES team(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- formation_position
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS formation_position (
  id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  formation_id INT UNSIGNED    NOT NULL,
  label        VARCHAR(120)    NOT NULL,
  line_key     ENUM('GK','DEF','MID','FWD') NOT NULL,
  grid_row     TINYINT UNSIGNED NOT NULL,
  grid_col     TINYINT UNSIGNED NOT NULL,
  sort_order   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_fp_formation_grid (formation_id, grid_row, grid_col),
  CONSTRAINT fk_fp_formation FOREIGN KEY (formation_id) REFERENCES formation(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- match  (backtick-quoted: reserved word in MySQL 8)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `match` (
  id                                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  team_id                           INT UNSIGNED    NOT NULL,
  phase_id                          INT UNSIGNED    NOT NULL,
  date                              DATE            NOT NULL,
  kick_off_time                     TIME            NULL,
  opponent_name                     VARCHAR(120)    NOT NULL,
  home_away                         ENUM('home','away','neutral') NOT NULL DEFAULT 'home',
  match_type                        ENUM('league','cup','friendly') NOT NULL DEFAULT 'league',
  regular_half_duration_minutes     TINYINT UNSIGNED NOT NULL DEFAULT 45,
  extra_time_half_duration_minutes  TINYINT UNSIGNED NOT NULL DEFAULT 15,
  status                            ENUM('planned','prepared','active','finished') NOT NULL DEFAULT 'planned',
  active_phase                      ENUM('none','regular_time','halftime','extra_time','penalty_shootout','finished') NOT NULL DEFAULT 'none',
  goals_scored                      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  goals_conceded                    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  shootout_goals_scored             TINYINT UNSIGNED NOT NULL DEFAULT 0,
  shootout_goals_conceded           TINYINT UNSIGNED NOT NULL DEFAULT 0,
  finished_at                       DATETIME        NULL,
  notes                             VARCHAR(500)    NULL,
  created_by                        INT UNSIGNED    NOT NULL,
  created_at                        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at                        DATETIME        NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_match_team       FOREIGN KEY (team_id)    REFERENCES team(id),
  CONSTRAINT fk_match_phase      FOREIGN KEY (phase_id)   REFERENCES phase(id),
  CONSTRAINT fk_match_created_by FOREIGN KEY (created_by) REFERENCES user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- match_period
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS match_period (
  id                          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  match_id                    INT UNSIGNED    NOT NULL,
  period_key                  ENUM('regular_1','regular_2','extra_1','extra_2') NOT NULL,
  sort_order                  TINYINT UNSIGNED NOT NULL,
  started_at                  DATETIME        NULL,
  ended_at                    DATETIME        NULL,
  configured_duration_minutes TINYINT UNSIGNED NOT NULL DEFAULT 45,
  created_at                  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mp_match_period (match_id, period_key),
  CONSTRAINT fk_mp_match FOREIGN KEY (match_id) REFERENCES `match`(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- match_selection  (attendance + status for each player in a match)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS match_selection (
  id                      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  match_id                INT UNSIGNED    NOT NULL,
  player_id               INT UNSIGNED    NOT NULL,
  player_season_context_id INT UNSIGNED   NULL,
  guest_type              ENUM('none','internal','external') NOT NULL DEFAULT 'none',
  is_guest                TINYINT(1)      NOT NULL DEFAULT 0,
  attendance_status       ENUM('present','absent','injured') NOT NULL DEFAULT 'present',
  absence_reason          ENUM('sick','holiday','school','other') NULL,
  injury_note             VARCHAR(500)    NULL,
  shirt_number_override   TINYINT UNSIGNED NULL,
  is_starting             TINYINT(1)      NOT NULL DEFAULT 0,
  is_on_bench             TINYINT(1)      NOT NULL DEFAULT 0,
  is_active_on_field      TINYINT(1)      NOT NULL DEFAULT 0,
  is_sent_off             TINYINT(1)      NOT NULL DEFAULT 0,
  can_reenter             TINYINT(1)      NOT NULL DEFAULT 1,
  playing_time_seconds    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ms_match_player (match_id, player_id),
  CONSTRAINT fk_ms_match  FOREIGN KEY (match_id)  REFERENCES `match`(id),
  CONSTRAINT fk_ms_player FOREIGN KEY (player_id) REFERENCES player(id),
  CONSTRAINT fk_ms_psc    FOREIGN KEY (player_season_context_id) REFERENCES player_season_context(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- match_lineup_slot
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS match_lineup_slot (
  id                   INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  match_id             INT UNSIGNED    NOT NULL,
  match_selection_id   INT UNSIGNED    NOT NULL,
  formation_position_id INT UNSIGNED   NULL,
  grid_row             TINYINT UNSIGNED NULL,
  grid_col             TINYINT UNSIGNED NULL,
  is_active_slot       TINYINT(1)      NOT NULL DEFAULT 1,
  created_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mls_match_selection (match_id, match_selection_id),
  UNIQUE KEY uq_mls_match_grid (match_id, grid_row, grid_col),
  CONSTRAINT fk_mls_match     FOREIGN KEY (match_id)             REFERENCES `match`(id),
  CONSTRAINT fk_mls_selection FOREIGN KEY (match_selection_id)   REFERENCES match_selection(id),
  CONSTRAINT fk_mls_fp        FOREIGN KEY (formation_position_id) REFERENCES formation_position(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- match_event
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS match_event (
  id                    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  match_id              INT UNSIGNED    NOT NULL,
  period_id             INT UNSIGNED    NULL,
  event_type            ENUM('goal','penalty','yellow_card','red_card','note') NOT NULL,
  team_side             ENUM('own','opponent') NOT NULL DEFAULT 'own',
  player_selection_id   INT UNSIGNED    NULL,
  assist_selection_id   INT UNSIGNED    NULL,
  zone_code             ENUM('tl','tm','tr','ml','mm','mr','bl','bm','br') NULL,
  outcome               ENUM('scored','missed','none') NOT NULL DEFAULT 'none',
  minute_display        TINYINT UNSIGNED NULL,
  match_second          SMALLINT UNSIGNED NULL,
  note_text             VARCHAR(500)    NULL,
  created_by_user_id    INT UNSIGNED    NOT NULL,
  created_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_me_match      FOREIGN KEY (match_id)            REFERENCES `match`(id),
  CONSTRAINT fk_me_period     FOREIGN KEY (period_id)           REFERENCES match_period(id),
  CONSTRAINT fk_me_player     FOREIGN KEY (player_selection_id) REFERENCES match_selection(id),
  CONSTRAINT fk_me_assist     FOREIGN KEY (assist_selection_id) REFERENCES match_selection(id),
  CONSTRAINT fk_me_created_by FOREIGN KEY (created_by_user_id)  REFERENCES user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- match_lock
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS match_lock (
  id         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  match_id   INT UNSIGNED    NOT NULL,
  user_id    INT UNSIGNED    NOT NULL,
  locked_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME        NOT NULL,
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ml_match (match_id),
  CONSTRAINT fk_ml_match FOREIGN KEY (match_id) REFERENCES `match`(id),
  CONSTRAINT fk_ml_user  FOREIGN KEY (user_id)  REFERENCES user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- audit_log  (append-only)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
  id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  entity_type     VARCHAR(60)     NOT NULL,
  entity_id       INT UNSIGNED    NOT NULL,
  match_id        INT UNSIGNED    NULL,
  user_id         INT UNSIGNED    NOT NULL,
  action_key      VARCHAR(120)    NOT NULL,
  field_name      VARCHAR(120)    NULL,
  old_value_json  JSON            NULL,
  new_value_json  JSON            NULL,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_al_user FOREIGN KEY (user_id) REFERENCES user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- migrations  (tracks executed migration files)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS migrations (
  id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  filename    VARCHAR(255)    NOT NULL,
  executed_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_migrations_filename (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

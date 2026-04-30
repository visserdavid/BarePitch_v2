-- Migration: add formation_id to match table
-- Allows an explicit formation to be stored on a match, separate from
-- the formation inferred from lineup slots.

ALTER TABLE `match`
    ADD COLUMN formation_id INT UNSIGNED NULL
        AFTER extra_time_half_duration_minutes,
    ADD CONSTRAINT fk_match_formation
        FOREIGN KEY (formation_id) REFERENCES formation(id);

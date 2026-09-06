-- Per ADR 0001, recurring slots are bounded by a start date and an
-- optional end date so a slot never resolves as an occurrence for a week
-- before it existed (or after it stopped).
ALTER TABLE recurring_slots
    ADD COLUMN start_date DATE NOT NULL AFTER week_parity,
    ADD COLUMN end_date DATE NULL AFTER start_date,
    ADD CONSTRAINT chk_recurring_slots_date_bounds CHECK (end_date IS NULL OR end_date >= start_date);

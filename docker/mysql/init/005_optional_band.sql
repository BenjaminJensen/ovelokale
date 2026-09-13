-- Per issue #10, an occurrence may belong to a person rather than a band.
-- A NULL band_id means the occurrence belongs to whoever created it.
ALTER TABLE bookings
    MODIFY COLUMN band_id INT UNSIGNED NULL;

-- recurring_slots recorded no user at all: the band *was* the owner. Mirror
-- bookings.booked_by_user_id so both occurrence sources serialise the same
-- two keys. Added nullable and backfilled first, because MySQL fills existing
-- rows of a NOT NULL add with 0, which the foreign key then rejects (1452).
ALTER TABLE recurring_slots
    ADD COLUMN booked_by_user_id INT UNSIGNED NULL AFTER band_id;

-- The literal 1 is App\User\CurrentUser::ID (ADR 0003); SQL cannot reference
-- the constant.
UPDATE recurring_slots SET booked_by_user_id = 1 WHERE booked_by_user_id IS NULL;

ALTER TABLE recurring_slots
    MODIFY COLUMN booked_by_user_id INT UNSIGNED NOT NULL;

ALTER TABLE recurring_slots
    ADD CONSTRAINT fk_recurring_slots_booked_by_user
    FOREIGN KEY (booked_by_user_id) REFERENCES users (id);

ALTER TABLE recurring_slots
    MODIFY COLUMN band_id INT UNSIGNED NULL;

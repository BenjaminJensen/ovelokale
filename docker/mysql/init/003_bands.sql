-- Bands: the musical acts that book rehearsal slots.
CREATE TABLE IF NOT EXISTS bands (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    color VARCHAR(7) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Many-to-many: a user can play in several bands, a band has several members.
CREATE TABLE IF NOT EXISTS band_members (
    band_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (band_id, user_id),
    INDEX idx_band_members_user_id (user_id),
    CONSTRAINT fk_band_members_band FOREIGN KEY (band_id) REFERENCES bands (id),
    CONSTRAINT fk_band_members_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE bookings
    ADD CONSTRAINT fk_bookings_band
    FOREIGN KEY (band_id) REFERENCES bands (id);

ALTER TABLE recurring_slots
    ADD CONSTRAINT fk_recurring_slots_band
    FOREIGN KEY (band_id) REFERENCES bands (id);

-- Ad-hoc bookings: one-off reservations of a rehearsal slot.
CREATE TABLE IF NOT EXISTS bookings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    band_id INT UNSIGNED NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    booked_by_user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bookings_time_range (start_time, end_time),
    INDEX idx_bookings_band_id (band_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Recurring patterns, e.g. "every other Tuesday 18:00-20:00".
-- week_parity is matched against the ISO-8601 week number of a given date.
CREATE TABLE IF NOT EXISTS recurring_slots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    band_id INT UNSIGNED NOT NULL,
    day_of_week TINYINT UNSIGNED NOT NULL COMMENT 'ISO-8601: 1=Monday .. 7=Sunday',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    week_parity ENUM('odd', 'even', 'all') NOT NULL DEFAULT 'all',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_recurring_slots_band_id (band_id),
    CONSTRAINT chk_recurring_slots_day_of_week CHECK (day_of_week BETWEEN 1 AND 7)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

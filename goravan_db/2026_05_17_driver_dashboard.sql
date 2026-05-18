ALTER TABLE users
    MODIFY COLUMN role ENUM('user','admin','driver') NOT NULL DEFAULT 'user';

ALTER TABLE drivers
    ADD COLUMN user_id_fk INT UNSIGNED NULL AFTER driver_id_pk,
    ADD UNIQUE KEY uk_drivers_user_id_fk (user_id_fk);

ALTER TABLE drivers
    ADD CONSTRAINT fk_drivers_users_login
    FOREIGN KEY (user_id_fk) REFERENCES users(user_id_pk)
    ON DELETE SET NULL;

ALTER TABLE schedules
    MODIFY COLUMN trip_status ENUM('boarding','not_departed','departed','arrived','completed','cancelled') NOT NULL DEFAULT 'boarding';

UPDATE schedules
SET trip_status = 'not_departed'
WHERE trip_status = 'boarding';

ALTER TABLE schedules
    MODIFY COLUMN trip_status ENUM('not_departed','departed','arrived','completed','cancelled') NOT NULL DEFAULT 'not_departed',
    ADD COLUMN departed_at DATETIME NULL AFTER trip_status,
    MODIFY COLUMN arrived_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN completed_at DATETIME NULL AFTER arrived_at;

UPDATE schedules
SET departed_at = COALESCE(departed_at, updated_at, created_at)
WHERE trip_status IN ('departed','arrived','completed')
  AND departed_at IS NULL;

UPDATE schedules
SET arrived_at = COALESCE(arrived_at, updated_at, created_at)
WHERE trip_status IN ('arrived','completed')
  AND arrived_at IS NULL;

UPDATE schedules
SET completed_at = COALESCE(completed_at, updated_at, arrived_at, created_at)
WHERE trip_status = 'completed'
  AND completed_at IS NULL;

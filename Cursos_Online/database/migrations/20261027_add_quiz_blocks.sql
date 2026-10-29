-- Add block grouping and multiple-choice exercises.
-- Existing exercises stay open response and remain unassigned to a block.

SET @column_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ejercicios' AND COLUMN_NAME = 'bloque_id'
);
SET @migration_sql = IF(@column_exists = 0,
    'ALTER TABLE ejercicios ADD COLUMN bloque_id INT NULL AFTER curso_id',
    'SELECT 1');
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @column_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ejercicios' AND COLUMN_NAME = 'tipo'
);
SET @migration_sql = IF(@column_exists = 0,
    'ALTER TABLE ejercicios ADD COLUMN tipo VARCHAR(20) NOT NULL DEFAULT ''abierta'' AFTER bloque_id',
    'SELECT 1');
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @index_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ejercicios' AND INDEX_NAME = 'idx_ejercicios_bloque'
);
SET @migration_sql = IF(@index_exists = 0,
    'ALTER TABLE ejercicios ADD INDEX idx_ejercicios_bloque (bloque_id)',
    'SELECT 1');
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'ejercicios' AND CONSTRAINT_NAME = 'fk_ejercicios_bloque'
);
SET @migration_sql = IF(@fk_exists = 0,
    'ALTER TABLE ejercicios ADD CONSTRAINT fk_ejercicios_bloque FOREIGN KEY (bloque_id) REFERENCES bloques(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

-- Place legacy exercises into the first block of their course when available.
UPDATE ejercicios e
SET e.bloque_id = (
        SELECT b.id FROM bloques b
        WHERE b.curso_id = e.curso_id
        ORDER BY b.orden, b.id
        LIMIT 1
)
WHERE e.bloque_id IS NULL
    AND EXISTS (SELECT 1 FROM bloques b WHERE b.curso_id = e.curso_id);

CREATE TABLE IF NOT EXISTS opciones_ejercicio (
    id INT NOT NULL AUTO_INCREMENT,
    ejercicio_id INT NOT NULL,
    texto VARCHAR(500) NOT NULL,
    es_correcta TINYINT(1) NOT NULL DEFAULT 0,
    orden TINYINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_opciones_ejercicio (ejercicio_id),
    CONSTRAINT fk_opciones_ejercicio FOREIGN KEY (ejercicio_id)
        REFERENCES ejercicios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

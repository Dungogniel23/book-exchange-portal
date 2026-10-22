USE book_exchange;

-- Inspect the current books columns before making any changes.
SHOW COLUMNS FROM `books`;

-- Add each feature column only when it is absent. Existing rows remain NULL
-- until their owners edit them; no existing values are overwritten.
SET @has_image = (
	SELECT COUNT(*) FROM information_schema.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'books' AND COLUMN_NAME = 'image'
);
SET @migration_sql = IF(
	@has_image = 0,
	'ALTER TABLE `books` ADD COLUMN `image` VARCHAR(255) NULL',
	'SELECT ''books.image already exists'' AS migration_status'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @has_condition = (
	SELECT COUNT(*) FROM information_schema.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'books' AND COLUMN_NAME = 'condition'
);
SET @migration_sql = IF(
	@has_condition = 0,
	'ALTER TABLE `books` ADD COLUMN `condition` ENUM(''Like New'',''Good'',''Fair'',''Poor'') NULL',
	'SELECT ''books.condition already exists'' AS migration_status'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @has_condition_review = (
	SELECT COUNT(*) FROM information_schema.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'books' AND COLUMN_NAME = 'condition_review'
);
SET @migration_sql = IF(
	@has_condition_review = 0,
	'ALTER TABLE `books` ADD COLUMN `condition_review` TEXT NULL',
	'SELECT ''books.condition_review already exists'' AS migration_status'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

-- Verify the resulting schema and confirm the existing row count is retained.
SHOW COLUMNS FROM `books`;
SELECT COUNT(*) AS books_after_migration FROM `books`;

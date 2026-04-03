-- Make creator_id nullable to support anonymous group creation
ALTER TABLE `groups` MODIFY COLUMN creator_id INT NULL;
ALTER TABLE `groups` DROP FOREIGN KEY groups_ibfk_1;
ALTER TABLE `groups` ADD CONSTRAINT groups_ibfk_1 FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE SET NULL;

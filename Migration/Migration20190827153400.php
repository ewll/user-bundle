<?php namespace Ewll\UserBundle\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20190827153400 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'user, twofaCode, token';
    }

    public function up(): string
    {
        return <<<SQL
CREATE TABLE `user` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(64) NOT NULL,
    `pass` VARCHAR(64) NOT NULL,
    `twofaTypeId` TINYINT(3) UNSIGNED NULL,
    `twofaData` VARCHAR(256) NULL,
    `ip` VARCHAR(39) NOT NULL,
    `timezone` VARCHAR (30) NOT NULL DEFAULT 'Atlantic/Reykjavik',
    `isEmailConfirmed` TINYINT(3) UNSIGNED NOT NULL,
    `accessRights` TEXT NOT NULL DEFAULT '[]',
    `createdTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `email_pass` (`email`, `pass`),
    UNIQUE INDEX `email` (`email`)
)COLLATE='utf8mb4_general_ci' ENGINE=InnoDB;

CREATE TABLE `twofaCode` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `userId` BIGINT(20) UNSIGNED NOT NULL,
    `twofaTypeId` TINYINT(3) UNSIGNED NOT NULL,
    `actionId` TINYINT(3) UNSIGNED NOT NULL,
    `contact` VARCHAR(64) NOT NULL,
    `code` VARCHAR(6) NOT NULL,
    `createdTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `userId_actionId` (`userId`, `actionId`),
    INDEX `twofaTypeId` (`twofaTypeId`)
)COLLATE='utf8mb4_general_ci' ENGINE=InnoDB;

CREATE TABLE `token` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `typeId` TINYINT(3) UNSIGNED NOT NULL,
    `actionHash` VARCHAR(32) NOT NULL,
    `data` VARCHAR(512) NOT NULL,
    `ip` VARCHAR(39) NOT NULL,
    `expirationTs` TIMESTAMP NOT NULL,
    `createdTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `typeId` (`typeId`),
    INDEX `actionHash` (`actionHash`),
    INDEX `expirationTs` (`expirationTs`)
)COLLATE='utf8mb4_general_ci' ENGINE=InnoDB;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DROP TABLE `token`;
DROP TABLE `twofaCode`;
DROP TABLE `user`;
SQL;
    }
}

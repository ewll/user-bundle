<?php namespace Ewll\UserBundle\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20190827153400 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'user, userSession, userRecovery';
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
    `emailConfirmationCode` VARCHAR (64) NULL,
    `isEmailConfirmed` TINYINT(3) UNSIGNED NOT NULL,
    `accessRights` TEXT NOT NULL DEFAULT '[]',
    `createdTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `email_pass` (`email`, `pass`),
    UNIQUE INDEX `email` (`email`),
    UNIQUE INDEX `emailConfirmationCode` (`emailConfirmationCode`)
)COLLATE='utf8mb4_general_ci' ENGINE=InnoDB;

CREATE TABLE `userSession` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `userId` INT(20) UNSIGNED NOT NULL,
    `crypt` VARCHAR(64) NOT NULL,
    `token` VARCHAR(64) NOT NULL,
    `ip` VARCHAR(39) NOT NULL,
    `lastActionTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `createdTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `crypt` (`crypt`)
)COLLATE='utf8mb4_general_ci' ENGINE=InnoDB;

CREATE TABLE `userRecovery` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `userId` BIGINT(20) UNSIGNED NOT NULL,
    `code` VARCHAR(64) NOT NULL,
    `ip` VARCHAR(39) NOT NULL,
    `isUsed` TINYINT(3) UNSIGNED NOT NULL,
    `createdTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `userId` (`userId`),
    UNIQUE INDEX `code` (`code`)
)COLLATE='utf8mb4_general_ci' ENGINE=InnoDB;

CREATE TABLE `twofaCode` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `userId` BIGINT(20) UNSIGNED NOT NULL,
    `twofaTypeId` TINYINT(3) UNSIGNED NOT NULL,
    `actionId` TINYINT(3) UNSIGNED NOT NULL,
    `contact` VARCHAR(64) NOT NULL,
    `code` VARCHAR(6) NOT NULL,
    `isUsed` TINYINT(3) UNSIGNED NOT NULL,
    `createdTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `userId` (`userId`),
    INDEX `twofaTypeId` (`twofaTypeId`),
    INDEX `actionId` (`actionId`)
)COLLATE='utf8mb4_general_ci' ENGINE=InnoDB;

CREATE TABLE `oauthToken` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(64) NOT NULL,
    `token` VARCHAR(64) NOT NULL,
    `ip` VARCHAR(39) NOT NULL,
    `createdTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `email` (`email`),
    UNIQUE INDEX `token` (`token`)
)COLLATE='utf8mb4_general_ci' ENGINE=InnoDB;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DROP TABLE `oauthToken`;
DROP TABLE `twofaCode`;
DROP TABLE `userRecovery`;
DROP TABLE `userSession`;
DROP TABLE `user`;
SQL;
    }
}

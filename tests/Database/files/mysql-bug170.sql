DROP DATABASE IF EXISTS nette_test;
CREATE DATABASE nette_test;
USE nette_test;

CREATE TABLE `Country` (
    `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `Region` (
    `order` int(1) unsigned NOT NULL,
    `countryId` int(4) unsigned NOT NULL,
    PRIMARY KEY (`countryId`, `order`),
    CONSTRAINT `Region_ibfk_2` FOREIGN KEY (`countryId`) REFERENCES `Country` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `Operator1` (
    `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
    `countryId` int(4) unsigned NOT NULL,
    `regionOrder` int(1) unsigned NOT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `Operator_ibfk_1` FOREIGN KEY (`countryId`, `regionOrder`) REFERENCES `Region` (`countryId`, `order`),
    CONSTRAINT `Operator_ibfk_2` FOREIGN KEY (`countryId`) REFERENCES `Country` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `Operator2` (
    `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
    `countryId` int(4) unsigned NOT NULL,
    `regionOrder` int(1) unsigned NOT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `Operator2_ibfk_1` FOREIGN KEY (`countryId`) REFERENCES `Country` (`id`),
    CONSTRAINT `Operator2_ibfk_2` FOREIGN KEY (`countryId`, `regionOrder`) REFERENCES `Region` (`countryId`, `order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- NOTE: Order of foreign keys to tables Region and Country is reversed in Operator2 table compared to Operator1 table

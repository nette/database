DROP DATABASE IF EXISTS nette_test;
CREATE DATABASE nette_test;
USE nette_test;

CREATE TABLE `Photo` (
  `number` int(4) unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

INSERT INTO `Photo` (`number`) VALUES (1), (2), (3);

CREATE TABLE `PhotoNonPublic` (
  `number` int(4) unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`number`),
  CONSTRAINT `PhotoNonPublic_ibfk_1` FOREIGN KEY (`number`) REFERENCES `Photo` (`number`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

INSERT INTO `PhotoNonPublic` (`number`) VALUES (2), (3);

DROP DATABASE IF EXISTS nette_test;
CREATE DATABASE nette_test;
USE nette_test;

CREATE TABLE simple_pk_autoincrement (
	identifier1 int NOT NULL AUTO_INCREMENT,
	note varchar(100),
	PRIMARY KEY (identifier1)
) ENGINE=InnoDB;

CREATE TABLE simple_pk_no_autoincrement (
	identifier1 int NOT NULL,
	note varchar(100),
	PRIMARY KEY (identifier1)
) ENGINE=InnoDB;

CREATE TABLE multi_pk_no_autoincrement (
	identifier1 int NOT NULL,
	identifier2 int NOT NULL,
	note varchar(100),
	PRIMARY KEY (identifier1, identifier2)
) ENGINE=InnoDB;

CREATE TABLE multi_pk_autoincrement(
	identifier1 int NOT NULL AUTO_INCREMENT,
	identifier2 int NOT NULL,
	note varchar(100),
	PRIMARY KEY (identifier1, identifier2)
) ENGINE=InnoDB;

CREATE TABLE no_pk (
	note varchar(100)
) ENGINE=InnoDB;

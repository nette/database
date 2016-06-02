/*!40102 SET storage_engine = InnoDB */;

DROP DATABASE IF EXISTS nette_test;
CREATE DATABASE nette_test;
USE nette_test;


CREATE TABLE simple_pk (
	id int NOT NULL,
	name varchar(100),
	PRIMARY KEY (id)
);

CREATE TABLE composite_pk (
	id1 int NOT NULL,
	id2 int NOT NULL,
	name varchar(100),
	PRIMARY KEY (id1, id2)
);

CREATE TABLE composite_pk_ai (
	id1 int NOT NULL AUTO_INCREMENT,
	id2 int NOT NULL,
	name varchar(100),
	PRIMARY KEY (id1, id2)
);

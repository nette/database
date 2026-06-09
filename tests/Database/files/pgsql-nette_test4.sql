DROP SCHEMA IF EXISTS public CASCADE;
CREATE SCHEMA public;

CREATE TABLE simple_pk_autoincrement (
	identifier1 serial NOT NULL,
	note varchar(100),
	PRIMARY KEY (identifier1)
);

CREATE TABLE simple_pk_no_autoincrement (
	identifier1 int NOT NULL,
	note varchar(100),
	PRIMARY KEY (identifier1)
);

CREATE TABLE multi_pk_no_autoincrement (
	identifier1 int NOT NULL,
	identifier2 int NOT NULL,
	note varchar(100),
	PRIMARY KEY (identifier1, identifier2)
);

CREATE TABLE multi_pk_autoincrement(
	identifier1 serial NOT NULL,
	identifier2 int NOT NULL,
	note varchar(100),
	PRIMARY KEY (identifier1, identifier2)
);

CREATE TABLE no_pk (
	note varchar(100)
);

CREATE TABLE string_pk (
	identifier1 varchar(20) NOT NULL,
	note varchar(100),
	PRIMARY KEY (identifier1)
);

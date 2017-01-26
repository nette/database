DROP TABLE IF EXISTS simple_pk_autoincrement;
DROP TABLE IF EXISTS simple_pk_no_autoincrement;
DROP TABLE IF EXISTS multi_pk_no_autoincrement;
DROP TABLE IF EXISTS multi_pk_autoincrement;
DROP TABLE IF EXISTS no_pk;

CREATE TABLE simple_pk_autoincrement (
	identifier1 integer PRIMARY KEY AUTOINCREMENT,
	note varchar(100)
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

CREATE TABLE no_pk (
	note varchar(100)
);

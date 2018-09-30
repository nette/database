IF OBJECT_ID('simple_pk_autoincrement', 'U') IS NOT NULL DROP TABLE simple_pk_autoincrement;
IF OBJECT_ID('simple_pk_no_autoincrement', 'U') IS NOT NULL DROP TABLE simple_pk_no_autoincrement;
IF OBJECT_ID('multi_pk_no_autoincrement', 'U') IS NOT NULL DROP TABLE multi_pk_no_autoincrement;
IF OBJECT_ID('multi_pk_autoincrement', 'U') IS NOT NULL DROP TABLE multi_pk_autoincrement;
IF OBJECT_ID('no_pk', 'U') IS NOT NULL DROP TABLE no_pk;


CREATE TABLE simple_pk_autoincrement (
	identifier1 int NOT NULL IDENTITY(1,1),
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
	note varchar(100)
);
ALTER TABLE multi_pk_no_autoincrement ADD CONSTRAINT PK_multi_pk_no_autoincrement PRIMARY KEY CLUSTERED (identifier1, identifier2);

CREATE TABLE multi_pk_autoincrement(
	identifier1 int NOT NULL IDENTITY(1,1),
	identifier2 int NOT NULL,
	note varchar(100)
);
ALTER TABLE multi_pk_autoincrement ADD CONSTRAINT PK_multi_pk_autoincrement PRIMARY KEY CLUSTERED (identifier1, identifier2);

CREATE TABLE no_pk (
	note varchar(100)
);

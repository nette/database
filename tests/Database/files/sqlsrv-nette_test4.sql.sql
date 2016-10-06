IF OBJECT_ID('simple_pk', 'U') IS NOT NULL DROP TABLE simple_pk;
IF OBJECT_ID('composite_pk', 'U') IS NOT NULL DROP TABLE composite_pk;
IF OBJECT_ID('composite_pk_ai', 'U') IS NOT NULL DROP TABLE composite_pk_ai;

CREATE TABLE simple_pk (
	id INTEGER NOT NULL,
	name TEXT,
	PRIMARY KEY (id)
);

CREATE TABLE composite_pk (
	id1 INTEGER NOT NULL,
	id2 INTEGER NOT NULL,
	name TEXT,
	PRIMARY KEY (id1, id2)
);

CREATE TABLE composite_pk_ai (
	id1 INTEGER NOT NULL IDENTITY(1,1),
	id2 INTEGER NOT NULL,
	name TEXT,
	PRIMARY KEY (id1, id2)
);

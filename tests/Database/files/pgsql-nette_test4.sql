DROP SCHEMA IF EXISTS public CASCADE;
CREATE SCHEMA public;

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
	id1 serial NOT NULL,
	id2 int NOT NULL,
	name varchar(100),
	PRIMARY KEY (id1, id2)
);

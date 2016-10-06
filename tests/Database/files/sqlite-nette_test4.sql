DROP TABLE IF EXISTS simple_pk;
DROP TABLE IF EXISTS composite_pk;

CREATE TABLE simple_pk (
	id INT NOT NULL,
	name TEXT,
	PRIMARY KEY (id)
);

CREATE TABLE composite_pk (
	id1 int NOT NULL,
	id2 int NOT NULL,
	name TEXT,
	PRIMARY KEY (id1, id2)
);

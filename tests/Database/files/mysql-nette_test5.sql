DROP DATABASE IF EXISTS nette_test;
CREATE DATABASE nette_test;
USE nette_test;

CREATE TABLE room (
	id INTEGER PRIMARY KEY
) ENGINE=InnoDB;

CREATE TABLE person (
	username VARCHAR(2) PRIMARY KEY
) ENGINE=InnoDB;

CREATE TABLE computer (
	id INTEGER PRIMARY KEY,
	room_id INTEGER NOT NULL,
	owner_id VARCHAR(2) NOT NULL,
	CONSTRAINT room_id FOREIGN KEY (room_id) REFERENCES room (id),
	CONSTRAINT owner_id FOREIGN KEY (owner_id) REFERENCES person (username)
) ENGINE=InnoDB;

INSERT INTO room (id) VALUES (1000);

INSERT INTO person (username) VALUES ('mh');

INSERT INTO computer (id, room_id, owner_id) VALUES (1, 1000, 'mh');

IF OBJECT_ID('computer', 'U') IS NOT NULL DROP TABLE computer;
IF OBJECT_ID('person', 'U') IS NOT NULL DROP TABLE person;
IF OBJECT_ID('room', 'U') IS NOT NULL DROP TABLE room;


CREATE TABLE room (
	id INTEGER PRIMARY KEY
);

CREATE TABLE person (
	username VARCHAR(2) PRIMARY KEY
);

CREATE TABLE computer (
	id INTEGER PRIMARY KEY,
	room_id INTEGER NOT NULL REFERENCES room (id),
	owner_id VARCHAR(2) NOT NULL REFERENCES person (username)
);

INSERT INTO room (id) VALUES (1000);

INSERT INTO person (username) VALUES ('mh');

INSERT INTO computer (id, room_id, owner_id) VALUES (1, 1000, 'mh');

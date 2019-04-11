DROP TABLE IF EXISTS computer;
DROP TABLE IF EXISTS person;
DROP TABLE IF EXISTS room;

CREATE TABLE room (
	id INTEGER PRIMARY KEY
);

CREATE TABLE person (
	username TEXT PRIMARY KEY
);

CREATE TABLE computer (
	id INTEGER PRIMARY KEY,
	room_id INTEGER NOT NULL REFERENCES room (id),
	owner_id TEXT NOT NULL REFERENCES person (username)
);

INSERT INTO room (id) VALUES (1000);

INSERT INTO person (username) VALUES ('mh');

INSERT INTO computer (id, room_id, owner_id) VALUES (1, 1000, 'mh');

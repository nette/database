SET @@sql_mode = sys.list_drop(@@sql_mode, 'ONLY_FULL_GROUP_BY');

DROP DATABASE IF EXISTS nette_test;
CREATE DATABASE nette_test;
USE nette_test;

SET FOREIGN_KEY_CHECKS = 0;



CREATE TABLE author (
	id int NOT NULL AUTO_INCREMENT,
	name varchar(30) NOT NULL,
	web varchar(100) NOT NULL COMMENT 'Author\'s website URL',
	born date DEFAULT NULL,
	PRIMARY KEY(id)
) ENGINE=InnoDB AUTO_INCREMENT=13 COMMENT='Table containing book authors';

INSERT INTO author (id, name, web, born) VALUES (11, 'Jakub Vrana', 'http://www.vrana.cz/', NULL);
INSERT INTO author (id, name, web, born) VALUES (12, 'David Grudl', 'http://davidgrudl.com/', NULL);
INSERT INTO author (id, name, web, born) VALUES (13, 'Geek', 'http://example.com', NULL);



CREATE TABLE tag (
	id int NOT NULL AUTO_INCREMENT,
	name varchar(20) NOT NULL,
	PRIMARY KEY (id)
) ENGINE=InnoDB AUTO_INCREMENT=25;

INSERT INTO tag (id, name) VALUES (21, 'PHP');
INSERT INTO tag (id, name) VALUES (22, 'MySQL');
INSERT INTO tag (id, name) VALUES (23, 'JavaScript');
INSERT INTO tag (id, name) VALUES (24, 'Neon');



CREATE TABLE book (
	id int NOT NULL AUTO_INCREMENT,
	author_id int NOT NULL,
	translator_id int,
	title varchar(50) NOT NULL,
	next_volume int,
	PRIMARY KEY (id),
	CONSTRAINT book_author FOREIGN KEY (author_id) REFERENCES author (id),
	CONSTRAINT book_translator FOREIGN KEY (translator_id) REFERENCES author (id),
	CONSTRAINT book_volume FOREIGN KEY (next_volume) REFERENCES book (id)
) ENGINE=InnoDB AUTO_INCREMENT=5;

CREATE INDEX book_title ON book (title);

INSERT INTO book (id, author_id, translator_id, title) VALUES (1, 11, 11, '1001 tipu a triku pro PHP');
INSERT INTO book (id, author_id, translator_id, title) VALUES (2, 11, NULL, 'JUSH');
INSERT INTO book (id, author_id, translator_id, title) VALUES (3, 12, 12, 'Nette');
INSERT INTO book (id, author_id, translator_id, title) VALUES (4, 12, 12, 'Dibi');



CREATE TABLE book_tag (
	book_id int NOT NULL,
	tag_id int NOT NULL,
	PRIMARY KEY (book_id, tag_id),
	CONSTRAINT book_tag_tag FOREIGN KEY (tag_id) REFERENCES tag (id),
	CONSTRAINT book_tag_book FOREIGN KEY (book_id) REFERENCES book (id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO book_tag (book_id, tag_id) VALUES (1, 21);
INSERT INTO book_tag (book_id, tag_id) VALUES (3, 21);
INSERT INTO book_tag (book_id, tag_id) VALUES (4, 21);
INSERT INTO book_tag (book_id, tag_id) VALUES (1, 22);
INSERT INTO book_tag (book_id, tag_id) VALUES (4, 22);
INSERT INTO book_tag (book_id, tag_id) VALUES (2, 23);



CREATE TABLE book_tag_alt (
	book_id int NOT NULL,
	tag_id int NOT NULL,
	state varchar(30),
	PRIMARY KEY (book_id, tag_id),
	CONSTRAINT book_tag_alt_tag FOREIGN KEY (tag_id) REFERENCES tag (id),
	CONSTRAINT book_tag_alt_book FOREIGN KEY (book_id) REFERENCES book (id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO book_tag_alt (book_id, tag_id, state) VALUES (3, 21, 'public');
INSERT INTO book_tag_alt (book_id, tag_id, state) VALUES (3, 22, 'private');
INSERT INTO book_tag_alt (book_id, tag_id, state) VALUES (3, 23, 'private');
INSERT INTO book_tag_alt (book_id, tag_id, state) VALUES (3, 24, 'public');



CREATE TABLE note (
	book_id int NOT NULL,
	note varchar(100),
	CONSTRAINT note_book FOREIGN KEY (book_id) REFERENCES book (id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

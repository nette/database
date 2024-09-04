IF OBJECT_ID('products', 'U') IS NOT NULL DROP TABLE products;

CREATE TABLE products (
	product_id int NOT NULL IDENTITY(11,1),
	title varchar(50) NOT NULL,
	PRIMARY KEY(product_id)
);

SET IDENTITY_INSERT products ON;
INSERT INTO products (product_id, title) VALUES (1, 'Chair');
INSERT INTO products (product_id, title) VALUES (2, 'Table');
INSERT INTO products (product_id, title) VALUES (3, 'Computer');
SET IDENTITY_INSERT products OFF;

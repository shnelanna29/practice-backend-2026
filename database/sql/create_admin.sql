-- Create admin user for demo/testing
INSERT INTO users (name, email, password, role, created_at, updated_at)
VALUES ('Admin', 'admin@studio.com', '$2y$10$bCt0Uq9BQ0oWTO/TTj6S9Oiy4WiF5Tu/xV0W53T02ViTFOA9uKkEu', 'admin', NOW(), NOW());

-- Dedicated test database (phpunit.xml pins DB_DATABASE=emails_test, force=true).
-- Runs once on first volume init via /docker-entrypoint-initdb.d.
CREATE DATABASE emails_test;

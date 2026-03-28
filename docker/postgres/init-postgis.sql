-- docker/postgres/init-extensions.sql
-- PostGIS will be added in Plan 2 when geospatial queries are needed
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

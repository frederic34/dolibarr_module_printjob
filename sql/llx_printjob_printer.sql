-- Copyright (C) 2025  Frédéric France  <frederic.france@free.fr>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE IF NOT EXISTS llx_printjob_printer (
	rowid        INT            NOT NULL AUTO_INCREMENT,
	entity       INT            NOT NULL DEFAULT 1,
	name         VARCHAR(255)   NOT NULL,
	status       VARCHAR(50)    NOT NULL DEFAULT 'unknown',
	is_accepting TINYINT(1)     NOT NULL DEFAULT 0,
	device_uri   VARCHAR(512)   NULL,
	options      TEXT           NULL,
	date_creation DATETIME      NOT NULL,
	tms          TIMESTAMP      NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (rowid),
	UNIQUE KEY uk_printjob_printer_name (entity, name)
) ENGINE=InnoDB;

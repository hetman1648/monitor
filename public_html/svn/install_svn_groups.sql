-- Shared SVN site groups (visible to all users) used by the multi-site SVN Updater.
-- Run once on the monitor DB. Safe to re-run (IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS svn_groups (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  created_by INT NOT NULL DEFAULT 0,
  date_added DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS svn_group_sites (
  group_id INT UNSIGNED NOT NULL,
  repository VARCHAR(255) NOT NULL,
  PRIMARY KEY (group_id, repository),
  KEY repository (repository)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

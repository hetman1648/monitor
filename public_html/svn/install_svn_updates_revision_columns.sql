-- Run once on the monitor DB so new history rows store revision + log message.
-- If columns already exist, skip or adjust.

ALTER TABLE svn_updates
  ADD COLUMN revision VARCHAR(32) NULL DEFAULT NULL AFTER repository,
  ADD COLUMN commit_message TEXT NULL AFTER revision;

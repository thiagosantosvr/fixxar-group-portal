-- FIXXAR GROUP — migration 002
-- Run once on existing installations after checking SHOW INDEX FROM admin_audit_log.
-- The fresh-install schema already includes this index.
ALTER TABLE admin_audit_log
  ADD INDEX idx_audit_login_throttle (action, ip_hash, created_at);

-- FIXXAR GROUP: perfil individual de contato e identidade visual por marca.
-- Execute uma única vez no banco do portal; não altera config.php nem outras tabelas.
ALTER TABLE brand_universes
  ADD COLUMN logo_url VARCHAR(500) NULL,
  ADD COLUMN cover_image_url VARCHAR(500) NULL,
  ADD COLUMN contact_email VARCHAR(190) NULL,
  ADD COLUMN contact_phone VARCHAR(80) NULL,
  ADD COLUMN whatsapp_url VARCHAR(500) NULL,
  ADD COLUMN instagram_url VARCHAR(500) NULL,
  ADD COLUMN facebook_url VARCHAR(500) NULL,
  ADD COLUMN youtube_url VARCHAR(500) NULL,
  ADD COLUMN linkedin_url VARCHAR(500) NULL,
  ADD COLUMN contact_address VARCHAR(500) NULL;

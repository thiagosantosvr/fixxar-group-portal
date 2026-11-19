-- FIXXAR GROUP — MySQL 8 / MariaDB compatible schema
-- Apply once to a new, dedicated database. Never run against an unrelated project.

CREATE TABLE IF NOT EXISTS admin_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('owner','administrator','editor','support') NOT NULL DEFAULT 'editor',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_pages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(100) NOT NULL UNIQUE,
  title VARCHAR(180) NOT NULL,
  content_json JSON NOT NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pages_admin FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS brand_universes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(100) NOT NULL UNIQUE,
  name VARCHAR(180) NOT NULL,
  eyebrow VARCHAR(180) NOT NULL DEFAULT '',
  description TEXT NOT NULL,
  detail TEXT NOT NULL,
  category VARCHAR(80) NOT NULL,
  website_url VARCHAR(500) NULL,
  status ENUM('active','in-development','archived') NOT NULL DEFAULT 'in-development',
  sort_order INT NOT NULL DEFAULT 0,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_universes_admin FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL,
  INDEX idx_universes_order (sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL,
  subject VARCHAR(180) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('new','in_progress','done','archived') NOT NULL DEFAULT 'new',
  assigned_to BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_messages_admin FOREIGN KEY (assigned_to) REFERENCES admin_users(id) ON DELETE SET NULL,
  INDEX idx_messages_status_date (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media_assets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  original_name VARCHAR(255) NOT NULL,
  storage_name VARCHAR(255) NOT NULL UNIQUE,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL,
  alt_text VARCHAR(255) NOT NULL DEFAULT '',
  uploaded_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_media_admin FOREIGN KEY (uploaded_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_settings (
  setting_key VARCHAR(120) NOT NULL PRIMARY KEY,
  setting_value JSON NOT NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_settings_admin FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_audit_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  admin_user_id BIGINT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id VARCHAR(100) NULL,
  details_json JSON NULL,
  ip_hash CHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE SET NULL,
  INDEX idx_audit_date (created_at),
  INDEX idx_audit_entity (entity_type, entity_id),
  INDEX idx_audit_login_throttle (action, ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Initial institutional content. INSERT IGNORE keeps re-imports non-destructive.
INSERT IGNORE INTO brand_universes
(slug,name,eyebrow,description,detail,category,website_url,status,sort_order)
VALUES
('agencia','Fixxar Agência','Estratégia & comunicação','Marcas, experiências e presença digital construídas com intenção.','Estratégia, comunicação, branding e soluções digitais.','Estratégia','https://agencia.fixxarestudios.com.br','active',1),
('fotografia','Fixxar Fotografia','Imagem & experiência','Fotografia profissional, histórias e experiências que permanecem.','Fotografia profissional, álbuns e formação.','Imagem','https://thiago.fixxarestudios.com.br','active',2),
('editora','Entre o Céu e Papel','Projetos editoriais','Ideias transformadas em livros, conhecimento e memória.','Editora independente, projetos editoriais e consultoria acadêmica.','Editorial',NULL,'in-development',3),
('tio-moises','Tio Moisés','Universo infantil','Histórias, conteúdos educativos e experiências para crianças.','Conteúdo educativo, histórias, personagens e experiências.','Infantil',NULL,'in-development',4),
('brindes','Fixxar Brindes','Personalização','Ideias que ganham forma em produtos e presentes personalizados.','Brindes e soluções de personalização.','Personalização',NULL,'in-development',5);

INSERT IGNORE INTO content_pages (slug,title,content_json,status)
VALUES ('home','Página inicial','{"headline":"Ideias que ganham forma.","subheadline":"Marcas que deixam marcas.","intro":"Somos um grupo que conecta criatividade, comunicação, conhecimento e inovação para transformar ideias em experiências com significado.","groupHeadline":"Um grupo. Muitas formas de transformar.","groupLead":"A FIXXAR GROUP reúne diferentes competências em um ecossistema que transforma ideias em projetos, marcas e experiências.","groupBody":"Atuamos em universos distintos, mas conectados por uma mesma visão: fazer bem-feito, com propósito, consistência e atenção aos detalhes.","processHeadline":"O movimento por trás da ideia.","processIntro":"Uma ideia merece mais do que existir. Ela merece encontrar seu caminho, ganhar consistência e chegar às pessoas.","processSteps":[{"title":"Imaginar","text":"Enxergar possibilidades e descobrir o que ainda pode existir."},{"title":"Construir","text":"Dar estrutura, linguagem e intenção para cada projeto."},{"title":"Conectar","text":"Aproximar ideias, pessoas e experiências que fazem sentido."},{"title":"Transformar","text":"Levar o que foi imaginado para o mundo real."}]}','published');

-- Atualização incremental FIXXAR GROUP: cadastrar YAD Sistemas Integrados.
-- Seguro para executar mais de uma vez; não altera os demais universos.
INSERT IGNORE INTO brand_universes
(slug,name,eyebrow,description,detail,category,website_url,status,sort_order)
VALUES
('yad-sistemas-integrados','YAD Sistemas Integrados','Tecnologia & integração','Soluções integradas para conectar processos, sistemas e operações.','Unidade de tecnologia dedicada a sistemas integrados e soluções digitais. Informações comerciais e endereço oficial serão definidos na etapa de lançamento.','Tecnologia',NULL,'in-development',6);

-- ════════════════════════════════════════════════════════════════════
-- F3 Tarefa 01 — Seed do conteúdo REAL do davidalmeida.xyz (Produção 18/10/2026)
-- Análise §3 + raspagem AUTORIZADA de TEXTOS públicos (imagens não migradas —
-- política: placeholders até upload via admin; user constraint F3-01).
-- IDEMPOTENTE: DELETE das âncoras + INSERT; rodável N vezes mesmo resultado.
-- Ajuste o prefixo das tabelas se não for rms_.
-- Descrições/keywords: somente EN (language_id=1); coalesce atende pt/es.
-- ════════════════════════════════════════════════════════════════════

-- ─── CATEGORIAS (3 — nomes reais da produção) ────────────────────────────────

DELETE FROM `rms_category` WHERE `category_id` BETWEEN 1 AND 3;
INSERT INTO `rms_category` (`category_id`,`parent_id`,`image`,`status`,`sort_order`,`date_added`,`date_modified`) VALUES
 (1,0,'',1,1,NOW(),NOW()),
 (2,0,'',1,2,NOW(),NOW()),
 (3,0,'',1,3,NOW(),NOW());
DELETE FROM `rms_category_description` WHERE `category_id` BETWEEN 1 AND 3;
INSERT INTO `rms_category_description` (`category_id`,`language_id`,`name`,`description`,`meta_title`,`meta_description`) VALUES
 (1,1,'Visual Identity','Logos, brand systems and identity manuals.','Visual Identity','Visual identity projects by David L. Almeida.'),
 (2,1,'Brand','Brand design, art direction and campaign look.','Brand','Brand design projects by David L. Almeida.'),
 (3,1,'Illustration','Digital art and illustration work.','Illustration','Illustration projects by David L. Almeida.');

-- ─── PROJETOS (10 — nomes/slugs reais da produção; keywords preservadas p/ SEO) ─

DELETE FROM `rms_project` WHERE `project_id` BETWEEN 1 AND 10;
INSERT INTO `rms_project` (`project_id`,`image`,`client`,`url`,`status`,`featured`,`sort_order`,`date_added`,`date_modified`) VALUES
 (1,'','Juvenile World Academy','',1,1,1,NOW(),NOW()),
 (2,'','','',1,1,2,NOW(),NOW()),
 (3,'','','',1,1,3,NOW(),NOW()),
 (4,'','','',1,1,4,NOW(),NOW()),
 (5,'','Hiperion Gamer','https://hiperiongamer.com/',1,0,5,NOW(),NOW()),
 (6,'','','',1,0,6,NOW(),NOW()),
 (7,'','','',1,0,7,NOW(),NOW()),
 (8,'','','',1,0,8,NOW(),NOW()),
 (9,'','','',1,0,9,NOW(),NOW()),
 (10,'','','',1,0,10,NOW(),NOW());

DELETE FROM `rms_project_description` WHERE `project_id` BETWEEN 1 AND 10;
INSERT INTO `rms_project_description` (`project_id`,`language_id`,`name`,`summary`,`description`,`tag`,`meta_title`,`meta_description`) VALUES
 (1,1,'JWA','Visual identity for Juvenile World Academy.','Complete visual identity for the Juvenile World Academy — manual, stationery and applications, from the logo to colors, typography and graphics.','visual-identity,logo,brand-manual','JWA - Juvenile World Academy','Visual identity project for Juvenile World Academy by David L. Almeida.'),
 (2,1,'Steel of Death','Visual identity for the band Steel of Death.','Visual identity for Steel of Death — logo, album cover and brand applications.','visual-identity,band,album-cover','Steel of Death','Visual identity for the band Steel of Death by David L. Almeida.'),
 (3,1,'Farmer Life Simulation','Brand design for the game Farmer Life Simulation.','Brand design for Farmer Life Simulation — logo and communication look for the farming simulator game.','brand,game,logo','Farmer Life Simulation','Brand design for the game Farmer Life Simulation by David L. Almeida.'),
 (4,1,'Chameleon Digital Art','Digital art illustration project.','Chameleon digital art — color study and character illustration.','illustration,digital-art,chameleon','Chameleon Digital Art','Chameleon digital art illustration by David L. Almeida.'),
 (5,1,'Hiperion Gamer','Brand design for Hiperion Gamer.','Brand design for Hiperion Gamer — external gaming portal.','brand,gaming,logo','Hiperion Gamer','Brand design for Hiperion Gamer by David L. Almeida.'),
 (6,1,'RC - Rogério Consultoria','Visual identity for RC Rogério Consultoria.','Visual identity for RC - Rogério Consultoria (consulting brand) — logo and corporate applications.','visual-identity,consulting,logo','RC - Rogério Consultoria','Visual identity for RC Rogério Consultoria by David L. Almeida.'),
 (7,1,'Pitbull Colors','Digital art for Pitbull Colors.','Pitbull color tint study — digital artwork.','illustration,digital-art,color-study','Pitbull Colors','Pitbull Colors digital art by David L. Almeida.'),
 (8,1,'Cat Fe - Digital Art','Digital art portrait.','Cat Fe digital art — character portrait study.','illustration,digital-art,portrait','Cat Fe - Digital Art','Cat Fe digital art by David L. Almeida.'),
 (9,1,'Classic Car Race','Digital art of a classic race car.','Classic car race artwork — vehicle illustration study.','illustration,digital-art,vehicle','Classic Car Race','Classic car race digital art by David L. Almeida.'),
 (10,1,'Selva Dev','Visual identity for Selva Dev.','Visual identity for the Selva Dev developer brand — logo and brand applications.','visual-identity,developer,logo','Selva Dev','Visual identity for Selva Dev by David L. Almeida.');

DELETE FROM `rms_project_to_category` WHERE `project_id` BETWEEN 1 AND 10;
INSERT INTO `rms_project_to_category` (`project_id`,`category_id`) VALUES
 (1,1),(2,1),(6,1),(10,1),   -- Visual Identity
 (3,2),(5,2),                -- Brand
 (4,3),(7,3),(8,3),(9,3);    -- Illustration

-- SEO do portfólio: keywords EXATAS da produção (inclui o legado "pitbul-color"
-- e as curtas "farmer"/"chameleon"/"rc"/"classic-car" — preservadas p/ §9 da Análise).
DELETE FROM `rms_seo_url` WHERE `key` LIKE 'project_id=%' OR `key` IN ('page_id=1','page_id=2') OR `value` IN ('cookies','politicas-internas');
INSERT INTO `rms_seo_url` (`key`,`value`,`language_id`,`sort_order`) VALUES
 ('project_id=1','jwa',1,0),
 ('project_id=2','steel-of-death',1,0),
 ('project_id=3','farmer',1,0),
 ('project_id=4','chameleon',1,0),
 ('project_id=5','hiperion-gamer',1,0),
 ('project_id=6','rc',1,0),
 ('project_id=7','pitbul-color',1,0),
 ('project_id=8','cat-fe',1,0),
 ('project_id=9','classic-car',1,0),
 ('project_id=10','selva-dev',1,0);

-- ─── DEPOIMENTOS (3 — textos REAIS da produção) ─────────────────────────────

DELETE FROM `rms_testimonial` WHERE `testimonial_id` BETWEEN 1 AND 3;
INSERT INTO `rms_testimonial` (`testimonial_id`,`image`,`status`,`sort_order`,`date_added`) VALUES
 (1,'',1,1,NOW()),(2,'',1,2,NOW()),(3,'',1,3,NOW());
DELETE FROM `rms_testimonial_description` WHERE `testimonial_id` BETWEEN 1 AND 3;
INSERT INTO `rms_testimonial_description` (`testimonial_id`,`language_id`,`author`,`company`,`text`) VALUES
 (1,1,'Silvio Robson B. Lima','GR Produtora','Best professional in the field I''ve ever met. Tact for details, technical eye and fair price.'),
 (2,1,'Pedrinho Seus Brinquedos','','David is a sensational professional, I highly recommend him!'),
 (3,1,'Bruno Reis','','About the Work I have no words to describe the work of this excellent professional, knowledge attention incredible quality a person with enormous knowledge willing to always give the best of what you ask for, calmly and coherently showing that your idea can be even better.');

-- ─── CLIENTES (5 — nomes reais; logos sobem via admin depois) ────────────────

DELETE FROM `rms_client_logo` WHERE `client_logo_id` BETWEEN 1 AND 5;
INSERT INTO `rms_client_logo` (`client_logo_id`,`name`,`image`,`url`,`status`,`sort_order`) VALUES
 (1,'SEVEN A','','',1,1),
 (2,'Alex Cristopher','','',1,2),
 (3,'BDRProPlayer','','',1,3),
 (4,'AJL Sorvete Express','','',1,4),
 (5,'Horror na Veia','','',1,5);

-- ─── FERRAMENTAS (4 — descrições reais, traduzidas p/ EN padrão ADR 0001) ────

DELETE FROM `rms_tool` WHERE `tool_id` BETWEEN 1 AND 4;
-- URLs externas reais ficam a cargo do mantenedor (produção resolve via
-- /catalog/tools/open?id=N — destino final não consta no HTML público; NÃO inventar).
INSERT INTO `rms_tool` (`tool_id`,`image`,`vendor`,`url`,`status`,`sort_order`,`date_added`) VALUES
 (1,'','DavidCreator','',1,1,NOW()),
 (2,'','DavidCreator','',1,2,NOW()),
 (3,'','DavidCreator','',1,3,NOW()),
 (4,'','DavidCreator','',1,4,NOW());
DELETE FROM `rms_tool_description` WHERE `tool_id` BETWEEN 1 AND 4;
INSERT INTO `rms_tool_description` (`tool_id`,`language_id`,`name`,`description`) VALUES
 (1,1,'Fluxa','Financial management and indicator tracking tool.'),
 (2,1,'Quotia','Platform to request and organize quotes in a single flow.'),
 (3,1,'Vertex','Free resume builder focused on clarity and professional presentation.'),
 (4,1,'Solis','Strategic generator for social media content planning and posting.');

-- ─── PÁGINAS LEGAIS (2 — conteúdo real da produção) ──────────────────────────

DELETE FROM `rms_page` WHERE `page_id` BETWEEN 1 AND 2;
INSERT INTO `rms_page` (`page_id`,`status`,`sort_order`,`date_added`,`date_modified`) VALUES
 (1,1,1,NOW(),NOW()),(2,1,2,NOW(),NOW());
DELETE FROM `rms_page_description` WHERE `page_id` BETWEEN 1 AND 2;
INSERT INTO `rms_page_description` (`page_id`,`language_id`,`title`,`description`,`meta_title`,`meta_description`) VALUES
 (1,1,'Cookie Policy','<h3>1. What cookies are</h3><p>Cookies are small files stored in your browser to keep essential features running and, when allowed, collect usage data for analytics.</p><h3>2. How we use cookies</h3><p>We use necessary cookies for technical operation and analytics cookies only when you allow that category.</p><h3>3. Categories and examples</h3><p><strong>Necessary</strong> — PHPSESSID, dc_cookie_consent: session, security, session language and storing your consent choice (session / up to 180 days).</p><p><strong>Analytics (optional)</strong> — _ga, _gid (Google Analytics): measure traffic, performance and aggregated behavior to improve the site (retention per provider policy).</p><p><strong>Marketing (optional)</strong> — currently not used.</p><h3>4. Your rights and control</h3><p>You can accept, reject or update your preferences at any time using the cookie button in the corner of the page.</p><h3>5. Privacy contact</h3><p>For privacy and personal data questions, use the <a href="/#contact">site contact area</a>.</p>','Cookie Policy','How cookies are used on davidalmeida.xyz.'),
 (2,1,'Internal Policies','<h3>1. Operational security</h3><p>We apply access controls, event monitoring and periodic reviews to reduce technical risk and protect the platform.</p><h3>2. Data handling and retention</h3><p>Contact data is used only for support and professional relationship. Storage follows minimization and necessary retention principles.</p><h3>3. Incident response</h3><p>In relevant incidents, we activate technical analysis, containment, traceability and communication according to impact level.</p><h3>4. Transparency and rights</h3><p>Privacy and personal data requests can be sent through the official contact channels of this website.</p>','Internal Policies','Internal policies of davidalmeida.xyz.');
INSERT INTO `rms_seo_url` (`key`,`value`,`language_id`,`sort_order`) VALUES
 ('page_id=1','cookies',1,0),
 ('page_id=2','politicas-internas',1,0);

-- Fixture sanitizada para o Tool F9-03; não contém dados reais nem credenciais.
SET NAMES utf8mb4;

CREATE TABLE `f903_safe_parent` (
  `id` int(10) UNSIGNED NOT NULL,
  `safe_label` varchar(64) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `f903_safe_child` (
  `id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED NOT NULL,
  `safe_note` varchar(64) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `f903_safe_child` (`id`, `parent_id`, `safe_note`) VALUES
(1, 1, 'fixture-only');

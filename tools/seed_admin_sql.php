<?php
/**
 * seed_admin_sql.php — gera o SQL "wizard-less" (grupo Administrator + usuário)
 * pronto para colar no phpMyAdmin — F6-04 (ADR 0039), passo 4.2.3 do runbook.
 *
 * Uso:  php tools/seed_admin_sql.php <usuario> <senha> <email> [nome] [sobrenome] [prefixo]
 * Ex.:  php tools/seed_admin_sql.php david 'MinhaSenha!Forte2026' d@exemplo.com David Almeida
 *
 * Rodar DEPOIS de importar o schema.sql e ANTES dos upgrade_*.sql.
 * O bcrypt é gerado na hora (password_hash), porque o valor é por-execução.
 */

if ($argc < 4) {
    fwrite(STDERR, "uso: php tools/seed_admin_sql.php <usuario> <senha> <email> [nome] [sobrenome] [prefixo]\n");
    exit(1);
}

[$script, $user, $pass, $email] = $argv;
$firstname = $argv[4] ?? ucfirst($user);
$lastname  = $argv[5] ?? '';
$prefix    = $argv[6] ?? 'rms_';

$hash = password_hash($pass, PASSWORD_BCRYPT);
$esc  = fn(string $v): string => "'" . str_replace("'", "\\'", $v) . "'";

// Mesmas 11 rotas seed de install/model/install/install.php :: createAdministrator()
$seed = ["user/user", "user/user_group", "catalog/project", "catalog/category", "cms/page",
         "marketing/testimonial", "marketing/client_logo", "tool/tool", "sale/order",
         "communication/contact_message", "setting/webhook"];
$perm = json_encode(['access' => $seed, 'modify' => $seed]);

header_comment:
?>
-- ─────────────────────────────────────────────────────────────────────────────
-- Seed "wizard-less" F6-04 (ADR 0039 — runbook §4.2.3)
-- Rodar DEPOIS do schema.sql e ANTES dos upgrade_*.sql (os grants fazem UPDATE
-- WHERE name='Administrator' — sem este grupo os upgrades de permissão caem no vazio).
-- Gerado por tools/seed_admin_sql.php em <?= date('c') . "\n" ?>
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO `<?= $prefix ?>user_group` (`name`, `permission`)
VALUES ('Administrator', '<?= addslashes($perm) ?>');

INSERT INTO `<?= $prefix ?>user` (`user_group_id`, `username`, `password`, `email`,
       `firstname`, `lastname`, `image`, `status`, `date_added`)
VALUES (1, <?= $esc($user) ?>, '<?= $hash ?>', <?= $esc($email) ?>,
       <?= $esc($firstname) ?>, <?= $esc($lastname) ?>, '', 1, NOW());

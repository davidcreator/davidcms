# RELATORIO KZ-36 — Dracaunos-Security no fluxo real de login

**Data:** 2026-11-18 · **Base:** HEAD `1b70811` (CI 7/7 verde) · **Pacote:** `ReamurCMS-Frente-KZ36-Dracaunos-Login.zip`

## O que foi ligado
1. **Verificação de IP** — antes de processar credenciais, `dracaunos_ip_rule` (escopo admin) é consultada; deny bloqueia e registra a tentativa.
2. **Log de logins** — toda tentativa (sucesso ou falha) vai para `dracaunos_login_log` com user_id, username, IP e user-agent.
3. **2FA no login** — se a conta tem TOTP ativo, a senha so autentica com o código de 6 dígitos do app autenticador (campo extra no formulário, só quando exigido).

## Arquivos
- `dev/admin/controller/common/login.php` (novo fluxo POST com as 3 camadas)
- `dev/admin/model/security/dracaunos.php` (+`getUserByUsername()`)
- `dev/admin/view/template/common/login.twig` (campo 2FA condicional)
- idiomas pt-br/en-us/es-eu `common/login.php`
- CHANGELOG + CHECKSUMS recalculado

## Aplicar
```bash
git commit -m "feat(security): Dracaunos no login real — IP deny, log de tentativas e 2FA TOTP (KZ-36)"
```
Só código — sem SQL novo (tabelas já existem desde a KZ-34).

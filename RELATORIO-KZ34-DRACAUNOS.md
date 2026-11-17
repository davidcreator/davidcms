# RELATORIO KZ-34 — Dracaunos-Security (interno) + JOW/Dracaunos fora do catálogo público

**Data:** 2026-11-17 · **Base:** HEAD `453da06` · **Pacote:** `ReamurCMS-Frente-KZ34-Dracaunos-Security.zip`

## Correção de exposição
JOW-payments e Dracaunos-Security são INTERNAS: o SQL remove ambas de `rms_tool`/`rms_tool_description`
(não aparecem mais no catálogo público) e elas permanecem no `internal-tools.json`, que só a página
Ferramentas do **admin** lê.

## Novo módulo admin: `security/dracaunos` (menu Sistema → Segurança)
- **Política de senhas**: tamanho mínimo, maiúscula/número/símbolo obrigatórios, validade (dias)
- **Log de logins**: usuário, IP, user-agent, sucesso/falha (`dracaunos_login_log`)
- **2FA com app de celular**: TOTP RFC 6238 puro (sem lib externa), segredo base32 + código de 6 dígitos, janela ±30s (`dracaunos_2fa`)
- **Rota do admin**: slug configurável (setting `dracaunos_admin_route`, aplicado no próximo deploy)
- **Recuperação/verificação de conta**: fila de solicitações (senha/conta/verificação) com aprovar/negar + IP (`dracaunos_recovery`)
- **Verificação de IP**: regras allow/deny com CIDR, escopo admin/catalog (`dracaunos_ip_rule`)
- **Usuários/perfil**: lista de contas com link direto para o perfil/edição em `user/user`

## Arquivos
- `tools/upgrade_kz34_dracaunos_security.sql` — 4 tabelas novas, settings, permissão do grupo 1, remoção da exposição pública
- `dev/admin/controller/security/dracaunos.php` + `model/security/dracaunos.php` + `view/template/security/dracaunos.twig`
- idiomas pt-br/en-us/es-eu (`security/dracaunos.php`) + chave `text_menu_security` no header ×3 + item de menu
- `internal-tools.json` — Dracaunos-Security 1.1.0 com notas
- CHANGELOG + CHECKSUMS recalculado

## Aplicar
```bash
git commit -m "feat(security): Dracaunos-Security interno (senhas, logins, 2FA, rota admin, IP, recuperação) (KZ-34)"
mysql -u USUARIO -p davidcms < tools/upgrade_kz34_dracaunos_security.sql
```
Depois: Admin → Sistema → Segurança (Dracaunos). O 2FA é por conta: cada admin ativa o próprio TOTP.

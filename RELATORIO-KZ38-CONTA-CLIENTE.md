# RELATORIO KZ-38 — Fase A do Dracaunos-Security: conta do cliente no catálogo

**Data:** 2026-11-18 · **Base:** HEAD `1b70811` · **Pacote:** `ReamurCMS-Frente-KZ38-Conta-Cliente.zip`

## Rotas novas (públicas, em `account/`)
| Rota | Função |
| --- | --- |
| `account/login` / `account/logout` | Login do cliente com IP check (escopo catalog) + log em `dracaunos_login_log` |
| `account/register` | Cadastro (status=0) + token de **verificação de e-mail** |
| `account/verify&token=…` | Ativa a conta (consome o token) |
| `account/forgotten` | **Recuperar senha**: gera token type=password (resposta não revela se o e-mail existe) |
| `account/reset&token=…` | Define a nova senha e consome o token |
| `account/account` | **Perfil** (requer login) |
| `account/edit` / `account/password` | Editar dados e trocar senha (confere a atual) |

## Arquivos
- 8 controllers `dev/catalog/controller/account/*.php` + model `account/dracaunos.php`
- 8 views `dev/catalog/view/template/account/*.twig`
- idiomas pt-br/en-us/es-eu (`account/{login,register,forgotten,account}.php`)
- CHANGELOG + CHECKSUMS recalculado · **sem SQL novo** (usa `rms_customer` + tabelas dracaunos da KZ-34)

## Aplicar
```bash
git commit -m "feat(security): conta do cliente no catalogo — login, cadastro+verificacao, recuperar senha, perfil (KZ-38)"
```
Fluxo de teste: `/account/register` → copie o link de verificação → `/account/verify&token=…` → login → perfil → alterar senha → esqueci a senha → reset.

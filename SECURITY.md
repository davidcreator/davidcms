# Política de Segurança — ReamurCMS

## Reportar vulnerabilidades

**Não abra issue pública.** Envie para **contato@davidalmeida.xyz** com:

- Descrição do problema e impacto potencial;
- Passos para reproduzir (ou prova de conceito);
- Versão/commit afetado.

Você receberá retorno em até **5 dias úteis** e, se confirmado, o crédito na correção (se desejar).

## Escopo

| Em escopo | Fora de escopo |
| --- | --- |
| Código do ReamurCMS (`dev/`) | Vulnerabilidades de pacotes de terceiros sem exploração prática no CMS (reporte via `composer audit`) |
| Falhas de autenticação/autorização (admin, account) | Ataques que exigem acesso físico/root ao servidor |
| XSS/SQLi/CSRF/SSRF (webhooks) | Volumetria/DoS simples |
| Upload inseguro, exposição de storage/config | Problemas em sites de terceiros integrados |

## Boas práticas do projeto

- `error_display = false` em produção; logs em `dev/system/storage/logs/`;
- Credenciais nunca versionadas (`config.php` gerado + `.gitignore`);
- Uploads via `system/library/security/upload_security.php`;
- Webhooks assinados com HMAC por endpoint (ver `docs/Webhooks.md` §5);
- Checklist completo: [`docs/Guia-CMS.md` §8](docs/Guia-CMS.md#8-segurança).

Suportado: branch `main` e tags mais recentes (≥ `v1.0.0`).

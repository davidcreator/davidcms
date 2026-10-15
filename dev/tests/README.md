# dev/tests — Suíte de testes ReamurCMS

> Pasta autorizada pela **ADR 0004** (única exceção aprovada de pasta de primeiro nível em `dev/`). Ativada na **Fase 1**.

## O que vai morar aqui

| Arquivo/pasta | Propósito |
| --- | --- |
| `phpunit.xml.dist` | Config do PHPUnit 10 (bootstrap mínimo do núcleo) |
| `unit/` | Testes unitários de `system/engine` e `system/library` (Registry, Config, Loader, helpers) |
| `integration/` | Testes com banco de testes (Models + Dicionário de Dados) — Fase 4 |

## Regras

- Testes **nunca** rodam contra banco de produção — config separada;
- Toda correção de bug regra-se por "teste que reproduz → correção → teste verde";
- Executar em CI a partir da Fase 1 (job `composer test`, já preparado no workflow comentado).

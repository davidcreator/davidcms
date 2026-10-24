# F9-17 — plano integrado/E2E Blog Blocks

## Estado

Preparação de contrato, fixtures sintéticas e verificador estático. **Nenhum teste E2E foi executado** nesta tarefa. A rota E2E real depende de ambiente de teste isolado, credenciais autorizadas e confirmação explícita da migration F9-12 nesse ambiente.

## Sequência operacional futura

1. Criar uma instância descartável de teste, separada de produção, com snapshot sintético e backups.
2. Integrar os commits F9-12–F9-16 e verificar a migration F9-12 nesse banco de teste pelo procedimento autorizado. Não inferir o estado a partir do código ou deste pacote.
3. Criar um usuário de teste com permissão Blog e outro sem permissão; usar apenas conteúdo sintético.
4. Executar a matriz no contrato `F9-17-Contrato-E2E-Blog-Blocos.json` em navegador suportado.
5. Para cenário de rollback, provocar falhas apenas com mecanismo controlado na instância descartável e confirmar estado antes/depois sem imprimir conteúdo privado em logs.
6. Destruir a instância e revogar credenciais temporárias após aprovação.

## Casos e evidências esperadas

| ID | Jornada | Evidência sem dados sensíveis |
|---|---|---|
| F917-01 | Sem sidecar | Estado do formulário legado; nenhum fallback em erro de consulta |
| F917-02 | Criar documento | POST aceito; contagem de linhas sidecar por idioma e snapshot presente |
| F917-03 | Editar documento | Documento carregado e atualizado somente ao enviar o formulário |
| F917-04 | Converter legado simples | Hash do HTML original preservado; novo conteúdo validado |
| F917-05 | Legado complexo | Conteúdo mantido no modo legado e sem sobrescrita |
| F917-06 | Documento corrompido | Editor bloqueado; valor sidecar intacto após tentativa rejeitada |
| F917-07 | Falha transacional | Nenhuma mudança parcial em post/descrições/SEO/sidecar |
| F917-08 | Biblioteca de mídia | Caminho local inserido no bloco selecionado; capa não muda |
| F917-09 | POST inválido | Resposta de validação sem persistência |
| F917-10 | ACL e idiomas | Escrita negada sem permissão; mensagens e conteúdo coerentes por idioma |

Evidência autorizada deve ser composta por status/códigos e contagens somente. Não capturar títulos, textos, URLs privadas, cookies, tokens, dumps ou linhas de log com dados do usuário.

## Ferramenta incluída

- `tools/e2e/e2e_f917.sh --plan`: imprime este plano; não toca no projeto.
- `tools/e2e/e2e_f917.sh --static <checkout>`: verifica estrutura e marcadores de contratos/fontes; lê arquivos como texto, sem iniciar aplicação, PHP, SQL ou navegador.

O modo `--static` é uma verificação de fonte, não E2E de runtime. Nenhum comando com conexão, browser, servidor ou banco faz parte deste pacote.

# ADR 0071 — F9-15: renderer Blog de blocos allowlisted

**Data:** 2026-10-24 · **Status:** código local preparado; sem runtime test/deploy/DB.  
**Base:** `davidcreator/davidcms` `main` @ `0162822d3bf98ce100780b17cf0c88ff22ee832f`.

## Contexto

F9-14 criou um editor standalone com tipos avançados. O catálogo usa descrição HTML legada com `|raw`; a F9-12 sidecar é opcional até sua migration manual. A saída pública de JSON não pode tratar conteúdo do editor como HTML confiável.

## Decisão

1. Renderizar somente após `BlockDocument::validateDocument` reconhecer a versão e todos os nós/atributos/parents.
2. Converter texto para HTML usando escaping contextual; nenhuma string editorial vira tag/attribute/style. Não há bloco HTML custom.
3. Image paths são locais e restritos. CTA só usa `http(s)` ou path local raiz. URLs de vídeo viram IDs parseados e URLs embed fixas YouTube-nocookie/Vimeo; iframe com sandbox e sem top navigation.
4. Layout é controlado por classes CSS estáticas. Grupos/colunas validados; 2/3 colunas viram uma coluna em viewport mobile.
5. Catalog Model detecta a sidecar antes de SELECT. Sem tabela/registro permanece no reader HTML legado. Com linha válida, só saída do renderer é usada. Com linha inválida/erro, exibe revisão e não volta a `|raw` para a mesma linha.
6. O Controller e Twig passam o HTML do renderer como dado somente no branch de sucesso; esse `|raw` é limitado ao renderer fixo. O branch HTML legado permanece sem alterações de comportamento.
7. Excerpt do catálogo permanece derivado do snapshot HTML `description`; não há conversão, update, migration ou cleanup.

## Alternativas

- Renderizar JSON serializando direto como HTML ou permitir arbitrary HTML: rejeitado por XSS.
- Fallback para HTML legado quando linha sidecar existe mas JSON inválido: rejeitado, pois bypassaria fail-closed e esconderia corrupção/versão incompatível.
- Remover o `|raw` legado nesta subtarefa: rejeitado, pois alteraria trust boundary/lifecycle legado fora do escopo; fica explícito que esse branch não foi sanitizado.
- Atualizar `description` ou converter registros: rejeitado, sem aprovação/migration/backup.
- `iframe src` da URL original ou usar host arbitrário: rejeitado; somente URL reconstruída de ID validado.

## Consequências

- Projeto sem F9-12 aplicado continua publicando o conteúdo antigo.
- Documentos novos ficam indisponíveis até sidecar/schema existente; layout inválido é bloqueado em vez de renderizado parcialmente.
- `information_schema` detection e tabela precisam de validação operacional em MariaDB/MySQL do deploy.
- Fixture runner PHP existe, mas deve ser executado por mantenedor em staging autorizado antes de ativar/publicar feature.

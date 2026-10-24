# F9-16 — Editor administrativo de blocos do Blog

## Escopo

Integra o editor de blocos F9-14 e o renderer allowlist F9-15 ao formulário administrativo de criação/edição de posts. O documento JSON é enviado no POST normal do formulário e salvo no sidecar criado pela tarefa F9-12; não há autosave nem chamadas de persistência em segundo plano.

## Comportamento

- O formulário usa o editor visual apenas quando o usuário tem permissão de alteração e a tabela `blog_post_content` está disponível. Se a migration F9-12 não tiver sido aplicada, o formulário permanece no modo legado; esta entrega não aplica nem presume migration.
- Posts com conteúdo sidecar válido abrem no editor de blocos. Conteúdo legado é convertido apenas quando o conversor o reconhece com segurança; nos demais casos permanece no editor legado para revisão manual.
- Sidecar inválido é tratado como estado bloqueado, sem fallback silencioso para conteúdo vazio ou sobrescrita do documento.
- O POST é validado novamente no servidor; o renderer gera o snapshot HTML e o model persiste post, descrições e sidecar sob transação. A cópia/hash do HTML legado é obtida do banco na primeira conversão.
- A Biblioteca de Mídia pode inserir imagens locais `view/image/blog/...` no bloco selecionado. URLs externas não passam pela allowlist de imagem local do documento.
- Mensagens específicas de F9-16 estão disponíveis em `pt-br`, `en-us` e `es-eu`.

## Arquivos incluídos

Os caminhos sob `dev/` espelham o repositório DavidCMS e são arquivos pontuais para integração, não um clone completo. As bibliotecas `block_document.php` e `block_renderer.php` mantêm a dependência do contrato F9-13/F9-15.

## Segurança e limites

- Não executar migrations, SQL, testes PHP/SQL/browser/app, nem deploy como parte desta entrega.
- A tabela sidecar é verificada somente em runtime do produto. Falhas em detecção/leitura são propagadas como erro; não devem ser interpretadas como tabela ausente.
- E2E está documentado para execução posterior em ambiente de teste com F9-12 aplicada explicitamente. Este pacote não afirma que schema local ou de produção foi atualizado.
- Não inclui dados, dumps ou logs runtime.

## Estado de validação

Revisão estática e inspeção do fluxo realizadas. Nenhum PHP, SQL, navegador ou aplicação foi executado; E2E não foi executado. Antes de integrar, revisar a diferença contra o HEAD indicado no manifesto e realizar a validação operacional em ambiente de teste isolado.

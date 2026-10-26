# Upload de áudio e vídeo na biblioteca Blog

## Escopo

A biblioteca local Blog agora permite upload de áudio e vídeo no editor de blocos de Blog e em CMS > Páginas. Os arquivos são anexados ao bloco selecionado imediatamente, mas o conteúdo só é persistido ao enviar manualmente o formulário. Upload não cria migrations nem altera a estrutura de banco.

| Tipo | Extensões aceitas | MIME detectado no servidor | Máximo |
|---|---|---|---:|
| Áudio | MP3, OGG, WAV | allowlist MIME por extensão via `finfo` | 20 MiB |
| Vídeo local | MP4, WebM, OGV | allowlist MIME por extensão via `finfo` | 100 MiB |

Os nomes enviados pelo navegador não viram nomes de destino: o servidor gera nomes aleatórios. Os diretórios são dedicados: `catalog/view/audio/blog/` e `catalog/view/video/blog/`. Não há exclusão implementada. O bloco existente de vídeo HTTPS continua separado do novo bloco **Vídeo local**.

## Permissões e proteção

- A interface CMS aparece somente para quem tem `modify` em `cms/page` e `modify` em `blog/blog`.
- Rotas novas de upload são POST, exigem sessão/permissão `modify blog/blog` e token CSRF no corpo; listagens são GET e também verificam essa ACL.
- O servidor verifica `is_uploaded_file`, tamanho real, extensão, MIME detectado, destino sob raiz dedicada, symlinks e nome aleatório.
- Não foi adicionado upload executável, HTML, ZIP ou outros formatos. Não há endpoint de exclusão.
- A biblioteca permanece local; os vídeos são renderizados como `<video controls preload="none">` sem autoplay ou JavaScript no conteúdo público.

## Uso

No BDREditor, insira **Áudio** ou **Vídeo local**, abra o Inspetor e use **Selecionar ou enviar**. Também é possível selecionar um item existente ou arrastá-lo da lista ao canvas. A ação genérica **Importar arquivo** continua dedicada a documentos; se receber uma extensão de áudio/vídeo, a mensagem encaminha ao seletor correto.

A tela Blog reutiliza as mesmas rotas/biblioteca e modal. Em CMS > Páginas, o áudio/vídeo selecionado fica no envelope v2 do campo de descrição existente e só é salvo ao enviar o formulário.

## Operação/limites do servidor

O PHP e o servidor web precisam permitir o corpo do upload: `upload_max_filesize` e `post_max_size` devem comportar pelo menos 100 MiB (recomendado `post_max_size` acima disso para multipart overhead), além de timeout compatível. O storage `catalog/view/` precisa ser gravável pelo usuário do PHP. Não alterei configurações do servidor nesta entrega.

## Validação e pendências

Os checkers são estáticos/contratuais; não substituem teste de upload com arquivos reais em um CMS local. Validar MP3, OGG, WAV, MP4, WebM e OGV válidos e inválidos, limites/tipos adulterados, rejeição sem CSRF/ACL, seleção, reabertura do conteúdo e reprodução pública. `php -l` não estava disponível neste ambiente. Não houve escrita GitHub, staging, deploy ou migration.

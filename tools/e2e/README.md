# F9-24 — checker estático e plano futuro

## Checker atual

`node tools/e2e/e2e_f924_static.cjs [F9-24-root]` verifica marcadores textuais em PHP/JS/CSS/controller/traduções e parseia fixtures sintéticas. Não executa nenhum arquivo PHP nem navegador, SQL, CMS, renderer real ou banco. PASS é evidência somente estática.

## E2E funcional futuro (não executado)

1. Validar/serializar/deserializar v1 (incluindo imagem antiga) e v2 com gallery, cover e image metadata.
2. POST e render de captions com HTML adversarial; confirmar escape sem tags/atributos no output.
3. Gallery com 1/20/21 imagens, colunas 2/3/4 e alinhamentos; rejeitar filho não-image, path remoto e campos não allowlisted.
4. Cover com imagem local e filhos permitidos; rejeitar filhos estruturais, URL externa, campo extra e v1.
5. Usar Media Library para capa e cada imagem de galeria; confirmar que fonte inválida/ausente bloqueia save sem apagar legado.
6. Ajustar size/alignment/mobile_size; testar CSS desktop/tablet/mobile, alt/caption e layout de galeria.
7. Admin previews: button sem navegar e embed sem iframe externo; verificar playback sandboxed somente no catálogo sob política aprovada.
8. Inserir cada pattern dentro de root/group/column, verificar IDs únicos, constraints e estado; confirmar cover pattern precisa de imagem antes de salvar.
9. Teclado, leitores de tela, foco, reduced motion, mobile e idiomas PT-BR/EN-US/ES-EU.

Executar apenas após autorização explícita em staging descartável, com F9-12 verificada/aplicada pelo responsável. Nunca usar produção, dump privado ou dados reais. Nenhuma dessas verificações de runtime foi feita nesta tarefa.

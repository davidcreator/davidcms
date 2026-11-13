# ADR 0091 — Segurança: o instalador se fecha pelo estado real da instalação

| | |
| --- | --- |
| **Status** | ✅ aceita (passe de segurança S12, 2026-11-12) |
| **Data** | 2026-11-12 |
| **Contexto** | Achado S12 da análise do repositório ("`/install/` responde 200 em produção"), investigado em `c49f789`. Reproduzido na instalação de teste (instalador web, MariaDB 11.8.6, PHP 8.2.32) antes de qualquer mudança. Complementa as ADRs 0018 (trava `install.lock`), 0039 (build de produção sem `install/`) e 0088 (modo produção). |

## O que foi encontrado

### No site em produção

O `/install/` que responde 200 em `davidalmeida.xyz` **não é o instalador deste repositório**. É o do site antigo ("Instalador | DavidCreator", com `/admin/login` e `config/.env`). Só foram feitas requisições GET. A página diz "Sistema já instalado. Para sua segurança, o instalador foi bloqueado" e mostra um diagnóstico do servidor, incluindo "Permissão de escrita em config/.env: OK". Não há código do site antigo neste repositório, e nenhum formulário foi enviado a ele. A providência é do mantenedor: apagar a pasta `install` do servidor.

### No instalador do CMS

O instalador só se fechava com o arquivo `system/storage/install.lock`, gravado quando a página do passo 4 era aberta. Com o site instalado, a pasta `install/` no servidor e a trava ausente, um visitante de outro endereço:

| Ação do visitante | Resultado antes |
| --- | --- |
| `GET /install/` | 200, assistente disponível |
| Passo 3 com um banco dele e o usuário "invasor" | `catalog/config.php` e `admin/config.php` regravados, apontando para o banco dele |
| Login do dono | recusado |
| Login do "invasor" no admin | aceito |
| "Testar conexão" em portas do próprio servidor | a resposta distingue porta fechada, banco de dados e serviço calado; uma porta que aceita a conexão e não responde prendeu o PHP por 20 s (o limite de leitura do mysqlnd é 86400 s) |

A trava faltava em três situações:

1. **Assistente interrompido.** O passo 3 gravava os `config.php`, importava o banco e criava o administrador. A trava só vinha se a página do passo 4 fosse aberta.
2. **Pasta `storage` sem permissão de escrita.** O passo 4 mostrava "Instalação concluída" e a gravação falhava em silêncio (`@file_put_contents`). Ao recarregar, a sessão já não tinha a marca de conclusão e a trava nunca mais era gravada.
3. **`config.php` criado à mão**, sem passar pelo assistente: nada cria a trava.

**Alcance, medido:** a tomada do site vale para o layout do assistente, em que a pasta do projeto se chama `dev` (`…/dev/install`, `…/dev/catalog`). No layout de produção do roteiro (conteúdo de `prod/` direto na raiz pública, sem pasta `dev`), um `install/` enviado por engano não chega a subir: o `index.php` procura o núcleo em `…/dev/system/` e morre com erro fatal, que mostra o caminho do servidor quando a exibição de erros está ligada.

## Decisão

1. **"Instalado" é o estado real do disco.** `Security\InstallSeal::reason()` responde `lock` (existe `install.lock`), `config` (existe `catalog/config.php` ou `admin/config.php` com conteúdo) ou vazio. Qualquer motivo fecha o instalador com 403. Arquivo `config.php` vazio não conta: `catalog/` e `admin/` também o tratam como instalação pendente.
2. **A decisão vem antes do núcleo.** `install/index.php` carrega a classe com `require` direto e responde o 403 sem subir `startup.php` / `framework.php`. Vale para qualquer rota e método.
3. **O passo 3 trava junto com a instalação.** Ordem: trava → `config.php` → banco → administrador. Se a trava não pode ser gravada, nada foi alterado e o erro aparece no formulário.
4. **Falha no meio é desfeita.** Se um `config.php` não pode ser gravado, ou se a importação do banco falha, os `config.php` e a trava são removidos e o erro aparece no formulário. As duas pastas são conferidas antes de gravar o primeiro arquivo. As tabelas já criadas no banco não são removidas: a mensagem pede para esvaziá-lo.
5. **A página de conclusão sai na resposta do passo 3.** Um redirecionamento para o passo 4 receberia o 403. O passo 4 não grava mais a trava.
6. **"Testar conexão" e conexão do passo 3:** limite de 5 s no teste e 60 s na instalação (`mysqlnd.net_read_timeout`, `default_socket_timeout` e `PDO::ATTR_TIMEOUT`, com os valores do `ini` devolvidos depois). Servidor do banco aceita só letras, números e `. _ - : / [ ]`; nome do banco recusa `;` e bytes de controle. No PDO os dois entram no DSN, e `x;unix_socket=…` trocava o destino.
7. **Aviso no painel.** Enquanto a pasta `install/` existir ao lado do admin, o painel inicial mostra um aviso pedindo para apagá-la (pt-br, en-us, es-eu).
8. **`tools/check_producao.sh`, check 14:** o instalador travado responde 403 e passava como "pasta ausente". O check agora emite um aviso quando encontra a página de recusa. O script continua com 16 checks.

## Efeito em instalações existentes

- Instalação com `install.lock`: nada muda.
- Instalação com `config.php` e sem a trava: o instalador passa a responder 403.
- Reinstalar continua exigindo apagar `install.lock` e os dois `config.php`. Apagar só a trava deixa de bastar.
- Quem instala vê a página de conclusão como antes. Recarregá-la mostra "Installation locked".
- Sem tabela, coluna, migration, rota, configuração ou dependência nova.

## Verificação

- **Reprodução, antes e depois** (`repro.sh`, visitante em outro endereço): antes, o visitante regravou os `config.php` e entrou no admin; depois, todas as requisições ao instalador respondem 403, o `config.php` não muda e o login do dono continua valendo.
- **Assistente de ponta a ponta, depois** (`instalacao.sh`):

| Caso | Resultado |
| --- | --- |
| Site não instalado | assistente abre (200) |
| "Testar conexão" em porta que aceita e cala por 20 s | resposta em 5 s |
| "Testar conexão" com servidor `x;unix_socket=/tmp/s` | recusado sem conectar |
| `storage` sem permissão de escrita | erro no formulário; sem trava, sem `config.php`, banco vazio |
| `admin/` sem permissão de escrita | erro no formulário; nenhum `config.php` fica pela metade |
| Banco que não aceita criar tabelas | erro no formulário; instalação desfeita; assistente disponível de novo |
| Instalação normal | página de conclusão na mesma resposta; trava e `config.php` gravados; 44 tabelas |
| Depois de instalado | `GET /install/`, passo 3 e passo 4: 403; site e login do admin funcionam |
| Trava apagada, `config.php` presente | 403 |
| Painel do admin | mostra o aviso com a pasta `install/` presente; nenhum aviso sem ela |

- `tests/Unit/Install/InstallSealTest.php`: 52 testes. Sete deles executam o `install/index.php` real em uma árvore de teste, em subprocesso.
- Mutação: 24 alterações que desfazem cada parte da correção, todas reprovadas pela suíte.
- PHPUnit em PHP 8.2 e 8.3: 566 testes. PHPStan sem erros.

As duas baterias (`repro.sh`, `instalacao.sh`) usam o servidor embutido do PHP e um MariaDB local. Elas não rodam no CI; o que roda no CI são os testes unitários.

## Limites

- **Site antigo em produção:** fora do alcance deste repositório. Não foi verificado se o bloqueio do instalador antigo vale para envio de formulário.
- **Não exercitado:** Apache, nginx + PHP-FPM e LiteSpeed; o driver `mysqli` (o PHP de teste só tem `pdo_mysql`), inclusive o limite de tempo nesse driver; Windows / XAMPP.
- **Antes da instalação** o assistente continua aberto a qualquer visitante, por definição: quem chegar primeiro instala. "Testar conexão" continua respondendo se uma porta do servidor está aberta, agora com limite de tempo. A janela entre enviar os arquivos e instalar deve ser curta.
- **Arquivos estáticos:** `install/sql/*.sql` e `install/config.php.tpl` continuam baixáveis enquanto a pasta existir. São públicos no GitHub e não contêm segredos.
- **A ordem das etapas do passo 3 e o desfazer** são cobertos por teste de texto do código-fonte e pela bateria local, não por teste unitário de comportamento: o controlador grava em `DIR_STORAGE` e nas pastas reais do projeto.
- **Layout de produção sem `config.php`:** um `install/` enviado ali continua sem subir (erro fatal ao procurar o núcleo em `dev/`, com o caminho do servidor na tela se a exibição de erros estiver ligada). Com `config.php` presente, responde 403 (conferido).
- A página de recusa é fixa, em inglês.
- Importação que falha depois de criar parte das tabelas deixa essas tabelas no banco.

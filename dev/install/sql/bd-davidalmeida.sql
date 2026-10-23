-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 20/10/2026 às 14:03
-- Versão do servidor: 11.8.9-MariaDB-log
-- Versão do PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `u163008030_almeida`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `admin_audit_logs`
--

CREATE TABLE `admin_audit_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `action_key` varchar(120) NOT NULL DEFAULT '',
  `entity` varchar(120) NOT NULL DEFAULT '',
  `summary` varchar(500) NOT NULL DEFAULT '',
  `details_json` longtext NOT NULL,
  `actor_username` varchar(190) NOT NULL DEFAULT '',
  `ip_address` varchar(64) NOT NULL DEFAULT '',
  `created_at` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `admin_audit_logs`
--

INSERT INTO `admin_audit_logs` (`id`, `action_key`, `entity`, `summary`, `details_json`, `actor_username`, `ip_address`, `created_at`) VALUES
(1, 'security.clear_cache', 'runtime_cache', 'Limpeza manual de cache e sessoes', '{\"removed_files\":37,\"freed_bytes\":2301,\"errors\":0,\"opcache_reset\":true}', 'davidsefhro', '187.45.49.23', '2026-08-31 00:44:14');

-- --------------------------------------------------------

--
-- Estrutura para tabela `class_attendance`
--

CREATE TABLE `class_attendance` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `module_id` int(10) UNSIGNED NOT NULL,
  `was_present` tinyint(1) NOT NULL DEFAULT 0,
  `attendance_date` varchar(25) NOT NULL DEFAULT '',
  `notes` text NOT NULL,
  `marked_by` varchar(120) NOT NULL DEFAULT '',
  `created_at` varchar(25) NOT NULL,
  `updated_at` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `class_students`
--

CREATE TABLE `class_students` (
  `id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `enrollment_status` varchar(30) NOT NULL DEFAULT 'active',
  `enrolled_at` varchar(25) NOT NULL DEFAULT '',
  `notes` text NOT NULL,
  `created_at` varchar(25) NOT NULL,
  `updated_at` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(160) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(60) NOT NULL DEFAULT '',
  `subject` varchar(255) NOT NULL DEFAULT '',
  `message` text NOT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'new',
  `priority` varchar(40) NOT NULL DEFAULT 'normal',
  `source` varchar(40) NOT NULL DEFAULT 'site',
  `ip_address` varchar(64) NOT NULL DEFAULT '',
  `assigned_to` varchar(120) NOT NULL DEFAULT '',
  `internal_notes` text NOT NULL,
  `replied_at` varchar(25) NOT NULL DEFAULT '',
  `updated_by` varchar(120) NOT NULL DEFAULT '',
  `created_at` varchar(25) NOT NULL,
  `updated_at` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `phone`, `subject`, `message`, `status`, `priority`, `source`, `ip_address`, `assigned_to`, `internal_notes`, `replied_at`, `updated_by`, `created_at`, `updated_at`) VALUES
(33, 'Davidskigo', 'no.reply.OlofRouxson@gmail.com', '87375172189', 'A lawful way to send commercial messages', 'Good morning! davidalmeida.xyz, \r\nWhile exploring websites I noticed davidalmeida.xyz . \r\nWe provide a platform for contacting website owners. \r\nMany companies use web platforms to connect with website operators. \r\nOur plans are flexible and easy to start with. \r\nYou can try the platform with a free demo. \r\nYou are welcome to get in touch if this might interest you. \r\n \r\nWishing you a great day. \r\nContact us. \r\nTelegram - https://t.me/FeedbackFormEU \r\nWhatsApp - +375259112693 \r\nWhatsApp  https://wa.me/+375259112693 \r\nWe only use chat for communication.', 'new', 'normal', 'site', '158.173.156.220', '', '', '', '', '2026-09-25 04:09:57', '2026-09-25 04:09:57'),
(34, 'hxPVQJOhCVDvmjUeYKpQDLtD', 'tp.s.y.ci.h.clgn.s9.2.8@gmail.com', '9475152128', 'https://hdoymuodw.com', 'qnbEkQWbfmvAisugYpTzTel', 'new', 'normal', 'site', '104.167.242.117', '', '', '', '', '2026-09-27 04:13:59', '2026-09-27 04:13:59'),
(35, 'hxPVQJOhCVDvmjUeYKpQDLtD', 'tp.s.y.ci.h.clgn.s9.2.8@gmail.com', '9475152128', 'https://hdoymuodw.com', 'qnbEkQWbfmvAisugYpTzTel', 'new', 'normal', 'site', '104.167.242.117', '', '', '', '', '2026-09-27 04:14:04', '2026-09-27 04:14:04'),
(36, 'hxPVQJOhCVDvmjUeYKpQDLtD', 'tp.s.y.ci.h.clgn.s9.2.8@gmail.com', '9475152128', 'https://hdoymuodw.com', 'qnbEkQWbfmvAisugYpTzTel', 'new', 'normal', 'site', '104.167.242.117', '', '', '', '', '2026-09-27 04:14:09', '2026-09-27 04:14:09'),
(37, 'NAERTERHTE476259NEHTYHYHTR', 'baal36@notlettersmail.com', '86518876255', 'TOTYJTRT476259TIRTYTH', 'MEYJYTJY476259MARTHHDF', 'new', 'normal', 'site', '86.54.28.75', '', '', '', '', '2026-09-29 19:56:57', '2026-09-29 19:56:57'),
(38, 'jafeTifs', 'grjb88ok@yahoo.com', '87729563229', 'I promised.', 'Hi, this is Anna. I am sending you my intimate photos as I promised. https://tinyurl.com/36ta4ujy', 'new', 'normal', 'site', '108.181.58.239', '', '', '', '', '2026-10-01 23:13:51', '2026-10-01 23:13:51'),
(39, 'NAERTREGE359962NEYHRTGE', 'jorre_4319@notboxletters.com', '83369261165', 'TORTYT359962TIGHTRTG', 'MEYJYTJY359962MAVNGHJTH', 'new', 'normal', 'site', '217.217.254.126', '', '', '', '', '2026-10-10 12:36:25', '2026-10-10 12:36:25'),
(40, 'Rachelle Deberry', 'parkerharrison31023@gmail.com', '675055745', '', 'Hi,I took a quick look at davidalmeida.xyz and noticed a few issues that may be costing you potential visitors, customers, and sales.\r\n\r\nI put together a free report showing the issues I found.\r\n\r\nInterested in seeing it? Just reply YES and I\'ll send it over.\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\nTo unsubscribe, please reply with subject:  Unsubscribe !davidalmeida.xyz', 'new', 'normal', 'site', '46.4.188.179', '', '', '', '', '2026-10-11 20:30:43', '2026-10-11 20:30:43'),
(41, 'CharlesGot', 'beezowilliams7@gmail.com', '83771564333', 'READ THE NEWS AND LATENT VARIABLE MODELING FOR $50,000,000 PER MONTH', 'Why you should read the news and earn $50,000,000 per month https://telegra.ph/Read-the-latest-news-and-earn-50000000-in-a-month-Message-ID-110198-10-05', 'new', 'normal', 'site', '196.196.53.101', '', '', '', '', '2026-10-12 05:19:19', '2026-10-12 05:19:19');

-- --------------------------------------------------------

--
-- Estrutura para tabela `contact_message_events`
--

CREATE TABLE `contact_message_events` (
  `id` int(10) UNSIGNED NOT NULL,
  `message_id` int(10) UNSIGNED NOT NULL,
  `event_type` varchar(40) NOT NULL DEFAULT 'system',
  `event_body` text NOT NULL,
  `event_meta` text NOT NULL,
  `created_by` varchar(120) NOT NULL DEFAULT '',
  `created_at` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `contact_reply_templates`
--

CREATE TABLE `contact_reply_templates` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(190) NOT NULL,
  `content` text NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` varchar(25) NOT NULL,
  `updated_at` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `contact_reply_templates`
--

INSERT INTO `contact_reply_templates` (`id`, `title`, `content`, `is_default`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Primeiro retorno', 'Olá! Recebemos sua mensagem e já estamos analisando.\nRetornaremos com os próximos passos em breve.', 1, 1, '2026-05-20 19:51:36', '2026-08-12 14:56:41'),
(4, 'Follow-up', 'Passando para acompanhar sua solicitação.\nSe ainda fizer sentido, sigo disponível para te ajudar.', 0, 4, '2026-05-20 19:51:36', '2026-08-12 14:58:29'),
(29703, 'OrÃ§amento enviado', 'Perfeito! Seu orÃ§amento foi preparado e enviado.\nSe quiser, marcamos um horÃ¡rio para revisar os itens juntos.', 0, 3, '2026-08-12 14:58:03', '2026-08-12 14:58:03'),
(29726, 'Pedido de informaÃ§Ãµes', 'Obrigado pelo contato.\nPara seguir com o atendimento, pode enviar mais detalhes sobre objetivo, prazo e referÃªncias?', 0, 2, '2026-08-12 14:58:56', '2026-08-12 14:58:56');

-- --------------------------------------------------------

--
-- Estrutura para tabela `course_classes`
--

CREATE TABLE `course_classes` (
  `id` int(10) UNSIGNED NOT NULL,
  `track_id` int(10) UNSIGNED NOT NULL,
  `class_name` varchar(190) NOT NULL DEFAULT '',
  `weekday` varchar(30) NOT NULL DEFAULT '',
  `class_date` varchar(25) NOT NULL DEFAULT '',
  `start_time` varchar(10) NOT NULL DEFAULT '',
  `end_time` varchar(10) NOT NULL DEFAULT '',
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `notes` text NOT NULL,
  `created_at` varchar(25) NOT NULL,
  `updated_at` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `course_classes`
--

INSERT INTO `course_classes` (`id`, `track_id`, `class_name`, `weekday`, `class_date`, `start_time`, `end_time`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'Demonstração', 'sexta', '', '19:00', '21:00', 'active', 'Esta é uma conta demonstrativa não tem valor histórico para nenhum aluno', '2026-08-10 17:06:44', '2026-08-10 17:06:44'),
(2, 2, 'Demonstração', 'sexta', '', '21:00', '23:00', 'active', '', '2026-08-10 19:59:14', '2026-08-10 19:59:14');

-- --------------------------------------------------------

--
-- Estrutura para tabela `course_contents`
--

CREATE TABLE `course_contents` (
  `id` int(10) UNSIGNED NOT NULL,
  `module_id` int(10) UNSIGNED NOT NULL,
  `content_title` varchar(190) NOT NULL DEFAULT '',
  `content_description` text NOT NULL,
  `resource_url` varchar(600) NOT NULL DEFAULT '',
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` varchar(25) NOT NULL,
  `updated_at` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `course_contents`
--

INSERT INTO `course_contents` (`id`, `module_id`, `content_title`, `content_description`, `resource_url`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 1, 'Conhecendo o sistema operacional', 'Conhecendo o equipamento - Hardware e Periféricos\r\nO que é um sistema operacional?\r\nConhecendo ambiente Windows\r\nTrabalhando com Mouse\r\nTrabalhando com Teclado\r\nMenu iniciar\r\nNavegando em Janelas e Programas', '', 0, '2026-08-06 13:28:38', '2026-08-06 13:28:38'),
(2, 1, 'Explorador de Arquivos', 'Mover janelas\r\nCriar, Editar, Atualizar e Deletar pastas\r\nLixeira\r\nCalculadora, Bloco de Notas e Paint\r\nSalvando arquivos', '', 0, '2026-08-06 13:35:13', '2026-08-06 13:35:13'),
(3, 1, 'Configurações Básicas de Sistema', 'Data Hora\r\nPlano de Fundo\r\nInternet\r\nIdiomas\r\nAtualizações', '', 0, '2026-08-06 13:35:54', '2026-08-06 13:35:54'),
(4, 1, 'Boas Práticas', 'Antivirus\r\nNavegação de internet e downloads\r\nAtualização de sistema e programas\r\nFinalização correta do sistema', '', 0, '2026-08-06 13:36:13', '2026-08-06 13:37:13'),
(5, 2, 'Primeiros Passos no Word', 'O que é Microsoft Word?\r\nPor que aprender a usar um editor de texto\r\nO que posso criar em um editor de texto\r\nOnde usar editores de texto\r\nConehcendo tela inicial - Boas Vindas\r\nConehcendo área de trabalho - Documento\r\nCriando seu primeiro documento\r\nSalvando primeiro documento', '', 0, '2026-08-06 13:48:17', '2026-08-06 22:12:01'),
(6, 2, 'Primeiro documento', 'Criando documento\r\nGrupo Fonte\r\nGrupo Paragrafo\r\nGrupo Área de Transferencia\r\nAprendendo a Selecionar com Mouse e Teclado\r\nComandos Copiar, Colar e Recortar, Colar.\r\nManuseando conteúdo \"arrasto\"\r\nO que é formatação de Texto\r\nFonte, Tamanho, Cor da letra, Cor de Fundo, Borda do Texto\r\nParagrafia, esquerda, centralizada, direita e justificada\r\nCaixa de Texto\r\nCaptular', '', 0, '2026-08-06 13:54:24', '2026-08-10 13:24:43'),
(7, 2, 'Trabalhando com Marcadore, Numeradores e Listas', 'Aplicando marcador\r\nAplicando numerador\r\nAplicando simbolos\r\nAlterando níveis de hierarquia, avanço e recuo\r\nBordas e sombreamento de páginas e conteúdo', '', 0, '2026-08-06 18:02:49', '2026-08-10 13:28:19'),
(8, 2, 'Guia Inserir', 'O que são elementos visuais?\r\nO que é uma tabela\r\nInserindo tabela, adicionando linha, coluna, removendo linha, conula e tabela\r\nMesclando conteúdo\r\nModelando tabela\r\nInserindo imagem\r\nGuia Forma de Imagem\r\nInserindo Formas\r\nGuia forma de formatos', '', 0, '2026-08-06 18:04:08', '2026-08-10 13:33:45'),
(9, 2, 'Criando Curriculo', 'Criação de curriculo\r\nFormatação\r\nSalvando curriculo', '', 0, '2026-08-06 18:05:15', '2026-08-10 13:34:17'),
(10, 2, 'Aula final de módulo Word', 'Revisão de conteúdo e ferramentas', '', 0, '2026-08-06 21:44:38', '2026-08-10 13:34:49'),
(11, 3, 'Primeiros passos no Excel', 'O que é Microsoft Excel?\r\nPor que aprender a usar tabelas e planilhas\r\nO que posso criar em um editor de planilhas\r\nOnde usar planilhas\r\nConehcendo tela inicial - Boas Vindas\r\nConehcendo área de trabalho - Pasta de trabalho\r\nCriando sua primeira planilha\r\nSalvando primeira planilha', '', 0, '2026-08-06 22:17:22', '2026-08-06 22:17:22'),
(12, 3, 'Criando projeto controle financeiro', 'Projeto de controle financeiro\r\nTitulo, Cabeçalho, Conteúdo\r\nFormatar como tabela\r\nAuto Completar\r\nCampos de fórmulas básicas\r\nSalvando projeto', '', 0, '2026-08-06 22:18:18', '2026-08-10 13:40:43'),
(13, 3, 'Formatação de Células', 'Preenchimento de célula\r\nTipos de Dados\r\nFormatação de texto\r\nGrupo Fonte\r\nGrupo Alinhamento\r\nSalvando projeto', '', 0, '2026-08-10 12:35:06', '2026-08-10 13:40:53'),
(14, 3, 'Design de Planilhas', 'Largura de Coluna\r\nAltura de Linha\r\nSeleção por intervalos\r\nSeleção complementar\r\nMesclagem básica\r\nFiltro de cabeçalho\r\nCores e bordas', '', 0, '2026-08-10 12:36:52', '2026-08-10 13:36:35'),
(15, 3, 'Fórmulas e funções', 'Utilizando fórmulas básicas, soma, subtração, multiplicação, divisão, porcentagem.\r\nAplicando fórmulas ao projeto controle financeiro\r\nAprendendo funções básicas. Soma, Média, Máximo, Minímo\r\nAplicando funções ao projeto controle financeiro', '', 0, '2026-08-10 13:16:46', '2026-08-10 13:37:08'),
(16, 3, 'Inserindo Imagens e Gráfico', 'Inserindo formas, imagems no projeto controle financeiro\r\nInserindo gráfico\r\nEditando gráfico, eixos, estilos e cores', '', 0, '2026-08-10 13:20:34', '2026-08-10 13:37:37'),
(17, 3, 'Fim de módulo Excel', 'Revisão de ferramentas, tabelas e planilhas', '', 0, '2026-08-10 13:22:21', '2026-08-10 13:40:31'),
(18, 4, 'Primeiros passos no PowerPoint', 'O que é Microsoft PowerPoint?\r\nO que são Slides e metologia de apresentação?\r\nQuais tipos de obras e peças posso criar?\r\nTipos de programas para criação de slides e conteúdo publicitário\r\nConehcendo tela inicial - Boas Vindas\r\nConehcendo área de trabalho - Apresentação\r\nCriando seu primeiro slide\r\nSalvando primeira Apresentação', '', 0, '2026-08-10 13:45:37', '2026-08-10 13:45:37'),
(19, 4, 'Criando primeira apresentação', 'Escolhendo Tema\r\nConfigurando Slides\r\nTrabalhando com caixas de textos\r\nInserindo formas\r\nInserindo imagens\r\nOrganizando objetos\r\nSalvando apresentação', '', 0, '2026-08-10 13:49:59', '2026-08-10 13:50:17'),
(20, 4, 'Aplicando Transições e Animações', 'Aplicando transições de slides\r\nAplicando animações de objetos. Entrada, enfase e saída\r\nAplicando efeitos sonoros\r\nAplicando automatização entre slides e animações\r\nSalvando apresentação', '', 0, '2026-08-10 13:51:29', '2026-08-10 13:51:29'),
(21, 4, 'Finalizando arquivos de PowerPoint', 'Salvando como apresentação executável\r\nExportando como vídeo\r\nExportando como imagem\r\nExportando para impressão PDF', '', 0, '2026-08-10 13:52:46', '2026-08-10 13:52:46'),
(22, 5, 'CONHECENDO A ÁREA DE TRABALHO', 'Detalhe completo da área de trabalho do pacote Office', '', 0, '2026-08-10 18:41:50', '2026-08-10 18:41:50'),
(23, 5, 'GERENCIANDO PASTAS DE TRABALHO', 'Aprendendo a gerenciar e trabalhar com pastas de trabalho no Excel\r\nSalvando a Pasta de Trabalho pela primeira vez', '', 0, '2026-08-10 18:42:18', '2026-08-10 18:42:55'),
(24, 5, 'INSERINDO DADOS', 'Inserindo dados e tipos de dados.\r\nAdicionando\r\nAlterando\r\nApagando\r\nApagando com Backspace\r\nPagando com Delete', '', 0, '2026-08-10 18:45:05', '2026-08-10 18:45:05'),
(25, 5, 'USANDO MODELOS PRONTOS PARA CRIAR PLANILHAS', 'Modelos disponíveis Microsoft Online\r\nModelos disponíveis em sites de terceiros\r\nModelos do Google planilhas', '', 0, '2026-08-10 18:46:13', '2026-08-10 18:46:13'),
(26, 5, 'AUTORECUPERAÇÃO', 'Autorecuperação de pasta de trabalho\r\nConfigurando tempo de autorecuperação\r\nDefinindo local de salvamento autorecuperação', '', 0, '2026-08-10 18:47:10', '2026-08-10 18:47:10'),
(27, 6, 'ADICIONANDO UMA NOVA PLANILHA', 'Trabalhando com novas abas de planilhas\r\nRemovendo\r\nEditando\r\nCopiando\r\nDuplicando', '', 0, '2026-08-10 18:58:45', '2026-08-10 18:58:45'),
(28, 6, 'SELECIONANDO PLANILHAS', 'Selecionando planilhas\r\nSeleção multipla\r\nSeleção seletiva\r\nSeleção de pastas de trabalho', '', 0, '2026-08-10 18:59:43', '2026-08-10 18:59:43'),
(29, 6, 'SELECIONANDO TODAS AS PLANILHAS', 'Seleção massiva', '', 0, '2026-08-10 19:00:12', '2026-08-10 19:00:12'),
(30, 6, 'ALTERANDO O NOME DAS PLANILHAS', 'Alterando nomes de planilhas\r\nBoas práticas', '', 0, '2026-08-10 19:00:42', '2026-08-10 19:00:42'),
(31, 6, 'NAVEGANDO PELA PLANILHA ATIVA', 'Navegando pelas planilhas\r\nNavegação com mouse\r\nNavegação com teclado\r\nNavegação com teclas de atalho', '', 0, '2026-08-10 19:02:12', '2026-08-10 19:02:12'),
(32, 6, 'SUBSTITUINDO DADOS', 'Editando dados nas células\r\nTecla de atalho', '', 0, '2026-08-10 19:02:49', '2026-08-10 19:02:49'),
(33, 6, 'ALTERANDO A LARGURA DAS COLUNAS', 'Alterando largura das colunas\r\nAlterando largura com seleção de colunas\r\nAlterando através da edição numérica', '', 0, '2026-08-10 19:03:39', '2026-08-10 19:03:39'),
(34, 6, 'USANDO O COMANDO FORMATAR', 'Formatando células\r\nGrupo Fonte\r\nGrupo Alinhamento', '', 0, '2026-08-10 19:04:12', '2026-08-10 19:04:12'),
(35, 6, 'INSERINDO CÉLULAS', 'Criando novas células através das colunas e linhas', '', 0, '2026-08-10 19:04:44', '2026-08-10 19:04:44'),
(36, 6, 'INSERINDO COLUNAS', 'Trabalhando com colunas inserindo, editando', '', 0, '2026-08-10 19:07:59', '2026-08-10 19:07:59'),
(37, 6, 'INSERINDO LINHAS', 'Trabalhando com linhas, inserindo e editando', '', 0, '2026-08-10 19:08:30', '2026-08-10 19:08:30'),
(38, 6, 'EXCLUINDO CÉLULA', 'Trabalhando com exclusão de células', '', 0, '2026-08-10 19:09:00', '2026-08-10 19:09:00'),
(39, 6, 'EXCLUINDO COLUNA E LINHA', 'Trabalhando com exclusões de linhas e colunas', '', 0, '2026-08-10 19:09:31', '2026-08-10 19:09:31'),
(40, 6, 'EXCLUINDO PLANILHA', 'Excluindo aba de planilha', '', 0, '2026-08-10 19:09:48', '2026-08-10 19:09:48'),
(41, 8, 'O QUE SÃO FÓRMULAS?', 'O que são fórmulas conceito básico', '', 0, '2026-08-10 19:44:39', '2026-08-11 20:13:00'),
(42, 8, 'OPERADORES MATEMÁTICOS', 'Soma, subtração, multiplicação, divisão, exponenciação, porcentagem', '', 0, '2026-08-10 19:44:49', '2026-08-11 20:13:26'),
(43, 8, 'OPERADORES DE COMPARAÇÃO', 'Operadores lógicos de comparaçã maior, menor, igual, diferente', '', 0, '2026-08-10 19:44:58', '2026-08-11 20:14:23'),
(44, 8, 'OPERADORES CONCATENAÇÃO', 'Concatenar células e congelar intervalos', '', 0, '2026-08-10 19:45:06', '2026-08-11 20:14:48'),
(45, 8, 'UTILIZANDO FÓRMULAS', 'Aplicando fórmulas básicas', '', 0, '2026-08-10 19:45:17', '2026-08-11 20:15:24'),
(46, 8, 'UTILIZANDO FUNÇÕES', 'Conceitos básicos de funções', '', 0, '2026-08-10 19:45:27', '2026-08-11 20:15:53'),
(47, 8, 'FUNÇÕES ESTATÍSTICAS', 'Média', '', 0, '2026-08-10 19:45:37', '2026-08-11 20:17:32'),
(48, 8, 'MÍNIMO E MÁXIMO', 'Encontrar o menor valor e o maior valor', '', 0, '2026-08-10 19:45:46', '2026-08-11 20:17:53'),
(49, 8, 'CLASSIFICANDO E FILTRANDO DADOS', 'Ajustes de filtro', '', 0, '2026-08-10 19:45:58', '2026-08-11 20:18:09'),
(50, 8, 'CLASSIFICANDO DADOS', 'Ajustando dados a serem exebidos', '', 0, '2026-08-10 19:46:10', '2026-08-11 20:18:38'),
(51, 8, 'DEFININDO NÍVEIS DE CLASSIFICAÇÃO', 'Hierarquia de dados', '', 0, '2026-08-10 19:46:18', '2026-08-11 20:18:53'),
(52, 8, 'FILTRANDO DADOS', 'Ajuste fino na exposição de dadoos', '', 0, '2026-08-10 19:46:40', '2026-08-11 20:19:08'),
(53, 8, 'LIMPANDO O RESULTADO DO FILTRO', 'Limpando filtro para exibição padrão de dados', '', 0, '2026-08-10 19:46:49', '2026-08-11 20:19:24'),
(54, 8, 'ALTERANDO O FILTRO', 'Alterando buscas do filtro', '', 0, '2026-08-10 19:47:00', '2026-08-11 20:19:42'),
(55, 9, 'GRÁFICOS', 'O que são gráficos e conceitos de elementos visuais', '', 0, '2026-08-10 19:47:40', '2026-08-11 20:23:32'),
(56, 9, 'GRÁFICOS RECOMENDADOS', 'Trabalhando com ferramenta gráficos recomendados', '', 0, '2026-08-10 19:47:48', '2026-08-11 20:23:52'),
(57, 9, 'CRIANDO UM GRÁFICO', 'Criando gráfico a partir da base de modelos', '', 0, '2026-08-10 19:47:57', '2026-08-11 20:24:10'),
(58, 9, 'EDITANDO UM GRÁFICO', 'Editando propriedades de um gráfico', '', 0, '2026-08-10 19:48:12', '2026-08-11 20:24:29'),
(59, 9, 'FILTRANDO DADOS DE UM GRÁFICO', 'Ferramenta filtrar em gráficos', '', 0, '2026-08-10 19:48:22', '2026-08-11 20:24:43'),
(60, 9, 'ALTERANDO O ESTILO E AS CORES DO GRÁFICO', 'Ferramenta Design', '', 0, '2026-08-10 19:48:34', '2026-08-11 20:24:55'),
(61, 9, 'LAYOUT RÁPIDO', 'Ferramenta layout', '', 0, '2026-08-10 19:48:43', '2026-08-11 20:25:09'),
(62, 9, 'TROCANDO OS DADOS DOS EIXOS', 'Alterando dados de eixos dos gráficos', '', 0, '2026-08-10 19:48:55', '2026-08-11 20:25:25'),
(63, 9, 'TÍTULOS DOS EIXOS', 'Trabalhando com edição de titulos nos eixos', '', 0, '2026-08-10 19:49:07', '2026-08-11 20:25:44'),
(64, 9, 'LINHAS DE GRADE', 'Alterando configurações das linhas de grade', '', 0, '2026-08-10 19:49:27', '2026-08-11 20:26:25'),
(65, 9, 'MOVENDO GRÁFICO', 'Conceitos básicos de mover', '', 0, '2026-08-10 19:50:12', '2026-08-11 20:26:55'),
(66, 9, 'ALTERAR TIPO DE GRÁFICO', 'Alterando modelos de gráficos', '', 0, '2026-08-10 19:50:20', '2026-08-11 20:27:11'),
(67, 9, 'TIPOS DE GRÁFICO', 'Tipos e modelos de gráficos estudo amplo sobre dados por meio de imagens', '', 0, '2026-08-10 19:50:29', '2026-08-11 20:27:44'),
(68, 10, 'IMPRIMINDO PLANILHAS', 'Preparando planilhas para impressão', '', 0, '2026-08-10 19:52:56', '2026-08-11 20:38:13'),
(69, 10, 'VERIFICANDO ORTOGRAFIA E GRAMÁTICA', 'Correção de textos e gramática', '', 0, '2026-08-10 19:57:36', '2026-08-11 20:38:29'),
(70, 10, 'CORRIGINDO A ORTOGRAFIA', 'Ferramentas correção de ortografia', '', 0, '2026-08-10 19:57:46', '2026-08-11 20:39:02'),
(71, 10, 'UTILIZANDO O DICIONÁRIO DE SINÔNIMOS', 'Conhecendo ferramenta dicionário', '', 0, '2026-08-10 19:57:56', '2026-08-11 20:39:13'),
(72, 7, 'FORMATA TABELA (FERRAMENTA DE TABELA)', 'Formatando tabela usando ferramentas de formatação', '', 0, '2026-08-11 19:42:14', '2026-08-11 19:42:14'),
(73, 7, 'DEFININDO UM INTERVALO COM FORMATO TABELA', 'Limitação da formatação como tabela definindo intervalo', '', 0, '2026-08-11 19:42:40', '2026-08-11 19:42:40'),
(74, 7, 'GUIA CONTEXTUAL (FERRAMENTAS DE TABELA)', 'Aplicando guia contextual com as ferramentas de formatação como tabela', '', 0, '2026-08-11 19:43:09', '2026-08-11 19:43:09'),
(75, 7, 'ALTERANDO O NOME DE UMA TABELA', 'Alterando nome de tabela e intervalo de tabela', '', 0, '2026-08-11 19:48:29', '2026-08-11 19:48:29'),
(76, 7, 'EXPANDINDO A ÁREA DE UMA TABELA', 'Expanção em matrizes', '', 0, '2026-08-11 20:03:29', '2026-08-11 20:03:29'),
(77, 7, 'EXPANDINDO A ÁREA DA TABELA PARA LINHAS', 'Expansão simples e multipla', '', 0, '2026-08-11 20:03:45', '2026-08-11 20:03:45'),
(78, 7, 'EXPANDINDO A ÁREA DA TABELA PARA COLUNAS', 'Expansã simples e multipla', '', 0, '2026-08-11 20:04:05', '2026-08-11 20:04:05'),
(79, 7, 'FILTRANDO DADOS DA TABELA', 'Filtros de dados intermediário', '', 0, '2026-08-11 20:04:29', '2026-08-11 20:04:29'),
(80, 7, 'LIMPANDO O RESULTADO DO FILTRO', 'Ajustes finos em filtragem de dados', '', 0, '2026-08-11 20:04:50', '2026-08-11 20:04:50'),
(81, 7, 'REMOVENDO DADOS REPETIDOS EM UMA TABELA', 'Removend duplicatas', '', 0, '2026-08-11 20:05:12', '2026-08-11 20:05:12'),
(82, 7, 'FORMATANDO CÉLULAS', 'formatações básicas de células', '', 0, '2026-08-11 20:05:27', '2026-08-11 20:05:27'),
(83, 7, 'ALTERANDO FONTE, COR E TAMANHO DO CARACTERE', 'Trabalhando grupoo fonte', '', 0, '2026-08-11 20:05:46', '2026-08-11 20:05:46'),
(84, 7, 'ALTERANDO O ALINHAMENTO DA CÉLULA', 'Trabalhandoo com grupo alinhamento', '', 0, '2026-08-11 20:06:06', '2026-08-11 20:06:06'),
(85, 7, 'ALTERANDO A FORMATAÇÃO DOS VALORES NUMÉRICOS', 'Trabalhando com grupo números', '', 0, '2026-08-11 20:06:25', '2026-08-11 20:06:25'),
(86, 7, 'INSERINDO BORDAS', 'Ferramenta boordas e formatação de bordas', '', 0, '2026-08-11 20:06:46', '2026-08-11 20:06:46'),
(87, 7, 'COPIANDO UMA FORMATAÇÃO', 'Trabalhando com ferramenta copiar', '', 0, '2026-08-11 20:07:18', '2026-08-11 20:07:18'),
(88, 7, 'MOVENDO CÉLULAS, COLUNAS OU LINHAS', 'Movendo dados de uma célula para outra', '', 0, '2026-08-11 20:07:39', '2026-08-11 20:07:39'),
(89, 7, 'COPIANDO CÉLULAS, COLUNAS OU LINHAS', 'Copiando todoo conteúdo de uma célula para outra', '', 0, '2026-08-11 20:08:09', '2026-08-11 20:08:09'),
(90, 7, 'LOCALIZANDO E SUBSTITUINDO DADOS', 'Utilizando ferramenta localizar', '', 0, '2026-08-11 20:08:52', '2026-08-11 20:08:52'),
(91, 7, 'SUBSTITUINDO DADOS', 'Ferramenta substituir conteúdo', '', 0, '2026-08-11 20:09:07', '2026-08-11 20:09:07'),
(92, 11, 'CRIANDO UMA PASTA DE TRABALHO MODELO', 'ABRINDO UMA PASTA DE TRABALHO MODELO \r\nAPAGANDO UMA PASTA DE TRABALHO MODELO', '', 0, '2026-08-18 19:27:53', '2026-08-18 19:27:53'),
(93, 11, 'FORMATAÇÃO CONDICIONAL', 'UTILIZANDO FORMATAÇÃO CONDICIONAL SEM DEFINIR PARÂMETROS \r\nLIMPANDO A FORMATAÇÃO UTILIZANDO FORMATAÇÃO CONDICIONAL PARA DEFINIR PARÂMETROS \r\nCRIANDO NOVAS REGRAS', '', 0, '2026-08-18 19:28:27', '2026-08-18 19:28:27'),
(94, 11, 'VINCULANDO PASTAS DE TRABALHO', 'CRIANDO FÓRMULAS ENTRE PASTAS', '', 0, '2026-08-18 19:29:13', '2026-08-18 19:29:13'),
(95, 11, 'PREENCHENDO CÉLULAS AUTOMATICAMENTE', 'AUTO COMPLENTAR COM BASE DE DADOS', '', 0, '2026-08-18 19:29:40', '2026-08-18 19:29:40'),
(96, 12, 'FUNÇÕES', 'FUNÇÃO SE( ) \r\nFUNÇÃO E( ) \r\nFUNÇÃO OU( )\r\nFUNÇÃO LOCALIZAR( ) \r\nFUNÇÃO ESQUERDA( ) \r\nFUNÇÃO NUM.CARACT( ) \r\nFUNÇÃO DIREITA( ) \r\nFUNÇÕES DE DATA( )\r\nFUNÇÃO HOJE( ) \r\nFUNÇÃO DIA.DA.SEMANA( ) \r\nFUNÇÃO HORA( ) \r\nFUNÇÕES DIA( ), MÊS( ) E ANO( )', '', 0, '2026-08-18 19:30:54', '2026-08-18 19:30:54'),
(97, 13, 'INTRODUÇÃO', 'TRABALHANDO COM GUIA INSERIR CONCEITOS BÁSICOS E ELEMENTOS ESSENCIAIS', '', 0, '2026-08-18 19:32:37', '2026-08-18 19:32:37'),
(98, 13, 'INSERINDO E EDITANDO IMAGENS', 'INSERINDO IMAGENS DA MÁQUINA LOCAL\r\nINSERINDO IMAGENS ONLINE\r\nEDITANDO IMAGENS ATRAVÉS DA GUIA FORMA DE IMAGEM\r\nEDITANDO IMAGENS ATRAVÉS DE RECURSOS AVANÇADOS', '', 0, '2026-08-18 19:33:14', '2026-08-18 19:34:53'),
(99, 13, 'INSERINDO E EDITANDO FORMAS', 'INSERINDO FORMAS DA BIBLIOTECA FORMAS DO PACOTE OFFICE\r\nEDITANDO FORMAS ATRÁVES DA GUIA FORMMA DE FORMATOS', '', 0, '2026-08-18 19:36:34', '2026-08-18 19:36:34'),
(100, 13, 'INSERINDO TEXTOS EM FORMAS E CAIXA DE TEXTOS', 'INSERINDO TEXTOS E EDITANDO DENTRO DE FORMAS', '', 0, '2026-08-18 19:37:12', '2026-08-18 19:37:12'),
(101, 13, 'INSERINDO SMARTART', 'INTRODUÇÃO A SMARTART E ARQUITETURAS DE SMARTARTS\r\nO QUE LEVAR EM CONSIDERAÇÃO AO ESCOLHER UM LAYOUT?\r\nEDITANDO SMARTART', '', 0, '2026-08-18 19:37:53', '2026-08-18 19:38:22'),
(102, 14, 'CRIANDO NOME', 'DEFININDO NOME PARA UM INTERVALO \r\nEDITANDO UM NOME \r\nEXCLUINDO NOME', '', 0, '2026-08-18 19:58:17', '2026-08-18 19:58:17'),
(103, 14, 'FILTROS', 'CRIANDO FILTRO LOCAL \r\nLIMPANDO FILTRO', '', 0, '2026-08-18 19:58:48', '2026-08-18 19:58:48'),
(104, 14, 'CRIANDO FILTRO EM OUTRO LOCAL', 'UTILIZANDO VÁRIOS CRITÉRIOS COMO FILTRO \r\nUTILIZANDO CONJUNTO CRITÉRIOS COMO FILTRO \r\nUTILIZANDO CORINGAS COMO CRITÉRIO', '', 0, '2026-08-18 19:59:17', '2026-08-18 19:59:17'),
(105, 14, 'CRIAR RELATÓRIO DE TABELA DINÂMICA', 'FILTRANDO DADOS COM VÁRIOS ITENS \r\nCRIANDO TABELA DINÂMICA COM DADOS EXTERNOS', '', 0, '2026-08-18 20:02:37', '2026-08-18 20:02:37'),
(106, 15, 'IMPORTANDO E ABRINDO ARQUIVO DE TEXTO/CSV', 'IMPORTANDO E TRATANDO DADOS EM TEXTO\r\nCONVERTENDO PARA COLUNAS', '', 0, '2026-08-18 20:06:47', '2026-08-18 20:06:47'),
(107, 15, 'IMPORTANDO DADOS DO ACCESS', 'TRABALHANDO COM BANCO DE DADAOS EXPORTAÇÃO E IMPORTAÇÃO', '', 0, '2026-08-18 20:07:29', '2026-08-18 20:07:29'),
(108, 15, 'ATUALIZANDO DADOS IMPORTADOS', 'ATUALIZAÇÃO DE BASE DADOS E SINCRONIZAÇÃO', '', 0, '2026-08-18 20:09:16', '2026-08-18 20:09:16'),
(109, 15, 'AUTOMATIZANDO ATUALIZAÇÃO DE DADOS IMPORTADOS', 'CRIANDO ROTINAS AUTOMÁTICAS PARA ATUALIZAÇÃO DE DADOS', '', 0, '2026-08-18 20:09:46', '2026-08-18 20:09:46'),
(110, 16, 'CRIANDO CENÁRIOS', 'CRIANDO E MESCLANDO CENÁRIOS COM TESTE DE HIPOTESE\r\nMODIFICANDO E EDITANDO DADOS EM TESTE DE HIPOTESE GERANDO NOVOS CENÁRIOS', '', 0, '2026-08-18 20:12:37', '2026-08-18 20:13:08'),
(111, 16, 'CRIANDO RELATÓRIO DE CENÁRIO', 'GERANDO RELATÓRIOS PARA CADA CENÁRIO', '', 0, '2026-08-18 20:26:35', '2026-08-18 20:26:35'),
(112, 16, 'CRIANDO RELATÓRIO DE CENÁRIO UTILIZANDO TABELA DINÂMICA', 'GERANDO RELATÓRIOS DE CENÁRIOS DINÂMICOS COMM ATUALIZAÇÃO AUTOMÁTICA', '', 0, '2026-08-18 20:27:09', '2026-08-18 20:27:09'),
(113, 16, 'EXCLUSÃO DE CENÁRIO', 'EXCLUSÃO E LIMPEZA DE DADOS DE CENÁRIOS', '', 0, '2026-08-18 20:27:28', '2026-08-18 20:27:28'),
(114, 17, 'PROTEGENDO TODA A PLANILHA', 'CRIANDO PROTEÇÃO DE DADOS ATRAVÉS DA GUIA DADOS', '', 0, '2026-08-18 20:36:09', '2026-08-18 20:36:09'),
(115, 17, 'DESPROTEGENDO CÉLULAS E PLANILHAS', 'DESABILITANDO PROTEÇÃO DE DADOS', '', 0, '2026-08-18 20:36:24', '2026-08-18 20:36:24'),
(116, 17, 'PROTEGENDO PARTES DA PLANILHA', 'COMO PROTEGER ÁREAS SELECIONADAS CÉLUAS, COLUNAS, LINHAS E MATRIZES', '', 0, '2026-08-18 20:37:48', '2026-08-18 20:37:48'),
(117, 17, 'PROTEGENDO PASTA DE TRABALHO', 'USANDO FERRAMENTA DE SENHAS EM ARQUIVO PARA PROTEGER PASTA DE TRABALHO', '', 0, '2026-08-18 20:38:38', '2026-08-18 20:38:38'),
(118, 18, 'CRIANDO VALIDAÇÃO DE DADOS', 'PARA LIMITAR A ENTRADA A UM NÚMERO DECIMAL \r\nPARA LIMITAR O COMPRIMENTO DA ENTRADA DE TEXTO \r\nINSERINDO MENSAGEM DE ENTRADA\r\nINSERINDO ALERTA DE ERRO', '', 0, '2026-08-18 20:41:56', '2026-08-18 20:41:56'),
(119, 18, 'USANDO AUDITORIA EM CÉLULAS', 'ADICIONANDO CÉLULAS À JANELA DE INSPEÇÃO', '', 0, '2026-08-18 20:42:18', '2026-08-18 20:42:18'),
(120, 18, 'REALIZANDO AUDITORIA EM UMA PLANILHA', 'RASTREANDO CÉLULAS PRECEDENTES \r\nREMOVENDO O RASTREAMENTO', '', 0, '2026-08-18 20:42:47', '2026-08-18 20:42:47'),
(121, 18, 'CRIANDO GUIA E GRUPO', 'ADICIONANDO COMANDO AO GRUPO \r\nREMOVENDO GUIA E GRUPO', '', 0, '2026-08-18 20:43:08', '2026-08-18 20:43:08'),
(122, 19, 'SOLUCIONANDO PROBLEMAS', 'PROBLEMAS COM UMA VARIÁVEL \r\nCARREGANDO O SOLVER \r\nAPLICANDO O SOLVER\r\nFUNÇÕES PARA SOLVER', '', 0, '2026-08-18 20:45:40', '2026-08-18 20:45:40'),
(123, 19, 'AVANÇADO - MÓDULO 7 – FUNÇÕES', 'FUNÇÕES DE TEXTO \r\nFUNÇÕES DE MATEMÁTICA E TRIGONOMETRIA', '', 0, '2026-08-18 20:47:02', '2026-08-18 20:47:02'),
(124, 20, 'FUNÇÃO CONT.NÚM', 'CONTAR NÚMEROS', '', 0, '2026-08-18 21:02:39', '2026-08-18 21:02:39'),
(125, 20, 'FUNÇÃO CONT.VALORES', 'CONTAR VALORES', '', 0, '2026-08-18 21:02:55', '2026-08-18 21:02:55'),
(126, 20, 'FUNÇÃO CONT.VAZIO', 'CONTAR CÉLULAS VAZIAS', '', 0, '2026-08-18 21:04:51', '2026-08-18 21:04:51'),
(127, 20, 'FUNÇÃO CONT.SE', 'CONTAR COM CONDIÇÕES LÓGICAS', '', 0, '2026-08-18 21:05:12', '2026-08-18 21:05:12'),
(128, 20, 'FUNÇÕES DE PESQUISA E REFERÊNCIA', 'PROCH()\r\nPROCV()\r\nPROCX()', '', 0, '2026-08-18 21:06:54', '2026-08-18 21:06:54'),
(129, 20, 'FUNÇÃO ÍNDICE', 'FUNÇÃO INDICE PARA BUSCA DE CONTEÚDO', '', 0, '2026-08-18 21:07:24', '2026-08-18 21:07:24'),
(130, 20, 'FUNÇÃO CORRESP', 'FUNÇÃO CORRESPONDER INTERVÁLOS E CÉLULAS', '', 0, '2026-08-18 21:08:08', '2026-08-18 21:08:08'),
(131, 20, 'FUNÇÃO DE BANCO DE DADOS', 'FUNÇÃO BDMÉDIA( ) \r\nFUNÇÃO BDCONTAR( )\r\nFUNÇÃO BDMÍN( ) \r\nFUNÇÃO BDMÁX( ) \r\nFUNÇÃO BDSOMA( )', '', 0, '2026-08-18 21:09:05', '2026-08-18 21:09:05'),
(133, 21, 'TRATAMENTO DE ERROS EM FÓRMULAS E FUNÇÕES', 'FUNÇÃO SEERRO( ) \r\nFUNÇÃO SENÃODISP( )\r\nFUNÇÕES FINANCEIRAS \r\nFUNÇÃO NPER( ) \r\nFUNÇÃO PGTO( ) \r\nFUNÇÃO VP( ) \r\nFUNÇÃO TAXA( ) \r\nFUNÇÃO VF( )', '', 0, '2026-08-18 21:23:03', '2026-08-18 21:23:03'),
(134, 21, 'TABELA DE DADOS E VARIÁVEIS DE ENTRADA', 'TABELA DE DADOS COM UMA VARIÁVEL DE ENTRADA \r\nTABELA DE DADOS COM DUAS VARIÁVEIS DE ENTRADA', '', 0, '2026-08-18 21:23:26', '2026-08-18 21:23:26'),
(135, 21, 'MACRO', 'HABILITANDO A GUIA DESENVOLVEDOR \r\nOPÇÕES DA GUIA DESENVOLVEDOR \r\nGRAVANDO MACRO UTILIZANDO REFERÊNCIA RELATIVA \r\nSALVANDO PASTA DE TRABALHO HABILITADA PARA MACRO \r\nEXECUTANDO MACRO ATRAVÉS DE ATALHO \r\nATRIBUINDO MACRO A UM BOTÃO \r\nFORMULÁRIOS PERSONALIZADOS', '', 0, '2026-08-18 21:24:02', '2026-08-18 21:24:02'),
(136, 22, 'CRIANDO CAMPOS DO FORMULÁRIO', 'BASE DE REFERÊNCIA PARA ÍNDICE', '', 0, '2026-08-18 21:26:13', '2026-08-18 21:26:13'),
(137, 22, 'CRIANDO CONTROLES DE FORMULÁRIO', 'BOTÕES E COMANDOS', '', 0, '2026-08-18 21:26:27', '2026-08-18 21:26:27'),
(138, 22, 'ATRIBUINDO CONTROLES DE OPÇÕES', 'CONFIGURANDO CONTROLES E OPÇÕES', '', 0, '2026-08-18 21:26:58', '2026-08-18 21:26:58'),
(139, 22, 'CARREGANDO INFORMAÇÕES PARA O FORMULÁRIO', 'CARREGANDO BASE DE DADOS NO FORMULÁRIO', '', 0, '2026-08-18 21:27:17', '2026-08-18 21:27:17'),
(140, 22, 'CALCULANDO TOTAIS USANDO FUNÇÃO SE E INDICE COM FORMULA MATEMÁTICA', 'FUNÇÕES E FÓRMULAS', '', 0, '2026-08-18 21:28:18', '2026-08-18 21:28:18'),
(141, 22, 'FORMATAÇÃO E LAYOUT', 'FORMATANDO FORMULÁRIO \r\nOCULTANDO GRADE, LINHAS E FERRAMENTAS', '', 0, '2026-08-18 21:28:43', '2026-08-18 21:28:43');

-- --------------------------------------------------------

--
-- Estrutura para tabela `course_modules`
--

CREATE TABLE `course_modules` (
  `id` int(10) UNSIGNED NOT NULL,
  `track_id` int(10) UNSIGNED NOT NULL,
  `module_name` varchar(190) NOT NULL DEFAULT '',
  `module_date` varchar(25) NOT NULL DEFAULT '',
  `module_time` varchar(25) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` varchar(25) NOT NULL,
  `updated_at` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `course_modules`
--

INSERT INTO `course_modules` (`id`, `track_id`, `module_name`, `module_date`, `module_time`, `description`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 1, 'Windows', '', '', 'Introdução a Sistema Windows', 0, '2026-08-06 13:22:20', '2026-08-10 13:42:35'),
(2, 1, 'Word', '', '', 'Introdução ao Word nível Básico', 0, '2026-08-06 13:41:54', '2026-08-10 13:42:42'),
(3, 1, 'Excel', '', '', 'Introdução ao Excel nível Básico', 0, '2026-08-06 21:47:39', '2026-08-06 21:47:39'),
(4, 1, 'PowerPoint', '', '', 'Introdução Microsoft PowerPoint', 0, '2026-08-10 13:41:58', '2026-08-10 13:42:21'),
(5, 2, 'BÁSICO - MÓDULO 1 – INICIANDO O EXCEL', '', '', 'CONHECENDO A ÁREA DE TRABALHO\r\nGERENCIANDO PASTAS DE TRABALHO\r\nSALVANDO A PASTA DE TRABALHO PELA PRIMEIRA VEZ\r\nINSERINDO DADOS \r\nUSANDO MODELOS PRONTOS PARA CRIAR PLANILHAS\r\nAUTORECUPERAÇÃO', 0, '2026-08-10 18:41:20', '2026-08-11 18:54:43'),
(6, 2, 'BÁSICO - MÓDULO 2 – EDITANDO A PASTA DE TRABALHO OU A PLANILHA', '', '', 'ADICIONANDO UMA NOVA PLANILHA \r\nSELECIONANDO PLANILHAS \r\nSELECIONANDO TODAS AS PLANILHAS \r\nALTERANDO O NOME DAS PLANILHAS \r\nNAVEGANDO PELA PLANILHA ATIVA \r\nSUBSTITUINDO DADOS \r\nALTERANDO A LARGURA DAS COLUNAS \r\nUSANDO O COMANDO FORMATAR \r\nINSERINDO CÉLULAS \r\nINSERINDO COLUNAS \r\nINSERINDO LINHAS \r\nEXCLUINDO CÉLULA \r\nEXCLUINDO COLUNA E LINHA \r\nEXCLUINDO PLANILHA', 0, '2026-08-10 18:48:41', '2026-08-11 18:54:55'),
(7, 2, 'BÁSICO - MÓDULO 3 – FORMATANDO TABELAS E CÉLULAS', '', '', 'FORMATA TABELA (FERRAMENTA DE TABELA) \r\nDEFININDO UM INTERVALO COM FORMATO TABELA \r\nGUIA CONTEXTUAL (FERRAMENTAS DE TABELA) \r\nALTERANDO O NOME DE UMA TABELA \r\nEXPANDINDO A ÁREA DE UMA TABELAEXPANDINDO A ÁREA DA TABELA PARA LINHAS \r\nEXPANDINDO A ÁREA DA TABELA PARA COLUNAS \r\nFILTRANDO DADOS DA TABELA \r\nLIMPANDO O RESULTADO DO FILTRO \r\nREMOVENDO DADOS REPETIDOS EM UMA TABELA \r\nCONVERTENDO UMA TABELA EM UM INTERVALO REGULAR \r\nFORMATANDO CÉLULAS \r\nALTERANDO FONTE, COR E TAMANHO DO CARACTERE \r\nALTERANDO O ALINHAMENTO DA CÉLULA \r\nALTERANDO A FORMATAÇÃO DOS VALORES NUMÉRICOS \r\nINSERINDO BORDAS \r\nCOPIANDO UMA FORMATAÇÃO \r\nMOVENDO CÉLULAS, COLUNAS OU LINHAS \r\nCOPIANDO CÉLULAS, COLUNAS OU LINHAS\r\nLOCALIZANDO E SUBSTITUINDO DADOS \r\nSUBSTITUINDO DADOS', 0, '2026-08-10 19:32:34', '2026-08-11 18:55:06'),
(8, 2, 'BÁSICO - MÓDULO 4 – TRABALHANDO COM FÓRMULAS', '', '', 'O QUE SÃO FÓRMULAS? \r\nOPERADORES MATEMÁTICOS \r\nOPERADORES DE COMPARAÇÃO \r\nOPERADORES CONCATENAÇÃO \r\nUTILIZANDO FÓRMULAS \r\nUTILIZANDO FUNÇÕES \r\nFUNÇÕES ESTATÍSTICAS \r\nMÍNIMO E MÁXIMO \r\nCLASSIFICANDO E FILTRANDO DADOS \r\nCLASSIFICANDO DADOS \r\nDEFININDO NÍVEIS DE CLASSIFICAÇÃO \r\nFILTRANDO DADOS \r\nLIMPANDO O RESULTADO DO FILTRO \r\nALTERANDO O FILTRO', 0, '2026-08-10 19:34:29', '2026-08-11 18:55:18'),
(9, 2, 'BÁSICO - MÓDULO 5 – GRÁFICOS', '', '', 'GRÁFICOS \r\nGRÁFICOS RECOMENDADOS \r\nCRIANDO UM GRÁFICO \r\nEDITANDO UM GRÁFICO \r\nFILTRANDO DADOS DE UM GRÁFICO \r\nALTERANDO O ESTILO E AS CORES DO GRÁFICO \r\nLAYOUT RÁPIDO \r\nTROCANDO OS DADOS DOS EIXOS \r\nTÍTULOS DOS EIXOS \r\n5 LINHAS DE GRADE \r\nMOVENDO GRÁFICO \r\nALTERAR TIPO DE GRÁFICO \r\nTIPOS DE GRÁFICO', 0, '2026-08-10 19:47:22', '2026-08-11 19:26:16'),
(10, 2, 'BÁSICO - MÓDULO 6 – RECURSOS', '', '', 'IMPRIMINDO PLANILHAS \r\nVERIFICANDO ORTOGRAFIA E GRAMÁTICA \r\nCORRIGINDO A ORTOGRAFIA \r\nUTILIZANDO O DICIONÁRIO DE SINÔNIMOS', 0, '2026-08-10 19:51:10', '2026-08-11 18:56:25'),
(11, 2, 'INERMEDIÁRIO - MÓDULO 1 - MODELO DE PASTA DE TRABALHO (PLANILHAS)', '', '', 'CRIANDO UMA PASTA DE TRABALHO MODELO\r\nFORMATAÇÃO CONDICIONAL \r\nLIMPANDO A FORMATAÇÃO \r\nCRIANDO NOVAS REGRAS \r\nVINCULANDO PASTAS DE TRABALHO \r\nCRIANDO FÓRMULAS ENTRE PASTAS \r\nPREENCHENDO CÉLULAS AUTOMATICAMENTE', 0, '2026-08-11 19:32:06', '2026-08-11 19:32:06'),
(12, 2, 'INERMEDIÁRIO - MÓDULO 2 – FUNÇÕES', '', '', 'FUNÇÃO SE( ) \r\nFUNÇÃO E( ) \r\nFUNÇÃO OU( )\r\nFUNÇÃO LOCALIZAR( ) \r\nFUNÇÃO ESQUERDA( ) \r\nFUNÇÃO NUM.CARACT( )\r\nFUNÇÃO DIREITA( ) \r\nFUNÇÕES DE DATA( ) \r\nFUNÇÃO HOJE( ) \r\nFUNÇÃO DIA.DA.SEMANA( ) \r\nFUNÇÃO HORA( ) \r\nFUNÇÕES DIA( ), MÊS( ) E ANO( )', 0, '2026-08-11 19:33:39', '2026-08-11 19:33:39'),
(13, 2, 'INTERMEDIÁRIO - MÓDULO 3 - TRABALHANDO COM ELEMENTOS GRÁFICOS', '', '', 'INTRODUÇÃO\r\nINSERINDO IMAGENS \r\nEDITANDO IMAGENS \r\nINSERINDO FORMAS\r\nEDITANDO FORMAS \r\nADICIONANDO TEXTO À FORMA \r\nINSERINDO SMARTART \r\n4 O QUE LEVAR EM CONSIDERAÇÃO AO ESCOLHER UM LAYOUT? \r\nEDITANDO SMARTART', 0, '2026-08-11 19:35:00', '2026-08-11 19:35:00'),
(14, 2, 'AVANÇADO - MÓDULO 1 – TRABALHANDO COM NOMES', '', '', 'CRIANDO NOME\r\nSUBTOTAL\r\nFILTROS\r\nCRIANDO FILTRO EM OUTRO LOCAL\r\nCRIAR RELATÓRIO DE TABELA DINÂMICA', 0, '2026-08-18 19:55:46', '2026-08-18 19:55:46'),
(15, 2, 'AVANÇADO - MÓDULO 2 – IMPORTAÇÃO DE DADOS', '', '', 'IMPORTANDO E ABRINDO ARQUIVO DE TEXTO/CSV \r\nIMPORTANDO DADOS DO ACCESS \r\nATUALIZANDO DADOS IMPORTADOS \r\nAUTOMATIZANDO ATUALIZAÇÃO DE DADOS IMPORTADOS', 0, '2026-08-18 20:05:54', '2026-08-18 20:05:54'),
(16, 2, 'AVANÇADO - MÓDULO 3 – CENÁRIOS', '', '', 'CRIANDO CENÁRIOS \r\nCRIANDO RELATÓRIO DE CENÁRIO CRIANDO RELATÓRIO DE CENÁRIO UTILIZANDO TABELA DINÂMICA \r\nEXCLUSÃO DE CENÁRIO', 0, '2026-08-18 20:11:19', '2026-08-18 20:11:19'),
(17, 2, 'AVANÇADO - MÓDULO 4 – SEGURANÇA DAS INFORMAÇÕES', '', '', 'PROTEGENDO TODA A PLANILHA \r\nDESPROTEGENDO CÉLULAS E PLANILHAS \r\nPROTEGENDO PARTES DA PLANILHA \r\nPROTEGENDO PASTA DE TRABALHO', 0, '2026-08-18 20:35:36', '2026-08-18 20:35:36'),
(18, 2, 'AVANÇADO - MÓDULO 5 – VALIDAÇÃO USANDO AUDITORIA', '', '', 'CRIANDO VALIDAÇÃO DE DADOS \r\nUSANDO AUDITORIA EM CÉLULAS\r\nREALIZANDO AUDITORIA EM UMA PLANILHA \r\nCRIANDO GUIA E GRUPO', 0, '2026-08-18 20:40:47', '2026-08-18 20:40:47'),
(19, 2, 'AVANÇADO - MÓDULO 6 – SOLVER E FUNÇÕES', '', '', 'SOLUCIONANDO PROBLEMAS\r\nFUNÇÕES AVANÇADAS', 0, '2026-08-18 20:44:42', '2026-08-18 20:44:42'),
(20, 2, 'AVANÇADO - MÓDULO 8 – FUNÇÕES E BANCOS DE DADOS', '', '', 'FUNÇÃO CONT.NÚM \r\nFUNÇÃO CONT.VALORES \r\nFUNÇÃO CONT.VAZIO \r\nFUNÇÃO CONT.SE \r\nFUNÇÕES DE PESQUISA E REFERÊNCIA \r\nFUNÇÃO ÍNDICE \r\nFUNÇÃO CORRESP\r\nFUNÇÃO DE BANCO DE DADOS', 0, '2026-08-18 21:02:13', '2026-08-18 21:02:13'),
(21, 2, 'AVANÇADO - MÓDULO 9 – FUNÇÕES E MACROS', '', '', 'TRATAMENTO DE ERROS EM FÓRMULAS E FUNÇÕES\r\nTABELA DE DADOS E VARIÁVEIS DE ENTRADA\r\nMACRO', 0, '2026-08-18 21:21:56', '2026-08-18 21:21:56'),
(22, 2, 'AVANÇADO - MÓDULO 10 – FORMULÁRIO PERSONALIZADO', '', '', 'CRIANDO CAMPOS DO FORMULÁRIO\r\nCRIANDO CONTROLES DE FORMULÁRIO \r\nATRIBUINDO CONTROLES DE OPÇÕES \r\nCARREGANDO INFORMAÇÕES PARA O FORMULÁRIO \r\nCALCULANDO TOTAIS USANDO FUNÇÃO SE E INDICE COM FORMULA MATEMÁTICA \r\nFORMATAÇÃO E LAYOUT', 0, '2026-08-18 21:25:33', '2026-08-18 21:25:48');

-- --------------------------------------------------------

--
-- Estrutura para tabela `course_tracks`
--

CREATE TABLE `course_tracks` (
  `id` int(10) UNSIGNED NOT NULL,
  `course_name` varchar(190) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` varchar(25) NOT NULL,
  `updated_at` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `course_tracks`
--

INSERT INTO `course_tracks` (`id`, `course_name`, `description`, `is_published`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 'Informática Básica (TIC)', 'Informática Básica introdução a Sistema Windows e Microsoft Office - Word, Excel e PowerPoint', 1, 0, '2026-08-06 13:21:47', '2026-08-06 13:21:47'),
(2, 'Excel do 0 ao Avançado', 'Curso completo Excel do Básico ao Avançado MO-211', 1, 0, '2026-08-10 18:33:58', '2026-08-10 18:33:58');

-- --------------------------------------------------------

--
-- Estrutura para tabela `external_resources`
--

CREATE TABLE `external_resources` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(190) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `provider` varchar(190) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `resource_url` varchar(500) NOT NULL,
  `embed_url` varchar(500) NOT NULL DEFAULT '',
  `image_url` varchar(500) NOT NULL DEFAULT '',
  `icon_text` varchar(20) NOT NULL DEFAULT '',
  `cta_label` varchar(190) NOT NULL DEFAULT '',
  `resource_type` varchar(40) NOT NULL DEFAULT 'external',
  `show_on_home` tinyint(1) NOT NULL DEFAULT 1,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` varchar(25) NOT NULL,
  `updated_at` varchar(25) NOT NULL,
  `name_en` varchar(190) NOT NULL DEFAULT '',
  `name_es` varchar(190) NOT NULL DEFAULT '',
  `provider_en` varchar(190) NOT NULL DEFAULT '',
  `provider_es` varchar(190) NOT NULL DEFAULT '',
  `description_en` text DEFAULT NULL,
  `description_es` text DEFAULT NULL,
  `cta_label_en` varchar(190) NOT NULL DEFAULT '',
  `cta_label_es` varchar(190) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `external_resources`
--

INSERT INTO `external_resources` (`id`, `name`, `slug`, `provider`, `description`, `resource_url`, `embed_url`, `image_url`, `icon_text`, `cta_label`, `resource_type`, `show_on_home`, `is_published`, `display_order`, `created_at`, `updated_at`, `name_en`, `name_es`, `provider_en`, `provider_es`, `description_en`, `description_es`, `cta_label_en`, `cta_label_es`) VALUES
(1, 'Fluxa', 'fluxa', 'DavidCreator', 'Ferramenta para gestão financeira e acompanhamento de indicadores.', 'https://davidcreator.com/fluxa', '', '/catalog/assets/images/tools/fluxa.svg', 'FL', 'Abrir Fluxa', 'external', 1, 1, 1, '2026-05-20 19:51:36', '2026-05-20 19:51:36', '', '', '', '', NULL, NULL, '', ''),
(2, 'Quotia', 'quotia', 'DavidCreator', 'Plataforma para solicitar e organizar orçamentos em um único fluxo.', 'https://davidcreator.com/quotia', '', '/catalog/assets/images/tools/quotia.svg', 'QU', 'Abrir Quotia', 'external', 1, 1, 2, '2026-05-20 19:51:36', '2026-05-20 19:51:36', '', '', '', '', NULL, NULL, '', ''),
(3, 'Vertex', 'vertex', 'DavidCreator', 'Gerador gratuito de currículos com foco em clareza e apresentação profissional.', 'https://davidcreator.com/vertex', '', '/catalog/assets/images/tools/vertex.svg', 'VX', 'Abrir Vertex', 'external', 1, 1, 3, '2026-05-20 19:51:36', '2026-05-20 19:51:36', '', '', '', '', NULL, NULL, '', ''),
(4, 'Solis', 'solis', 'DavidCreator', 'Gerador estratégico para planejamento de conteúdo e postagens em redes sociais.', 'https://davidcreator.com/solis', '', '/catalog/assets/images/tools/solis.svg', 'SO', 'Abrir Solis', 'external', 1, 1, 4, '2026-05-20 19:51:36', '2026-05-20 19:51:36', '', '', '', '', NULL, NULL, '', '');

-- --------------------------------------------------------

--
-- Estrutura para tabela `external_resource_clicks`
--

CREATE TABLE `external_resource_clicks` (
  `id` int(10) UNSIGNED NOT NULL,
  `resource_id` int(10) UNSIGNED NOT NULL,
  `resource_slug` varchar(190) NOT NULL DEFAULT '',
  `resource_name` varchar(190) NOT NULL DEFAULT '',
  `source_page` varchar(255) NOT NULL DEFAULT '',
  `action_type` varchar(40) NOT NULL DEFAULT 'open',
  `ip_address` varchar(64) NOT NULL DEFAULT '',
  `user_agent` varchar(500) NOT NULL DEFAULT '',
  `referer` varchar(500) NOT NULL DEFAULT '',
  `created_at` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `external_resource_clicks`
--

INSERT INTO `external_resource_clicks` (`id`, `resource_id`, `resource_slug`, `resource_name`, `source_page`, `action_type`, `ip_address`, `user_agent`, `referer`, `created_at`) VALUES
(1, 2, 'quotia', 'Quotia', 'home', 'open', '216.73.217.109', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-05-24 10:47:06'),
(2, 1, 'fluxa', 'Fluxa', 'home', 'open', '216.73.217.109', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-05-24 10:47:34'),
(3, 4, 'solis', 'Solis', 'home', 'open', '216.73.217.109', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-05-24 10:50:38'),
(4, 3, 'vertex', 'Vertex', 'home', 'open', '216.73.217.109', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-05-24 10:50:38'),
(5, 4, 'solis', 'Solis', 'tools', 'open', '216.73.217.109', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-05-24 11:53:36'),
(6, 3, 'vertex', 'Vertex', 'tools', 'open', '216.73.217.109', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-05-24 11:53:36'),
(7, 2, 'quotia', 'Quotia', 'tools', 'open', '216.73.217.109', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-05-24 11:53:36'),
(8, 1, 'fluxa', 'Fluxa', 'tools', 'open', '216.73.217.109', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-05-24 11:53:54'),
(9, 3, 'vertex', 'Vertex', 'home', 'open', '66.249.70.162', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.7778.96 Mobile Safari/537.36 (compatible; GoogleOther)', '', '2026-05-24 18:41:30'),
(10, 4, 'solis', 'Solis', 'home', 'open', '66.249.70.163', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.7778.96 Mobile Safari/537.36 (compatible; GoogleOther)', '', '2026-05-24 18:42:33'),
(11, 1, 'fluxa', 'Fluxa', 'home', 'open', '66.249.70.162', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.7778.96 Mobile Safari/537.36 (compatible; GoogleOther)', '', '2026-05-24 18:52:13'),
(12, 3, 'vertex', 'Vertex', 'home', 'open', '66.249.70.164', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.7778.96 Mobile Safari/537.36 (compatible; GoogleOther)', '', '2026-05-24 19:07:30'),
(13, 2, 'quotia', 'Quotia', 'home', 'open', '66.249.70.163', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.7778.96 Mobile Safari/537.36 (compatible; GoogleOther)', '', '2026-05-24 19:13:39'),
(14, 3, 'vertex', 'Vertex', 'tools', 'open', '66.249.70.162', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.7778.96 Mobile Safari/537.36 (compatible; GoogleOther)', '', '2026-05-24 21:56:32'),
(15, 2, 'quotia', 'Quotia', 'tools', 'open', '66.249.70.162', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.7778.96 Mobile Safari/537.36 (compatible; GoogleOther)', '', '2026-05-24 22:04:02'),
(16, 4, 'solis', 'Solis', 'tools', 'open', '66.249.70.163', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.7778.96 Mobile Safari/537.36 (compatible; GoogleOther)', '', '2026-05-24 23:42:32'),
(17, 3, 'vertex', 'Vertex', 'tools', 'open', '66.249.70.162', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.7778.96 Mobile Safari/537.36 (compatible; GoogleOther)', '', '2026-05-24 23:42:34'),
(18, 1, 'fluxa', 'Fluxa', 'tools', 'open', '66.249.70.162', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.7778.96 Mobile Safari/537.36 (compatible; GoogleOther)', '', '2026-05-25 00:07:30'),
(19, 4, 'solis', 'Solis', 'tools', 'open', '66.249.70.162', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.7778.96 Mobile Safari/537.36 (compatible; GoogleOther)', '', '2026-05-25 00:20:00'),
(20, 2, 'quotia', 'Quotia', 'tools', 'open', '66.249.70.162', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.7778.96 Mobile Safari/537.36 (compatible; GoogleOther)', '', '2026-05-25 00:38:45'),
(21, 1, 'fluxa', 'Fluxa', 'tools', 'open', '66.249.70.162', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.7778.96 Mobile Safari/537.36 (compatible; GoogleOther)', '', '2026-05-25 00:57:30'),
(22, 1, 'fluxa', 'Fluxa', 'home', 'open', '2804:d45:b332:dc00:6479:6e59:61c1:557e', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '', '2026-05-25 14:19:08'),
(23, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.83', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-05-25 17:13:09'),
(24, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.189', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-05-25 17:13:13'),
(25, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.199', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-05-25 17:13:15'),
(26, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.138', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-05-25 17:13:21'),
(27, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.131', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-05-25 17:15:19'),
(28, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.172', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-05-25 17:15:21'),
(29, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.88', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-05-25 17:15:23'),
(30, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.21', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-05-25 17:15:25'),
(31, 4, 'solis', 'Solis', 'home', 'open', '104.37.27.122', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0', '', '2026-05-27 03:21:57'),
(32, 1, 'fluxa', 'Fluxa', 'home', 'open', '104.37.27.122', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0', '', '2026-05-27 03:22:01'),
(33, 2, 'quotia', 'Quotia', 'home', 'open', '104.37.27.122', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0', '', '2026-05-27 03:22:02'),
(34, 3, 'vertex', 'Vertex', 'home', 'open', '104.37.27.122', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0', '', '2026-05-27 03:22:04'),
(35, 1, 'fluxa', 'Fluxa', 'tools', 'open', '104.37.27.122', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0', '', '2026-05-27 03:22:05'),
(36, 3, 'vertex', 'Vertex', 'tools', 'open', '104.37.27.122', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0', '', '2026-05-27 03:22:06'),
(37, 4, 'solis', 'Solis', 'tools', 'open', '104.37.27.122', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0', '', '2026-05-27 03:22:07'),
(38, 2, 'quotia', 'Quotia', 'tools', 'open', '104.37.27.122', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0', '', '2026-05-27 03:22:10'),
(39, 1, 'fluxa', 'Fluxa', 'home', 'open', '83.99.206.201', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko; GeedoShopProductFinder) Chrome/142.0.0.0 Safari/537.36', '', '2026-05-27 23:00:03'),
(40, 2, 'quotia', 'Quotia', 'home', 'open', '83.99.206.201', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko; GeedoShopProductFinder) Chrome/142.0.0.0 Safari/537.36', '', '2026-05-27 23:01:04'),
(41, 2, 'quotia', 'Quotia', 'tools', 'open', '83.99.206.69', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko; GeedoShopProductFinder) Chrome/142.0.0.0 Safari/537.36', '', '2026-05-27 23:01:43'),
(42, 2, 'quotia', 'Quotia', 'tools', 'open', '83.99.206.201', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko; GeedoShopProductFinder) Chrome/142.0.0.0 Safari/537.36', '', '2026-05-27 23:01:45'),
(43, 3, 'vertex', 'Vertex', 'home', 'open', '83.99.206.57', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko; GeedoShopProductFinder) Chrome/142.0.0.0 Safari/537.36', '', '2026-05-27 23:04:13'),
(44, 3, 'vertex', 'Vertex', 'tools', 'open', '83.99.206.12', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko; GeedoShopProductFinder) Chrome/142.0.0.0 Safari/537.36', '', '2026-05-27 23:06:50'),
(45, 3, 'vertex', 'Vertex', 'tools', 'open', '83.99.206.30', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko; GeedoShopProductFinder) Chrome/142.0.0.0 Safari/537.36', '', '2026-05-27 23:08:01'),
(46, 3, 'vertex', 'Vertex', 'tools', 'open', '83.99.206.184', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko; GeedoShopProductFinder) Chrome/142.0.0.0 Safari/537.36', '', '2026-05-27 23:12:34'),
(47, 3, 'vertex', 'Vertex', 'home', 'open', '190.97.240.5', 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.2309.372 Safari/537.36', '', '2026-05-29 02:14:42'),
(48, 2, 'quotia', 'Quotia', 'home', 'open', '103.82.120.84', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36', '', '2026-05-29 16:49:11'),
(49, 1, 'fluxa', 'Fluxa', 'home', 'open', '186.53.80.200', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Brave Chrome/86.0.4240.183 Safari/537.36', '', '2026-05-29 22:39:35'),
(50, 3, 'vertex', 'Vertex', 'home', 'open', '102.186.11.121', 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.86 Safari/537.36', '', '2026-05-30 03:02:44'),
(51, 4, 'solis', 'Solis', 'home', 'open', '165.165.95.103', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36', '', '2026-05-30 08:02:23'),
(52, 1, 'fluxa', 'Fluxa', 'home', 'open', '14.242.157.220', 'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36', '', '2026-05-30 23:37:02'),
(53, 3, 'vertex', 'Vertex', 'home', 'open', '102.67.159.158', 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2224.3 Safari/537.36', '', '2026-05-31 03:37:49'),
(54, 4, 'solis', 'Solis', 'home', 'open', '186.194.149.29', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.121 Safari/537.36', '', '2026-05-31 08:33:40'),
(55, 1, 'fluxa', 'Fluxa', 'home', 'open', '2001:470:36:65:80e3:57ff:fe65:bdf1', 'Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)', '', '2026-05-31 16:44:22'),
(56, 2, 'quotia', 'Quotia', 'home', 'open', '2001:470:36:65:80e3:57ff:fe65:bdf1', 'Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)', '', '2026-05-31 16:45:07'),
(57, 3, 'vertex', 'Vertex', 'home', 'open', '2001:41d0:303:5899::1', 'Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)', '', '2026-05-31 20:29:52'),
(58, 3, 'vertex', 'Vertex', 'tools', 'open', '2001:41d0:303:5899::1', 'Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)', '', '2026-05-31 20:30:00'),
(59, 1, 'fluxa', 'Fluxa', 'tools', 'open', '2001:41d0:303:9cee::1', 'Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)', '', '2026-06-01 02:00:27'),
(60, 2, 'quotia', 'Quotia', 'tools', 'open', '2001:41d0:303:523::1', 'Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)', '', '2026-06-01 03:05:49'),
(61, 4, 'solis', 'Solis', 'home', 'open', '2001:41d0:303:523::1', 'Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)', '', '2026-06-01 03:05:52'),
(62, 4, 'solis', 'Solis', 'tools', 'open', '2001:41d0:303:523::1', 'Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)', '', '2026-06-01 03:05:55'),
(63, 2, 'quotia', 'Quotia', 'tools', 'open', '2001:41d0:303:523::1', 'Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)', '', '2026-06-01 03:05:59'),
(64, 4, 'solis', 'Solis', 'home', 'open', '2001:41d0:303:523::1', 'Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)', '', '2026-06-01 03:06:02'),
(65, 4, 'solis', 'Solis', 'tools', 'open', '2001:41d0:303:523::1', 'Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)', '', '2026-06-01 03:06:38'),
(66, 2, 'quotia', 'Quotia', 'tools', 'open', '2001:41d0:303:523::1', 'Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)', '', '2026-06-01 03:06:44'),
(67, 4, 'solis', 'Solis', 'home', 'open', '2001:41d0:303:523::1', 'Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)', '', '2026-06-01 03:06:55'),
(68, 4, 'solis', 'Solis', 'home', 'open', '2001:41d0:303:523::1', 'Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)', '', '2026-06-01 03:07:17'),
(69, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.227.161.195', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_3) AppleWebKit/537.36 (KHTML, like Gecko, Mediapartners-Google) Chrome/84.0.4147.140 Safari/537.36', '', '2026-06-01 07:24:59'),
(70, 3, 'vertex', 'Vertex', 'home', 'open', '123.19.82.126', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.1 Safari/537.36', '', '2026-06-01 10:57:15'),
(71, 3, 'vertex', 'Vertex', 'home', 'open', '5.0.116.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.4591.54 Safari/537.36', '', '2026-06-01 10:57:24'),
(72, 4, 'solis', 'Solis', 'home', 'open', '187.244.225.226', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.88 Safari/537.36', '', '2026-06-01 11:52:02'),
(73, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.120', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-01 17:23:57'),
(74, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.107', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-01 17:24:01'),
(75, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.137', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-01 17:24:03'),
(76, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.101', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-01 17:24:09'),
(77, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.6', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-01 17:26:07'),
(78, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.28', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-01 17:26:09'),
(79, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.223', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-01 17:26:11'),
(80, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.94', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-01 17:26:13'),
(81, 2, 'quotia', 'Quotia', 'home', 'open', '80.85.245.145', '\"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36\"', '', '2026-06-02 13:50:03'),
(82, 3, 'vertex', 'Vertex', 'home', 'open', '123.20.23.166', 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3325.785 Safari/537.36', '', '2026-06-03 13:32:07'),
(83, 1, 'fluxa', 'Fluxa', 'home', 'open', '102.130.222.250', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/106.0.0.0 Safari/537.36', '', '2026-06-04 23:08:09'),
(84, 2, 'quotia', 'Quotia', 'home', 'open', '84.54.94.112', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.93 Safari/537.36', '', '2026-06-05 06:54:58'),
(85, 4, 'solis', 'Solis', 'tools', 'open', '14.173.63.28', 'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.114 Safari/537.36', '', '2026-06-05 17:24:20'),
(86, 2, 'quotia', 'Quotia', 'tools', 'open', '190.108.77.134', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.84 Safari/537.36', '', '2026-06-05 18:15:27'),
(87, 1, 'fluxa', 'Fluxa', 'tools', 'open', '103.138.173.12', 'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.100 Safari/537.36', '', '2026-06-06 16:37:09'),
(88, 2, 'quotia', 'Quotia', 'tools', 'open', '171.250.161.154', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.132 Safari/537.36', '', '2026-06-06 18:16:39'),
(89, 2, 'quotia', 'Quotia', 'tools', 'open', '45.71.40.46', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Brave Chrome/78.0.3904.108 Safari/537.36', '', '2026-06-06 18:16:55'),
(90, 1, 'fluxa', 'Fluxa', 'home', 'open', '190.219.100.170', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3865.120 Safari/537.36', '', '2026-06-06 21:44:07'),
(91, 3, 'vertex', 'Vertex', 'tools', 'open', '45.189.117.30', 'Mozilla/5.0 (Windows NT 6.2; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.4951.54 Safari/537.36', '', '2026-06-06 21:49:28'),
(92, 3, 'vertex', 'Vertex', 'home', 'open', '123.20.46.200', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Brave Chrome/86.0.4240.99 Safari/537.36', '', '2026-06-07 08:42:37'),
(93, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.82', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-08 17:35:01'),
(94, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.204', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-08 17:35:05'),
(95, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.79', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-08 17:35:13'),
(96, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.7', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-08 17:35:13'),
(97, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.112', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-08 17:37:11'),
(98, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.20', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-08 17:37:14'),
(99, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.39', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-08 17:37:15'),
(100, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.181', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-08 17:37:17'),
(101, 1, 'fluxa', 'Fluxa', 'tools', 'open', '216.73.217.103', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-06-10 10:32:06'),
(102, 4, 'solis', 'Solis', 'home', 'open', '14.226.233.204', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36', '', '2026-06-10 10:58:12'),
(103, 3, 'vertex', 'Vertex', 'home', 'open', '14.185.175.47', 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/93.0.4558.0 Safari/537.36', '', '2026-06-10 12:14:52'),
(104, 4, 'solis', 'Solis', 'tools', 'open', '170.247.80.193', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_3) AppleWebKit/537.36 (KHTML, like Gecko, Mediapartners-Google) Chrome/81.0.4044.108 Safari/537.36', '', '2026-06-10 20:37:44'),
(105, 1, 'fluxa', 'Fluxa', 'home', 'open', '189.30.237.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.106 Safari/537.36', '', '2026-06-10 22:43:28'),
(106, 1, 'fluxa', 'Fluxa', 'tools', 'open', '154.241.223.43', 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.132 AOL/11.0 AOLBUILD/11.0.1839 Safari/537.36', '', '2026-06-11 02:36:24'),
(107, 4, 'solis', 'Solis', 'home', 'open', '179.32.204.249', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4582.189 Safari/537.36', '', '2026-06-11 10:42:00'),
(108, 4, 'solis', 'Solis', 'home', 'open', '201.164.160.17', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Brave Chrome/87.0.4280.141 Safari/537.36', '', '2026-06-11 18:34:21'),
(109, 4, 'solis', 'Solis', 'tools', 'open', '190.108.214.154', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3325.6 Safari/537.36', '', '2026-06-12 15:15:48'),
(110, 4, 'solis', 'Solis', 'home', 'open', '86.108.18.241', 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.94 Safari/537.36', '', '2026-06-12 18:32:34'),
(111, 2, 'quotia', 'Quotia', 'home', 'open', '129.122.251.135', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/94.0.4606.81 Safari/537.36', '', '2026-06-13 08:56:33'),
(112, 3, 'vertex', 'Vertex', 'home', 'open', '102.182.46.85', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_1) AppleWebKit/537.36 (KHTML, like Gecko) Brave Chrome/87.0.4280.67 Safari/537.36', '', '2026-06-13 12:47:59'),
(113, 4, 'solis', 'Solis', 'home', 'open', '156.197.200.14', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', '', '2026-06-14 10:11:04'),
(114, 2, 'quotia', 'Quotia', 'home', 'open', '123.16.199.93', 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.88 Safari/537.36', '', '2026-06-14 10:15:00'),
(115, 4, 'solis', 'Solis', 'tools', 'open', '41.10.23.44', 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.99 Safari/537.36', '', '2026-06-15 03:01:43'),
(116, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.29', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-15 17:08:40'),
(117, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.46', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-15 17:08:44'),
(118, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.193', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-15 17:08:46'),
(119, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.191', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-15 17:08:52'),
(120, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.245', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-15 17:10:50'),
(121, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.186', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-15 17:10:52'),
(122, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.122', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-15 17:10:54'),
(123, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.71', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-15 17:10:56'),
(124, 4, 'solis', 'Solis', 'tools', 'open', '14.191.106.25', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Brave Chrome/86.0.4240.75 Safari/537.36', '', '2026-06-16 03:03:06'),
(125, 4, 'solis', 'Solis', 'home', 'open', '170.83.189.154', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/94.0.4606.71 Safari/537.36', '', '2026-06-19 23:06:18'),
(126, 1, 'fluxa', 'Fluxa', 'home', 'open', '113.178.14.79', 'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.127 Safari/537.36', '', '2026-06-20 01:24:21'),
(127, 3, 'vertex', 'Vertex', 'home', 'open', '187.189.171.139', 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.100 Safari/537.36', '', '2026-06-20 04:04:47'),
(128, 3, 'vertex', 'Vertex', 'home', 'open', '190.101.185.105', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2227.0 Safari/537.36', '', '2026-06-20 20:53:01'),
(129, 4, 'solis', 'Solis', 'home', 'open', '17.22.253.98', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15 (Applebot/0.1; +http://www.apple.com/go/applebot)', '', '2026-06-20 22:33:46'),
(130, 4, 'solis', 'Solis', 'home', 'open', '196.210.66.129', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.80 Safari/537.36', '', '2026-06-20 23:58:23'),
(131, 1, 'fluxa', 'Fluxa', 'home', 'open', '17.22.237.73', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15 (Applebot/0.1; +http://www.apple.com/go/applebot)', '', '2026-06-21 03:47:42'),
(132, 2, 'quotia', 'Quotia', 'home', 'open', '17.241.75.144', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15 (Applebot/0.1; +http://www.apple.com/go/applebot)', '', '2026-06-21 05:53:47'),
(133, 3, 'vertex', 'Vertex', 'home', 'open', '17.241.227.244', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15 (Applebot/0.1; +http://www.apple.com/go/applebot)', '', '2026-06-21 10:44:43'),
(134, 4, 'solis', 'Solis', 'home', 'open', '186.211.249.116', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.89 Safari/537.36', '', '2026-06-21 21:03:59'),
(135, 2, 'quotia', 'Quotia', 'home', 'open', '191.95.144.230', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_16_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.89 Safari/537.36', '', '2026-06-22 04:45:33'),
(136, 3, 'vertex', 'Vertex', 'tools', 'open', '113.172.200.194', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.2 Safari/537.36', '', '2026-06-22 06:12:01'),
(137, 2, 'quotia', 'Quotia', 'tools', 'open', '103.135.89.206', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.80 Safari/537.36', '', '2026-06-22 15:10:26'),
(138, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.244', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-22 17:06:46'),
(139, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.237', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-22 17:06:50'),
(140, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.173', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-22 17:06:52'),
(141, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.8', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-22 17:06:58'),
(142, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.84', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-22 17:08:56'),
(143, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.74', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-22 17:08:58'),
(144, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.223', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-22 17:09:00'),
(145, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.174', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-22 17:09:02'),
(146, 2, 'quotia', 'Quotia', 'home', 'open', '186.168.200.227', 'Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/30.0.1599.17 Safari/537.36', '', '2026-06-23 04:56:36'),
(147, 3, 'vertex', 'Vertex', 'home', 'open', '181.51.32.230', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Safari/537.36', '', '2026-06-23 06:51:57'),
(148, 1, 'fluxa', 'Fluxa', 'tools', 'open', '213.6.170.11', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3325.6 Safari/537.36', '', '2026-06-23 07:50:57'),
(149, 4, 'solis', 'Solis', 'home', 'open', '196.189.17.180', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.88 Safari/537.36', '', '2026-06-23 20:58:03'),
(150, 1, 'fluxa', 'Fluxa', 'home', 'open', '197.237.141.153', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', '', '2026-06-23 22:38:37'),
(151, 1, 'fluxa', 'Fluxa', 'home', 'open', '187.234.143.151', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.93 Safari/537.36', '', '2026-06-23 22:38:51'),
(152, 2, 'quotia', 'Quotia', 'home', 'open', '102.187.155.28', 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.2 Safari/537.36', '', '2026-06-24 04:36:14'),
(153, 4, 'solis', 'Solis', 'home', 'open', '43.172.195.126', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '', '2026-06-24 04:43:25'),
(154, 2, 'quotia', 'Quotia', 'tools', 'open', '150.228.187.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', '', '2026-06-24 06:12:20'),
(155, 1, 'fluxa', 'Fluxa', 'tools', 'open', '38.250.132.77', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', '', '2026-06-24 07:27:53'),
(156, 3, 'vertex', 'Vertex', 'home', 'open', '181.188.170.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.97 Safari/537.36', '', '2026-06-25 02:34:18'),
(157, 1, 'fluxa', 'Fluxa', 'home', 'open', '154.91.163.144', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Brave Chrome/80.0.3987.122 Safari/537.36', '', '2026-06-25 22:42:07'),
(158, 3, 'vertex', 'Vertex', 'home', 'open', '38.45.249.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36', '', '2026-06-26 02:49:42'),
(159, 4, 'solis', 'Solis', 'home', 'open', '144.76.67.250', 'serpstatbot/2.1 (advanced backlink tracking bot; https://serpstatbot.com/; abuse@serpstatbot.com)', '', '2026-06-26 04:25:18'),
(160, 3, 'vertex', 'Vertex', 'home', 'open', '144.76.67.250', 'serpstatbot/2.1 (advanced backlink tracking bot; https://serpstatbot.com/; abuse@serpstatbot.com)', '', '2026-06-26 04:26:19'),
(161, 2, 'quotia', 'Quotia', 'home', 'open', '144.76.67.250', 'serpstatbot/2.1 (advanced backlink tracking bot; https://serpstatbot.com/; abuse@serpstatbot.com)', '', '2026-06-26 04:27:18'),
(162, 1, 'fluxa', 'Fluxa', 'home', 'open', '144.76.67.250', 'serpstatbot/2.1 (advanced backlink tracking bot; https://serpstatbot.com/; abuse@serpstatbot.com)', '', '2026-06-26 04:28:14'),
(163, 3, 'vertex', 'Vertex', 'tools', 'open', '144.76.67.250', 'serpstatbot/2.1 (advanced backlink tracking bot; https://serpstatbot.com/; abuse@serpstatbot.com)', '', '2026-06-26 04:58:05'),
(164, 2, 'quotia', 'Quotia', 'tools', 'open', '144.76.67.250', 'serpstatbot/2.1 (advanced backlink tracking bot; https://serpstatbot.com/; abuse@serpstatbot.com)', '', '2026-06-26 04:58:56'),
(165, 1, 'fluxa', 'Fluxa', 'tools', 'open', '144.76.67.250', 'serpstatbot/2.1 (advanced backlink tracking bot; https://serpstatbot.com/; abuse@serpstatbot.com)', '', '2026-06-26 04:59:53'),
(166, 4, 'solis', 'Solis', 'tools', 'open', '144.76.67.250', 'serpstatbot/2.1 (advanced backlink tracking bot; https://serpstatbot.com/; abuse@serpstatbot.com)', '', '2026-06-26 05:01:33'),
(167, 2, 'quotia', 'Quotia', 'home', 'open', '201.119.217.234', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Brave Chrome/87.0.4280.141 Safari/537.36', '', '2026-06-26 05:03:49'),
(168, 1, 'fluxa', 'Fluxa', 'tools', 'open', '103.115.199.139', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36', '', '2026-06-26 07:49:18'),
(169, 4, 'solis', 'Solis', 'tools', 'open', '103.166.100.220', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36', '', '2026-06-27 03:02:25'),
(170, 1, 'fluxa', 'Fluxa', 'home', 'open', '2a0b:6580::76', 'Mozilla/5.0 (compatible; intelx.io_bot +https://intelx.io)', '', '2026-06-27 06:37:51'),
(171, 2, 'quotia', 'Quotia', 'home', 'open', '2a0b:6580::76', 'Mozilla/5.0 (compatible; intelx.io_bot +https://intelx.io)', '', '2026-06-27 06:37:51'),
(172, 3, 'vertex', 'Vertex', 'home', 'open', '2a0b:6580::76', 'Mozilla/5.0 (compatible; intelx.io_bot +https://intelx.io)', '', '2026-06-27 06:37:52'),
(173, 4, 'solis', 'Solis', 'home', 'open', '2a0b:6580::76', 'Mozilla/5.0 (compatible; intelx.io_bot +https://intelx.io)', '', '2026-06-27 06:37:53'),
(174, 1, 'fluxa', 'Fluxa', 'tools', 'open', '2a0b:6580::76', 'Mozilla/5.0 (compatible; intelx.io_bot +https://intelx.io)', '', '2026-06-27 06:37:58'),
(175, 1, 'fluxa', 'Fluxa', 'home', 'open', '191.85.24.166', 'Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.88 Safari/537.36', '', '2026-06-28 02:04:35'),
(176, 1, 'fluxa', 'Fluxa', 'tools', 'open', '190.103.29.30', 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.107 Safari/537.36', '', '2026-06-28 02:32:25'),
(177, 1, 'fluxa', 'Fluxa', 'home', 'open', '103.4.251.148', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', '', '2026-06-28 11:30:38'),
(178, 3, 'vertex', 'Vertex', 'home', 'open', '103.4.251.148', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', '', '2026-06-28 11:30:38'),
(179, 2, 'quotia', 'Quotia', 'home', 'open', '103.4.251.148', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', '', '2026-06-28 11:30:39'),
(180, 4, 'solis', 'Solis', 'home', 'open', '103.4.251.148', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', '', '2026-06-28 11:30:39'),
(181, 2, 'quotia', 'Quotia', 'home', 'open', '154.28.229.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '', '2026-06-28 12:10:15'),
(182, 3, 'vertex', 'Vertex', 'home', 'open', '154.28.229.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '', '2026-06-28 12:10:15'),
(183, 1, 'fluxa', 'Fluxa', 'home', 'open', '154.28.229.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '', '2026-06-28 12:10:15'),
(184, 4, 'solis', 'Solis', 'home', 'open', '154.28.229.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '', '2026-06-28 12:10:16'),
(185, 2, 'quotia', 'Quotia', 'home', 'open', '136.66.191.137', 'Mozilla/5.0 (Linux; Android 12; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36', '', '2026-06-29 00:21:53'),
(186, 4, 'solis', 'Solis', 'home', 'open', '136.66.191.137', 'Mozilla/5.0 (Linux; Android 12; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36', '', '2026-06-29 00:21:53'),
(187, 3, 'vertex', 'Vertex', 'home', 'open', '136.66.191.137', 'Mozilla/5.0 (Linux; Android 12; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36', '', '2026-06-29 00:21:53'),
(188, 1, 'fluxa', 'Fluxa', 'home', 'open', '136.66.191.137', 'Mozilla/5.0 (Linux; Android 12; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36', '', '2026-06-29 00:21:53'),
(189, 1, 'fluxa', 'Fluxa', 'tools', 'open', '2a02:4780:2d:b2ca::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '', '2026-06-29 07:17:56'),
(190, 2, 'quotia', 'Quotia', 'tools', 'open', '2a02:4780:2d:b2ca::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0', '', '2026-06-29 07:17:56'),
(191, 3, 'vertex', 'Vertex', 'tools', 'open', '2a02:4780:2d:b2ca::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 15_7_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Safari/605.1.15', '', '2026-06-29 07:17:56'),
(192, 4, 'solis', 'Solis', 'tools', 'open', '2a02:4780:2d:b2ca::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/146.0.3856.109', '', '2026-06-29 07:17:56'),
(193, 1, 'fluxa', 'Fluxa', 'tools', 'open', '2a02:4780:2d:b2ca::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '', '2026-06-29 07:17:58'),
(194, 2, 'quotia', 'Quotia', 'tools', 'open', '2a02:4780:2d:b2ca::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '', '2026-06-29 07:17:58'),
(195, 3, 'vertex', 'Vertex', 'tools', 'open', '2a02:4780:2d:b2ca::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '', '2026-06-29 07:17:58'),
(196, 4, 'solis', 'Solis', 'tools', 'open', '2a02:4780:2d:b2ca::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 15_7_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Safari/605.1.15', '', '2026-06-29 07:17:58'),
(197, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.137', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-29 17:32:12'),
(198, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.114', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-29 17:32:16'),
(199, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.243', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-29 17:32:18'),
(200, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.233', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-29 17:32:24'),
(201, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.241', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-29 17:34:22'),
(202, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.48', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-29 17:34:24'),
(203, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.235', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-29 17:34:26'),
(204, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.108', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-06-29 17:34:28'),
(205, 2, 'quotia', 'Quotia', 'tools', 'open', '177.74.204.39', 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.102 Safari/537.36', '', '2026-07-01 02:19:43'),
(206, 2, 'quotia', 'Quotia', 'home', 'open', '190.5.33.144', 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.125 Safari/537.36', '', '2026-07-02 02:15:56'),
(207, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.248', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-06 17:15:26'),
(208, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.113', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-06 17:15:30'),
(209, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.186', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-06 17:15:32'),
(210, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.93', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-06 17:15:38'),
(211, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.140', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-06 17:17:36'),
(212, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.61', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-06 17:17:38'),
(213, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.29', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-06 17:17:41'),
(214, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.61', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-06 17:17:43'),
(215, 1, 'fluxa', 'Fluxa', 'home', 'open', '66.93.160.77', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; SEOJuice-SearchBot/1.0; +https://seojuice.io/bot', '', '2026-07-06 22:08:06'),
(216, 3, 'vertex', 'Vertex', 'home', 'open', '154.208.41.47', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.114 Safari/537.36', '', '2026-07-09 02:17:02'),
(217, 1, 'fluxa', 'Fluxa', 'tools', 'open', '197.89.2.35', 'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.81 Safari/537.36', '', '2026-07-09 02:20:04'),
(218, 1, 'fluxa', 'Fluxa', 'home', 'open', '188.70.14.87', 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.157 Safari/537.36', '', '2026-07-09 02:23:58'),
(219, 1, 'fluxa', 'Fluxa', 'home', 'open', '179.191.217.0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36', '', '2026-07-09 02:29:11'),
(220, 2, 'quotia', 'Quotia', 'tools', 'open', '78.101.187.240', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.79 Safari/537.36', '', '2026-07-10 02:17:25'),
(221, 1, 'fluxa', 'Fluxa', 'tools', 'open', '14.187.167.204', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_1_0) AppleWebKit/537.36 (KHTML, like Gecko) Brave Chrome/89.0.4389.72 Safari/537.36', '', '2026-07-10 02:22:33'),
(222, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.104', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-13 17:25:29'),
(223, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.30', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-13 17:25:33'),
(224, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.120', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-13 17:25:35'),
(225, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.217', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-13 17:25:41'),
(226, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.59', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-13 17:27:39'),
(227, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.43', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-13 17:27:42'),
(228, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.23', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-13 17:27:43'),
(229, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.36', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-13 17:27:46'),
(230, 4, 'solis', 'Solis', 'home', 'open', '204.8.96.101', '\"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36\"', '', '2026-07-17 14:16:08'),
(231, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.54', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-20 17:14:59'),
(232, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.109', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-20 17:15:03'),
(233, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.198', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-20 17:15:05'),
(234, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.156', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-20 17:15:11'),
(235, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.65', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-20 17:17:11'),
(236, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.191', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-20 17:17:16'),
(237, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.97', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-20 17:17:28'),
(238, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.231', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-20 17:17:45'),
(239, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.182', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-20 17:19:40'),
(240, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.73', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-20 17:19:42'),
(241, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.221', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-20 17:19:44'),
(242, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.60', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-20 17:19:45'),
(243, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.246', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-20 17:20:57'),
(244, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.185', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-20 17:20:59'),
(245, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.170', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-20 17:21:01'),
(246, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.1', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-20 17:21:03'),
(247, 4, 'solis', 'Solis', 'home', 'open', '103.166.100.88', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'https://davidalmeida.xyz/catalog/', '2026-07-24 11:47:13'),
(248, 4, 'solis', 'Solis', 'home', 'open', '158.140.171.110', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'https://davidalmeida.xyz/catalog/', '2026-07-24 11:47:27'),
(249, 4, 'solis', 'Solis', 'home', 'open', '163.227.217.28', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'https://davidalmeida.xyz/catalog/', '2026-07-24 11:47:39'),
(250, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.73', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-27 17:12:24'),
(251, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.0', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-27 17:12:28'),
(252, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.229', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-27 17:12:30'),
(253, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.243', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-27 17:12:36'),
(254, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.47', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-27 17:14:36'),
(255, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.45', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-27 17:14:40');
INSERT INTO `external_resource_clicks` (`id`, `resource_id`, `resource_slug`, `resource_name`, `source_page`, `action_type`, `ip_address`, `user_agent`, `referer`, `created_at`) VALUES
(256, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.18', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-27 17:14:52'),
(257, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.22', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-27 17:15:10'),
(258, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.177', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-27 17:17:04'),
(259, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.194', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-27 17:17:06'),
(260, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.25', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-27 17:17:08'),
(261, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.16', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-27 17:17:10'),
(262, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.39', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-27 17:18:22'),
(263, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.157', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-27 17:18:24'),
(264, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.162', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-27 17:18:26'),
(265, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.45', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-07-27 17:18:28'),
(266, 1, 'fluxa', 'Fluxa', 'home', 'open', '47.82.50.194', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '', '2026-08-01 17:24:52'),
(267, 3, 'vertex', 'Vertex', 'home', 'open', '38.17.157.140', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '', '2026-08-01 17:57:25'),
(268, 3, 'vertex', 'Vertex', 'home', 'open', '103.129.238.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '', '2026-08-01 17:58:20'),
(269, 1, 'fluxa', 'Fluxa', 'home', 'open', '79.117.225.170', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '', '2026-08-01 18:11:55'),
(270, 1, 'fluxa', 'Fluxa', 'home', 'open', '83.195.121.212', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '', '2026-08-01 18:12:42'),
(271, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.239', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-03 17:23:23'),
(272, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.167', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-03 17:23:27'),
(273, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.3', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-03 17:23:30'),
(274, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.222', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-03 17:23:35'),
(275, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.157', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-03 17:25:50'),
(276, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.205', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-03 17:25:53'),
(277, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.184', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-03 17:26:05'),
(278, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.44', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-03 17:26:23'),
(279, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.139', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-03 17:28:17'),
(280, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.106', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-03 17:28:19'),
(281, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.121', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-03 17:28:21'),
(282, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.187', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-03 17:28:24'),
(283, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.113', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-03 17:29:36'),
(284, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.18', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-03 17:29:38'),
(285, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.9', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-03 17:29:40'),
(286, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.86', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-03 17:29:42'),
(287, 3, 'vertex', 'Vertex', 'home', 'open', '51.79.99.31', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/109.0', '', '2026-08-08 12:18:56'),
(288, 1, 'fluxa', 'Fluxa', 'home', 'open', '51.79.99.31', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/109.0', '', '2026-08-08 12:19:13'),
(289, 4, 'solis', 'Solis', 'home', 'open', '51.79.99.31', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/109.0', '', '2026-08-08 12:19:14'),
(290, 2, 'quotia', 'Quotia', 'home', 'open', '51.79.99.31', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/109.0', '', '2026-08-08 12:19:32'),
(291, 2, 'quotia', 'Quotia', 'tools', 'open', '51.79.99.31', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/109.0', '', '2026-08-08 12:20:42'),
(292, 3, 'vertex', 'Vertex', 'tools', 'open', '51.79.99.31', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/109.0', '', '2026-08-08 12:20:43'),
(293, 1, 'fluxa', 'Fluxa', 'tools', 'open', '51.79.99.31', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/109.0', '', '2026-08-08 12:20:44'),
(294, 4, 'solis', 'Solis', 'tools', 'open', '51.79.99.31', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/109.0', '', '2026-08-08 12:20:45'),
(295, 1, 'fluxa', 'Fluxa', 'tools', 'open', '2602:fa5d:1::ed', 'Mozilla/5.0 (Linux; U; Android 4.4.2; en-us; LG-V410/V41010d Build/KOT49I.V41010d) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/30.0.1599.103 Safari/537.36', '', '2026-08-09 03:57:10'),
(296, 1, 'fluxa', 'Fluxa', 'tools', 'open', '2602:fa5d:1::ed', 'Mozilla/5.0 (Linux; U; Android 4.4.2; en-us; LG-V410/V41010d Build/KOT49I.V41010d) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/30.0.1599.103 Safari/537.36', '', '2026-08-09 03:57:11'),
(297, 2, 'quotia', 'Quotia', 'tools', 'open', '2602:fa5d:1::ed', 'Mozilla/5.0 (Linux; U; Android 4.4.2; en-us; LG-V410/V41010d Build/KOT49I.V41010d) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/30.0.1599.103 Safari/537.36', '', '2026-08-09 03:57:12'),
(298, 3, 'vertex', 'Vertex', 'tools', 'open', '2602:fa5d:1::ed', 'Mozilla/5.0 (Linux; U; Android 4.4.2; en-us; LG-V410/V41010d Build/KOT49I.V41010d) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/30.0.1599.103 Safari/537.36', '', '2026-08-09 03:57:13'),
(299, 3, 'vertex', 'Vertex', 'tools', 'open', '2602:fa5d:1::ed', 'Mozilla/5.0 (Linux; U; Android 4.4.2; en-us; LG-V410/V41010d Build/KOT49I.V41010d) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/30.0.1599.103 Safari/537.36', '', '2026-08-09 03:57:13'),
(300, 4, 'solis', 'Solis', 'tools', 'open', '2602:fa5d:1::ed', 'Mozilla/5.0 (Linux; U; Android 4.4.2; en-us; LG-V410/V41010d Build/KOT49I.V41010d) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/30.0.1599.103 Safari/537.36', '', '2026-08-09 03:57:14'),
(301, 4, 'solis', 'Solis', 'tools', 'open', '192.210.150.199', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_5) AppleWebKit/600.2.5 (KHTML, like Gecko) Version/7.1.2 Safari/537.85.11', '', '2026-08-09 03:57:20'),
(302, 2, 'quotia', 'Quotia', 'tools', 'open', '198.12.69.93', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.6; rv:36.0) Gecko/20100101 Firefox/36.0', '', '2026-08-09 03:57:23'),
(303, 4, 'solis', 'Solis', 'home', 'open', '172.121.218.211', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', '', '2026-08-10 14:27:21'),
(304, 3, 'vertex', 'Vertex', 'home', 'open', '172.121.218.211', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', '', '2026-08-10 14:27:25'),
(305, 2, 'quotia', 'Quotia', 'home', 'open', '172.121.218.211', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', '', '2026-08-10 14:27:29'),
(306, 4, 'solis', 'Solis', 'tools', 'open', '172.121.218.211', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', '', '2026-08-10 14:27:34'),
(307, 1, 'fluxa', 'Fluxa', 'tools', 'open', '172.121.218.211', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', '', '2026-08-10 14:27:35'),
(308, 2, 'quotia', 'Quotia', 'tools', 'open', '172.121.218.211', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', '', '2026-08-10 14:27:38'),
(309, 3, 'vertex', 'Vertex', 'tools', 'open', '172.121.218.211', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', '', '2026-08-10 14:27:41'),
(310, 2, 'quotia', 'Quotia', 'home', 'open', '103.105.167.180', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/121.0', '', '2026-08-10 14:27:48'),
(311, 3, 'vertex', 'Vertex', 'home', 'open', '103.105.167.180', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/121.0', '', '2026-08-10 14:27:49'),
(312, 1, 'fluxa', 'Fluxa', 'home', 'open', '103.105.167.180', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/121.0', '', '2026-08-10 14:27:49'),
(313, 4, 'solis', 'Solis', 'tools', 'open', '103.105.167.180', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/121.0', '', '2026-08-10 14:27:53'),
(314, 1, 'fluxa', 'Fluxa', 'tools', 'open', '103.105.167.180', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/121.0', '', '2026-08-10 14:27:53'),
(315, 3, 'vertex', 'Vertex', 'tools', 'open', '103.105.167.180', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/121.0', '', '2026-08-10 14:27:54'),
(316, 2, 'quotia', 'Quotia', 'tools', 'open', '103.105.167.180', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/121.0', '', '2026-08-10 14:27:54'),
(317, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.25', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-10 17:06:40'),
(318, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.51', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-10 17:06:45'),
(319, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.114', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-10 17:06:46'),
(320, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.7', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-10 17:06:53'),
(321, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.214', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-10 17:08:52'),
(322, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.176', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-10 17:08:57'),
(323, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.20', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-10 17:09:08'),
(324, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.106', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-10 17:09:26'),
(325, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.138', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-10 17:11:20'),
(326, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.197', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-10 17:11:22'),
(327, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.187', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-10 17:11:24'),
(328, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.108', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-10 17:11:26'),
(329, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.57', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-10 17:12:39'),
(330, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.75', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-10 17:12:41'),
(331, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.214', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-10 17:12:42'),
(332, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.46', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-10 17:12:44'),
(333, 1, 'fluxa', 'Fluxa', 'home', 'open', '187.156.85.113', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'https://davidalmeida.xyz', '2026-08-13 04:25:00'),
(334, 1, 'fluxa', 'Fluxa', 'home', 'open', '14.235.145.42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'https://davidalmeida.xyz', '2026-08-13 04:25:37'),
(335, 3, 'vertex', 'Vertex', 'home', 'open', '41.69.67.209', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'https://davidalmeida.xyz', '2026-08-13 04:35:53'),
(336, 3, 'vertex', 'Vertex', 'home', 'open', '123.21.91.178', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'https://davidalmeida.xyz', '2026-08-13 04:36:00'),
(337, 2, 'quotia', 'Quotia', 'home', 'open', '2a12:f541:21c:9ce7:a2f8:c5ca:92c8:4a74', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'https://davidalmeida.xyz', '2026-08-13 06:35:49'),
(338, 1, 'fluxa', 'Fluxa', 'tools', 'open', '186.53.171.67', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'https://davidalmeida.xyz', '2026-08-13 22:54:43'),
(339, 1, 'fluxa', 'Fluxa', 'tools', 'open', '119.235.88.17', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'https://davidalmeida.xyz', '2026-08-13 22:55:03'),
(340, 3, 'vertex', 'Vertex', 'tools', 'open', '2a10:4a01:43c9:5a99:8a0d:a11b:e317:bbec', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'https://davidalmeida.xyz', '2026-08-14 03:00:32'),
(341, 4, 'solis', 'Solis', 'tools', 'open', '99.245.170.203', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'https://davidalmeida.xyz', '2026-08-14 06:15:53'),
(342, 4, 'solis', 'Solis', 'tools', 'open', '2402:a00:173:73e4:81d7:e3b6:7408:fd43', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'https://davidalmeida.xyz', '2026-08-14 06:16:14'),
(343, 4, 'solis', 'Solis', 'tools', 'open', '213.172.71.4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'https://davidalmeida.xyz', '2026-08-14 06:16:24'),
(344, 2, 'quotia', 'Quotia', 'tools', 'open', '160.187.56.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', 'https://davidalmeida.xyz', '2026-08-14 06:17:32'),
(345, 3, 'vertex', 'Vertex', 'home', 'open', '180.149.232.227', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', 'https://davidalmeida.xyz', '2026-08-16 03:24:06'),
(346, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.88', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-17 17:09:48'),
(347, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.91', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-17 17:09:52'),
(348, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.193', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-17 17:09:53'),
(349, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.119', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-17 17:09:59'),
(350, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.32', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-17 17:12:00'),
(351, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.94', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-17 17:12:03'),
(352, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.54', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-17 17:12:15'),
(353, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.211', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-17 17:12:33'),
(354, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.191', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-17 17:14:27'),
(355, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.135', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-17 17:14:29'),
(356, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.166', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-17 17:14:32'),
(357, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.55', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-17 17:14:33'),
(358, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.127', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-17 17:15:46'),
(359, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.136', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-17 17:15:48'),
(360, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.0', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-17 17:15:50'),
(361, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.6', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-17 17:15:52'),
(362, 2, 'quotia', 'Quotia', 'home', 'open', '20.114.128.114', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36', 'https://davidalmeida.xyz/catalog/', '2026-08-21 08:03:30'),
(363, 1, 'fluxa', 'Fluxa', 'home', 'open', '20.114.128.114', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36', 'https://davidalmeida.xyz/catalog/', '2026-08-21 08:03:30'),
(364, 3, 'vertex', 'Vertex', 'home', 'open', '20.114.128.114', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36', 'https://davidalmeida.xyz/catalog/', '2026-08-21 08:03:30'),
(365, 4, 'solis', 'Solis', 'home', 'open', '20.114.128.114', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36', 'https://davidalmeida.xyz/catalog/', '2026-08-21 08:03:30'),
(366, 1, 'fluxa', 'Fluxa', 'tools', 'open', '2a02:c207:3018:3703::1', 'Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)', '', '2026-08-21 12:07:22'),
(367, 2, 'quotia', 'Quotia', 'tools', 'open', '2a02:c207:3018:3703::1', 'Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)', '', '2026-08-21 12:07:30'),
(368, 1, 'fluxa', 'Fluxa', 'tools', 'open', '2001:470:36:65:d99e:e07b:4a6d:5217', 'Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)', '', '2026-08-21 13:05:26'),
(369, 2, 'quotia', 'Quotia', 'tools', 'open', '2001:470:36:65:d99e:e07b:4a6d:5217', 'Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)', '', '2026-08-21 13:12:37'),
(370, 2, 'quotia', 'Quotia', 'home', 'open', '216.73.217.131', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-23 01:14:32'),
(371, 3, 'vertex', 'Vertex', 'home', 'open', '216.73.217.131', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-23 01:14:34'),
(372, 4, 'solis', 'Solis', 'home', 'open', '216.73.217.131', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-23 01:14:34'),
(373, 1, 'fluxa', 'Fluxa', 'home', 'open', '216.73.217.131', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-23 01:14:39'),
(374, 3, 'vertex', 'Vertex', 'tools', 'open', '216.73.217.131', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-23 01:22:46'),
(375, 1, 'fluxa', 'Fluxa', 'tools', 'open', '216.73.217.131', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-23 01:22:58'),
(376, 4, 'solis', 'Solis', 'tools', 'open', '216.73.217.131', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-23 01:23:24'),
(377, 2, 'quotia', 'Quotia', 'tools', 'open', '216.73.217.131', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-23 01:24:32'),
(378, 3, 'vertex', 'Vertex', 'tools', 'open', '186.188.37.112', 'Mozilla/5.0 (Linux; Android 15; OPPO PCLM5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.5170.136 Mobile Safari/537.36 MicroMessenger/8.0.43(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/catalog/tools', '2026-08-23 03:03:34'),
(379, 3, 'vertex', 'Vertex', 'home', 'open', '179.189.92.188', 'Mozilla/5.0 (Linux; Android 14; OPPO PCLM1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.5235.43 Mobile Safari/537.36', 'http://davidalmeida.xyz/', '2026-08-23 11:57:03'),
(380, 1, 'fluxa', 'Fluxa', 'home', 'open', '45.183.119.170', 'Mozilla/5.0 (Linux; Android 15; M2102J28) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.5615.138 Mobile Safari/537.36 MicroMessenger/8.0.46(0x18001B3F) NetType/WIFI Language/zh_CN', 'http://davidalmeida.xyz/', '2026-08-23 20:50:34'),
(381, 1, 'fluxa', 'Fluxa', 'tools', 'open', '152.156.125.248', 'Mozilla/5.0 (Linux; Android 15; M2102J21) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.5058.71 Mobile Safari/537.36 MicroMessenger/8.0.35(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/catalog/tools', '2026-08-24 00:10:31'),
(382, 4, 'solis', 'Solis', 'tools', 'open', '216.73.217.131', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-24 03:12:30'),
(383, 3, 'vertex', 'Vertex', 'tools', 'open', '216.73.217.131', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-24 03:12:30'),
(384, 4, 'solis', 'Solis', 'home', 'open', '216.73.217.131', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-24 03:12:47'),
(385, 3, 'vertex', 'Vertex', 'home', 'open', '216.73.217.131', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-24 03:13:11'),
(386, 1, 'fluxa', 'Fluxa', 'tools', 'open', '216.73.217.131', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-24 03:13:54'),
(387, 1, 'fluxa', 'Fluxa', 'home', 'open', '216.73.217.131', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-24 03:15:15'),
(388, 2, 'quotia', 'Quotia', 'home', 'open', '216.73.217.131', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-24 03:15:15'),
(389, 2, 'quotia', 'Quotia', 'tools', 'open', '216.73.217.131', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-24 03:15:15'),
(390, 4, 'solis', 'Solis', 'tools', 'open', '116.68.170.155', 'Mozilla/5.0 (Linux; Android 12; OPPO PCLM5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.5569.47 Mobile Safari/537.36 MicroMessenger/8.0.36(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/catalog/tools', '2026-08-24 13:08:03'),
(391, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.226', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-24 17:30:05'),
(392, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.139', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-24 17:30:09'),
(393, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.73', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-24 17:30:11'),
(394, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.199', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-24 17:30:17'),
(395, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.59', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-24 17:32:17'),
(396, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.131', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-24 17:32:22'),
(397, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.93', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-24 17:32:34'),
(398, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.112', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-24 17:32:52'),
(399, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.10', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-24 17:34:46'),
(400, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.126', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-24 17:34:48'),
(401, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.135', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-24 17:34:49'),
(402, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.153', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-24 17:34:51'),
(403, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.43', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-24 17:36:04'),
(404, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.96', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-24 17:36:06'),
(405, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.145', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-24 17:36:07'),
(406, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.102', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-24 17:36:09'),
(407, 1, 'fluxa', 'Fluxa', 'home', 'open', '190.140.112.171', 'Mozilla/5.0 (Linux; Android 12; M2102J28) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.5349.38 Mobile Safari/537.36 MicroMessenger/8.0.42(0x18001B3F) NetType/WIFI Language/zh_CN', 'http://davidalmeida.xyz/', '2026-08-24 20:54:45'),
(408, 2, 'quotia', 'Quotia', 'home', 'open', '103.196.9.194', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', '', '2026-08-25 05:16:40'),
(409, 1, 'fluxa', 'Fluxa', 'home', 'open', '103.196.9.194', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', '', '2026-08-25 05:16:40'),
(410, 4, 'solis', 'Solis', 'home', 'open', '103.196.9.194', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', '', '2026-08-25 05:16:41'),
(411, 3, 'vertex', 'Vertex', 'home', 'open', '103.196.9.194', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', '', '2026-08-25 05:16:41'),
(412, 1, 'fluxa', 'Fluxa', 'tools', 'open', '154.28.229.230', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', '', '2026-08-27 10:37:05'),
(413, 3, 'vertex', 'Vertex', 'home', 'open', '154.28.229.230', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', '', '2026-08-27 10:37:06'),
(414, 3, 'vertex', 'Vertex', 'tools', 'open', '154.28.229.230', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', '', '2026-08-27 10:37:06'),
(415, 2, 'quotia', 'Quotia', 'home', 'open', '154.28.229.230', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', '', '2026-08-27 10:37:06'),
(416, 1, 'fluxa', 'Fluxa', 'home', 'open', '154.28.229.230', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', '', '2026-08-27 10:37:07'),
(417, 4, 'solis', 'Solis', 'home', 'open', '154.28.229.230', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', '', '2026-08-27 10:37:07'),
(418, 4, 'solis', 'Solis', 'tools', 'open', '154.28.229.230', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', '', '2026-08-27 10:37:07'),
(419, 2, 'quotia', 'Quotia', 'tools', 'open', '154.28.229.230', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', '', '2026-08-27 10:37:07'),
(420, 3, 'vertex', 'Vertex', 'home', 'open', '13.216.243.105', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.7778.0 Safari/537.36', '', '2026-08-27 22:10:51'),
(421, 2, 'quotia', 'Quotia', 'home', 'open', '216.73.216.146', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-28 13:00:07'),
(422, 4, 'solis', 'Solis', 'home', 'open', '216.73.216.146', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-28 13:00:41'),
(423, 3, 'vertex', 'Vertex', 'home', 'open', '216.73.216.146', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-28 13:00:41'),
(424, 1, 'fluxa', 'Fluxa', 'home', 'open', '216.73.216.146', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-28 13:00:48'),
(425, 3, 'vertex', 'Vertex', 'home', 'open', '136.118.139.250', 'Mozilla/5.0 (Linux; Android 12; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36', '', '2026-08-28 13:13:34'),
(426, 4, 'solis', 'Solis', 'home', 'open', '136.118.139.250', 'Mozilla/5.0 (Linux; Android 12; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36', '', '2026-08-28 13:13:35'),
(427, 1, 'fluxa', 'Fluxa', 'home', 'open', '136.118.139.250', 'Mozilla/5.0 (Linux; Android 12; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36', '', '2026-08-28 13:13:35'),
(428, 2, 'quotia', 'Quotia', 'home', 'open', '136.118.139.250', 'Mozilla/5.0 (Linux; Android 12; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36', '', '2026-08-28 13:13:36'),
(429, 2, 'quotia', 'Quotia', 'home', 'open', '8.235.18.157', 'Mozilla/5.0 (Linux; Android 12; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36', '', '2026-08-28 13:18:28'),
(430, 4, 'solis', 'Solis', 'home', 'open', '8.235.18.157', 'Mozilla/5.0 (Linux; Android 12; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36', '', '2026-08-28 13:18:28'),
(431, 1, 'fluxa', 'Fluxa', 'home', 'open', '8.235.18.157', 'Mozilla/5.0 (Linux; Android 12; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36', '', '2026-08-28 13:18:28'),
(432, 3, 'vertex', 'Vertex', 'home', 'open', '8.235.18.157', 'Mozilla/5.0 (Linux; Android 12; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36', '', '2026-08-28 13:18:28'),
(433, 3, 'vertex', 'Vertex', 'tools', 'open', '216.73.216.75', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-28 13:33:12'),
(434, 2, 'quotia', 'Quotia', 'tools', 'open', '216.73.216.75', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-28 13:33:13'),
(435, 1, 'fluxa', 'Fluxa', 'tools', 'open', '216.73.216.75', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-28 13:33:29'),
(436, 4, 'solis', 'Solis', 'tools', 'open', '216.73.216.75', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-28 13:33:30'),
(437, 1, 'fluxa', 'Fluxa', 'home', 'open', '3.84.69.148', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.7778.0 Safari/537.36', '', '2026-08-29 06:59:19'),
(438, 1, 'fluxa', 'Fluxa', 'tools', 'open', '182.255.40.167', 'Mozilla/5.0 (Linux; Android 13; V2184) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.5183.126 Mobile Safari/537.36 MicroMessenger/8.0.31(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/catalog/tools', '2026-08-29 12:16:29'),
(439, 1, 'fluxa', 'Fluxa', 'tools', 'open', '216.73.216.149', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-29 14:50:41'),
(440, 4, 'solis', 'Solis', 'tools', 'open', '216.73.216.149', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-29 14:50:58'),
(441, 3, 'vertex', 'Vertex', 'home', 'open', '216.73.216.149', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-29 14:51:06'),
(442, 2, 'quotia', 'Quotia', 'home', 'open', '216.73.216.149', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-29 14:51:56'),
(443, 4, 'solis', 'Solis', 'home', 'open', '216.73.216.149', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-29 14:51:57'),
(444, 2, 'quotia', 'Quotia', 'tools', 'open', '216.73.216.149', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-29 14:52:20'),
(445, 3, 'vertex', 'Vertex', 'tools', 'open', '216.73.216.149', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-29 14:52:48'),
(446, 1, 'fluxa', 'Fluxa', 'home', 'open', '216.73.216.149', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-08-29 14:52:49'),
(447, 4, 'solis', 'Solis', 'tools', 'open', '46.176.27.78', 'Mozilla/5.0 (Linux; Android 14; OPPO PCLM5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.5427.120 Mobile Safari/537.36 MicroMessenger/8.0.31(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/catalog/tools', '2026-08-29 19:24:46'),
(448, 3, 'vertex', 'Vertex', 'tools', 'open', '170.83.18.122', 'Mozilla/5.0 (Linux; Android 13; V2186) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.5277.62 Mobile Safari/537.36', 'https://davidalmeida.xyz/catalog/tools', '2026-08-29 20:29:20'),
(449, 3, 'vertex', 'Vertex', 'home', 'open', '103.65.222.54', 'Mozilla/5.0 (Linux; Android 14; M2102J23) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.5186.153 Mobile Safari/537.36', 'https://davidalmeida.xyz/index.php?lang=es', '2026-08-30 09:24:52'),
(450, 1, 'fluxa', 'Fluxa', 'home', 'open', '2001:41d0:401:3000::2918', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '', '2026-08-30 22:45:23'),
(451, 2, 'quotia', 'Quotia', 'home', 'open', '2001:41d0:367:cad::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '', '2026-08-30 23:27:11'),
(452, 1, 'fluxa', 'Fluxa', 'tools', 'open', '2001:41d0:52:400::33d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '', '2026-08-31 00:15:42'),
(453, 4, 'solis', 'Solis', 'tools', 'open', '2001:41d0:601:1100::5237', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '', '2026-08-31 00:15:55'),
(454, 3, 'vertex', 'Vertex', 'tools', 'open', '2001:41d0:305:2100::5f38', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '', '2026-08-31 00:15:56'),
(455, 2, 'quotia', 'Quotia', 'home', 'open', '200.2.163.40', 'Mozilla/5.0 (Linux; Android 13; V2185) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.5691.92 Mobile Safari/537.36 MicroMessenger/8.0.46(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/catalog/', '2026-08-31 15:34:21'),
(456, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.132', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-31 17:32:22'),
(457, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.63', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-31 17:32:26'),
(458, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.55', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-31 17:32:28'),
(459, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.23', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-31 17:32:34'),
(460, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.196', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-31 17:34:34'),
(461, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.126', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-31 17:34:38'),
(462, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.49', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-31 17:34:50'),
(463, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.172', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-31 17:35:08'),
(464, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.207', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-31 17:37:02'),
(465, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.107', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-31 17:37:04'),
(466, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.160', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-31 17:37:06'),
(467, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.108', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-31 17:37:08'),
(468, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.80', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-31 17:38:20'),
(469, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.212', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-31 17:38:22'),
(470, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.170', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-31 17:38:24'),
(471, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.143', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-08-31 17:38:26'),
(472, 1, 'fluxa', 'Fluxa', 'home', 'open', '2a0b:6580::76', 'Mozilla/5.0 (compatible; intelx.io_bot +https://intelx.io)', '', '2026-08-31 23:58:19'),
(473, 2, 'quotia', 'Quotia', 'home', 'open', '2a0b:6580::76', 'Mozilla/5.0 (compatible; intelx.io_bot +https://intelx.io)', '', '2026-08-31 23:58:19'),
(474, 3, 'vertex', 'Vertex', 'home', 'open', '2a0b:6580::76', 'Mozilla/5.0 (compatible; intelx.io_bot +https://intelx.io)', '', '2026-08-31 23:58:19'),
(475, 4, 'solis', 'Solis', 'home', 'open', '2a0b:6580::76', 'Mozilla/5.0 (compatible; intelx.io_bot +https://intelx.io)', '', '2026-08-31 23:58:20'),
(476, 1, 'fluxa', 'Fluxa', 'tools', 'open', '2a0b:6580::76', 'Mozilla/5.0 (compatible; intelx.io_bot +https://intelx.io)', '', '2026-08-31 23:58:23'),
(477, 4, 'solis', 'Solis', 'home', 'open', '137.184.44.69', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'https://www.google.com/search?q=davidalmeida', '2026-09-03 04:29:08'),
(478, 3, 'vertex', 'Vertex', 'home', 'open', '40.77.167.136', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm) Chrome/116.0.1938.76 Safari/537.36', '', '2026-09-03 13:11:53'),
(479, 3, 'vertex', 'Vertex', 'home', 'open', '209.38.137.21', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'https://www.google.com/search?q=davidalmeida', '2026-09-03 18:09:18'),
(480, 2, 'quotia', 'Quotia', 'home', 'open', '164.92.100.164', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://www.google.com/search?q=davidalmeida', '2026-09-03 18:41:10'),
(481, 4, 'solis', 'Solis', 'tools', 'open', '144.76.67.108', 'serpstatbot/2.1 (advanced backlink tracking bot; https://serpstatbot.com/; abuse@serpstatbot.com)', '', '2026-09-04 09:26:27'),
(482, 4, 'solis', 'Solis', 'home', 'open', '144.76.67.108', 'serpstatbot/2.1 (advanced backlink tracking bot; https://serpstatbot.com/; abuse@serpstatbot.com)', '', '2026-09-04 09:26:52'),
(483, 3, 'vertex', 'Vertex', 'tools', 'open', '144.76.67.108', 'serpstatbot/2.1 (advanced backlink tracking bot; https://serpstatbot.com/; abuse@serpstatbot.com)', '', '2026-09-04 09:27:15'),
(484, 3, 'vertex', 'Vertex', 'home', 'open', '144.76.67.108', 'serpstatbot/2.1 (advanced backlink tracking bot; https://serpstatbot.com/; abuse@serpstatbot.com)', '', '2026-09-04 09:27:42'),
(485, 2, 'quotia', 'Quotia', 'tools', 'open', '144.76.67.108', 'serpstatbot/2.1 (advanced backlink tracking bot; https://serpstatbot.com/; abuse@serpstatbot.com)', '', '2026-09-04 09:28:03'),
(486, 2, 'quotia', 'Quotia', 'home', 'open', '144.76.67.108', 'serpstatbot/2.1 (advanced backlink tracking bot; https://serpstatbot.com/; abuse@serpstatbot.com)', '', '2026-09-04 09:28:26'),
(487, 1, 'fluxa', 'Fluxa', 'tools', 'open', '144.76.67.108', 'serpstatbot/2.1 (advanced backlink tracking bot; https://serpstatbot.com/; abuse@serpstatbot.com)', '', '2026-09-04 09:28:50'),
(488, 1, 'fluxa', 'Fluxa', 'home', 'open', '144.76.67.108', 'serpstatbot/2.1 (advanced backlink tracking bot; https://serpstatbot.com/; abuse@serpstatbot.com)', '', '2026-09-04 09:29:08'),
(489, 1, 'fluxa', 'Fluxa', 'home', 'open', '209.101.255.122', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; SEOJuice-SearchBot/1.0; +https://seojuice.io/bot', '', '2026-09-05 11:36:34'),
(490, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.4', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-07 17:31:41'),
(491, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.13', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-07 17:31:45'),
(492, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.135', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-07 17:31:47'),
(493, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.52', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-07 17:31:53'),
(494, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.128', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-07 17:33:53'),
(495, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.129', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-07 17:33:57'),
(496, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.244', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-07 17:34:09'),
(497, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.211', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-07 17:34:27'),
(498, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.94', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-07 17:36:21'),
(499, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.140', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-07 17:36:23'),
(500, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.173', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-07 17:36:25'),
(501, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.32', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-07 17:36:27'),
(502, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.40', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-07 17:37:39'),
(503, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.124', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-07 17:37:41'),
(504, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.93', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-07 17:37:43'),
(505, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.6', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-07 17:37:45'),
(506, 3, 'vertex', 'Vertex', 'home', 'open', '202.131.229.251', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://davidalmeida.xyz/?lang=pt', '2026-09-11 20:53:38'),
(507, 1, 'fluxa', 'Fluxa', 'home', 'open', '144.217.135.220', 'Mozilla/5.0 (compatible; Dataprovider.com)', '', '2026-09-12 02:10:10'),
(508, 2, 'quotia', 'Quotia', 'home', 'open', '144.217.135.220', 'Mozilla/5.0 (compatible; Dataprovider.com)', '', '2026-09-12 02:10:11'),
(509, 3, 'vertex', 'Vertex', 'home', 'open', '144.217.135.220', 'Mozilla/5.0 (compatible; Dataprovider.com)', '', '2026-09-12 02:10:12'),
(510, 4, 'solis', 'Solis', 'home', 'open', '144.217.135.220', 'Mozilla/5.0 (compatible; Dataprovider.com)', '', '2026-09-12 02:10:13');
INSERT INTO `external_resource_clicks` (`id`, `resource_id`, `resource_slug`, `resource_name`, `source_page`, `action_type`, `ip_address`, `user_agent`, `referer`, `created_at`) VALUES
(511, 4, 'solis', 'Solis', 'home', 'open', '201.87.1.152', 'Mozilla/5.0 (Linux; Android 15; OPPO PCLM1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.5345.127 Mobile Safari/537.36 MicroMessenger/8.0.30(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/?lang=pt', '2026-09-12 17:38:56'),
(512, 2, 'quotia', 'Quotia', 'home', 'open', '190.2.221.121', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.2 Mobile/15E148 Safari/604.1', 'https://davidalmeida.xyz/?lang=pt', '2026-09-12 19:30:20'),
(513, 3, 'vertex', 'Vertex', 'home', 'open', '104.154.87.242', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Reflectionbot/1.0; +https://reflection.ai/bot) Chrome/151.0.0.0 Safari/537.36', '', '2026-09-13 01:11:26'),
(514, 1, 'fluxa', 'Fluxa', 'home', 'open', '38.147.153.62', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1 MicroMessenger/8.0.40(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/?lang=en', '2026-09-13 15:01:30'),
(515, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.93', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-14 17:27:27'),
(516, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.202', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-14 17:27:31'),
(517, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.223', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-14 17:27:33'),
(518, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.18', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-14 17:27:39'),
(519, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.95', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-14 17:29:39'),
(520, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.213', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-14 17:29:43'),
(521, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.156', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-14 17:29:55'),
(522, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.123', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-14 17:30:13'),
(523, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.221', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-14 17:32:07'),
(524, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.242', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-14 17:32:09'),
(525, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.186', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-14 17:32:11'),
(526, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.51', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-14 17:32:13'),
(527, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.146', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-14 17:33:53'),
(528, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.135', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-14 17:33:56'),
(529, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.133', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-14 17:33:57'),
(530, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.45', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-14 17:34:00'),
(531, 3, 'vertex', 'Vertex', 'tools', 'open', '186.2.157.85', 'Mozilla/5.0 (Linux; Android 15; M2102J21) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.5798.8 Mobile Safari/537.36 MicroMessenger/8.0.34(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/catalog/tools', '2026-09-16 02:52:30'),
(532, 2, 'quotia', 'Quotia', 'tools', 'open', '112.202.100.93', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1 MicroMessenger/8.0.42(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/catalog/tools', '2026-09-16 08:20:30'),
(533, 4, 'solis', 'Solis', 'tools', 'open', '212.58.119.222', 'Mozilla/5.0 (Linux; Android 12; OPPO PCLM7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.5763.135 Mobile Safari/537.36 MicroMessenger/8.0.37(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/catalog/tools', '2026-09-16 22:23:33'),
(534, 3, 'vertex', 'Vertex', 'home', 'open', '102.203.41.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1', 'https://davidalmeida.xyz/catalog/?lang=es', '2026-09-16 23:46:48'),
(535, 2, 'quotia', 'Quotia', 'home', 'open', '16.216.88.191', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Reflectionbot/1.0; +https://reflection.ai/bot) Chrome/151.0.0.0 Safari/537.36', '', '2026-09-19 06:39:57'),
(536, 2, 'quotia', 'Quotia', 'home', 'open', '106.219.66.220', 'Mozilla/5.0 (Linux; Android 12; V2186) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.5191.73 Mobile Safari/537.36', 'https://davidalmeida.xyz/index.php?lang=es', '2026-09-20 05:49:41'),
(537, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.108', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-21 17:13:34'),
(538, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.235', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-21 17:13:38'),
(539, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.203', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-21 17:13:40'),
(540, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.9', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-21 17:13:46'),
(541, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.25', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-21 17:15:46'),
(542, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.6', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-21 17:15:50'),
(543, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.190', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-21 17:16:02'),
(544, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.241', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-21 17:16:20'),
(545, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.18', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-21 17:18:14'),
(546, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.131', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-21 17:18:16'),
(547, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.3', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-21 17:18:18'),
(548, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.89', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-21 17:18:20'),
(549, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.180', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-21 17:19:32'),
(550, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.125', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-21 17:19:34'),
(551, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.125', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-21 17:19:36'),
(552, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.221', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-21 17:19:38'),
(553, 3, 'vertex', 'Vertex', 'tools', 'open', '177.93.15.30', 'Mozilla/5.0 (Linux; Android 12; M2102J28) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.5298.157 Mobile Safari/537.36', 'https://davidalmeida.xyz/catalog/tools', '2026-09-22 04:33:09'),
(554, 1, 'fluxa', 'Fluxa', 'tools', 'open', '103.176.88.145', 'Mozilla/5.0 (Linux; Android 14; V2185) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.5393.81 Mobile Safari/537.36 MicroMessenger/8.0.49(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/catalog/tools', '2026-09-22 13:25:07'),
(555, 1, 'fluxa', 'Fluxa', 'home', 'open', '139.177.156.181', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; SEOJuice-SearchBot/1.0; +https://seojuice.io/bot', '', '2026-09-23 03:45:30'),
(556, 4, 'solis', 'Solis', 'home', 'open', '102.207.169.42', 'Mozilla/5.0 (Linux; Android 12; M2102J22) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.5576.108 Mobile Safari/537.36 MicroMessenger/8.0.30(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/index.php?lang=pt', '2026-09-23 11:03:07'),
(557, 2, 'quotia', 'Quotia', 'tools', 'open', '201.7.31.37', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1', 'https://davidalmeida.xyz/catalog/tools', '2026-09-24 05:22:41'),
(558, 1, 'fluxa', 'Fluxa', 'home', 'open', '162.4.128.131', 'Mozilla/5.0 (Linux; Android 15; V2185) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.5112.10 Mobile Safari/537.36 MicroMessenger/8.0.41(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/catalog/?lang=es', '2026-09-25 13:27:50'),
(559, 2, 'quotia', 'Quotia', 'home', 'open', '14.102.123.50', 'Mozilla/5.0 (Linux; Android 15; V2186) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.5155.124 Mobile Safari/537.36', 'https://davidalmeida.xyz/catalog/?lang=es', '2026-09-26 04:45:54'),
(560, 1, 'fluxa', 'Fluxa', 'tools', 'open', '223.29.215.20', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.3 Mobile/15E148 Safari/604.1', 'https://davidalmeida.xyz/catalog/tools', '2026-09-27 14:39:39'),
(561, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.145', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-28 17:12:37'),
(562, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.179', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-28 17:12:41'),
(563, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.172', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-28 17:12:43'),
(564, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.1', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-28 17:12:49'),
(565, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.162', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-28 17:14:49'),
(566, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.152', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-28 17:14:53'),
(567, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.168', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-28 17:15:05'),
(568, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.107', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-28 17:15:23'),
(569, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.244', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-28 17:17:17'),
(570, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.117', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-28 17:17:19'),
(571, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.163', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-28 17:17:21'),
(572, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.26', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-28 17:17:23'),
(573, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.199', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-28 17:18:36'),
(574, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.100', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-28 17:18:38'),
(575, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.72', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-28 17:18:40'),
(576, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.99', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-09-28 17:18:42'),
(577, 4, 'solis', 'Solis', 'tools', 'open', '112.211.118.181', 'Mozilla/5.0 (Linux; Android 15; M2102J23) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.5168.129 Mobile Safari/537.36', 'https://davidalmeida.xyz/catalog/tools', '2026-09-29 05:27:18'),
(578, 4, 'solis', 'Solis', 'home', 'open', '103.151.46.26', 'Mozilla/5.0 (Linux; Android 14; OPPO PCLM8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.5103.44 Mobile Safari/537.36 MicroMessenger/8.0.45(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/?lang=es', '2026-09-29 11:51:57'),
(579, 2, 'quotia', 'Quotia', 'tools', 'open', '2.133.141.137', 'Mozilla/5.0 (Linux; Android 12; V2188) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.5306.8 Mobile Safari/537.36 MicroMessenger/8.0.31(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/catalog/tools', '2026-09-30 04:24:05'),
(580, 3, 'vertex', 'Vertex', 'home', 'open', '200.8.9.155', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1', 'https://davidalmeida.xyz/catalog/?lang=es', '2026-10-01 03:21:57'),
(581, 2, 'quotia', 'Quotia', 'home', 'open', '188.253.110.129', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0', '', '2026-10-01 05:25:16'),
(582, 1, 'fluxa', 'Fluxa', 'home', 'open', '188.253.110.129', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0', '', '2026-10-01 05:25:16'),
(583, 4, 'solis', 'Solis', 'home', 'open', '188.253.110.129', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0', '', '2026-10-01 05:25:16'),
(584, 3, 'vertex', 'Vertex', 'home', 'open', '188.253.110.129', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0', '', '2026-10-01 05:25:16'),
(585, 3, 'vertex', 'Vertex', 'home', 'open', '181.94.227.2', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '', '2026-10-01 23:50:59'),
(586, 2, 'quotia', 'Quotia', 'home', 'open', '38.248.132.51', 'Mozilla/5.0 (Linux; Android 13; M2102J23) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.5346.108 Mobile Safari/537.36 MicroMessenger/8.0.38(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/catalog/?lang=es', '2026-10-01 23:55:16'),
(587, 3, 'vertex', 'Vertex', 'home', 'open', '94.176.142.231', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; SEOJuice-SearchBot/1.0; +https://seojuice.io/bot', '', '2026-10-03 05:30:58'),
(588, 2, 'quotia', 'Quotia', 'home', 'open', '151.247.185.219', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; SEOJuice-SearchBot/1.0; +https://seojuice.io/bot', '', '2026-10-03 05:31:31'),
(589, 4, 'solis', 'Solis', 'home', 'open', '74.0.103.43', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; SEOJuice-SearchBot/1.0; +https://seojuice.io/bot', '', '2026-10-03 05:59:08'),
(590, 1, 'fluxa', 'Fluxa', 'tools', 'open', '154.3.62.15', 'Mozilla/5.0 (compatible; MixRankBot; crawler@mixrank.com)', '', '2026-10-04 05:00:52'),
(591, 2, 'quotia', 'Quotia', 'tools', 'open', '154.3.62.15', 'Mozilla/5.0 (compatible; MixRankBot; crawler@mixrank.com)', '', '2026-10-04 05:01:01'),
(592, 3, 'vertex', 'Vertex', 'tools', 'open', '154.3.62.15', 'Mozilla/5.0 (compatible; MixRankBot; crawler@mixrank.com)', '', '2026-10-04 05:01:14'),
(593, 3, 'vertex', 'Vertex', 'home', 'open', '171.22.217.146', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '', '2026-10-04 09:32:17'),
(594, 1, 'fluxa', 'Fluxa', 'home', 'open', '171.22.217.146', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '', '2026-10-04 09:32:17'),
(595, 4, 'solis', 'Solis', 'home', 'open', '171.22.217.146', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '', '2026-10-04 09:32:17'),
(596, 2, 'quotia', 'Quotia', 'home', 'open', '171.22.217.146', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '', '2026-10-04 09:32:17'),
(597, 3, 'vertex', 'Vertex', 'tools', 'open', '178.251.110.252', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1 MicroMessenger/8.0.44(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/catalog/tools', '2026-10-04 09:55:53'),
(598, 3, 'vertex', 'Vertex', 'home', 'open', '171.22.217.198', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '', '2026-10-04 11:14:43'),
(599, 2, 'quotia', 'Quotia', 'home', 'open', '171.22.217.198', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '', '2026-10-04 11:14:43'),
(600, 4, 'solis', 'Solis', 'home', 'open', '171.22.217.198', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '', '2026-10-04 11:14:43'),
(601, 1, 'fluxa', 'Fluxa', 'home', 'open', '171.22.217.198', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '', '2026-10-04 11:14:43'),
(602, 2, 'quotia', 'Quotia', 'home', 'open', '154.28.229.75', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', '', '2026-10-04 11:36:20'),
(603, 1, 'fluxa', 'Fluxa', 'home', 'open', '154.28.229.75', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', '', '2026-10-04 11:36:20'),
(604, 3, 'vertex', 'Vertex', 'home', 'open', '154.28.229.75', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', '', '2026-10-04 11:36:20'),
(605, 4, 'solis', 'Solis', 'home', 'open', '154.28.229.75', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', '', '2026-10-04 11:36:20'),
(606, 1, 'fluxa', 'Fluxa', 'tools', 'open', '194.124.37.235', 'Mozilla/5.0 (Linux; Android 14; OPPO PCLM0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.5798.89 Mobile Safari/537.36', 'https://davidalmeida.xyz/catalog/tools', '2026-10-04 13:53:43'),
(607, 4, 'solis', 'Solis', 'home', 'open', '201.4.68.200', 'Mozilla/5.0 (Linux; Android 12; V2180) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.5490.42 Mobile Safari/537.36', 'https://davidalmeida.xyz/?lang=en', '2026-10-05 05:27:24'),
(608, 4, 'solis', 'Solis', 'tools', 'open', '103.178.239.48', 'Mozilla/5.0 (Linux; Android 12; V2189) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.5789.116 Mobile Safari/537.36 MicroMessenger/8.0.39(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/catalog/tools', '2026-10-05 06:11:16'),
(609, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.132', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-05 17:22:37'),
(610, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.90', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-05 17:22:41'),
(611, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.245', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-05 17:22:43'),
(612, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.69', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-05 17:22:49'),
(613, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.179', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-05 17:24:49'),
(614, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.244', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-05 17:24:53'),
(615, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.211', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-05 17:25:05'),
(616, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.89', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-05 17:25:23'),
(617, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.125', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-05 17:27:17'),
(618, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.107', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-05 17:27:19'),
(619, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.180', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-05 17:27:21'),
(620, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.27', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-05 17:27:23'),
(621, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.104', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-05 17:28:35'),
(622, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.99', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-05 17:28:37'),
(623, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.37', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-05 17:28:39'),
(624, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.73', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-05 17:28:41'),
(625, 2, 'quotia', 'Quotia', 'home', 'open', '154.159.252.91', 'Mozilla/5.0 (Linux; Android 14; M2102J21) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.5813.104 Mobile Safari/537.36 MicroMessenger/8.0.36(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/catalog/?lang=es', '2026-10-07 14:48:58'),
(626, 1, 'fluxa', 'Fluxa', 'home', 'open', '136.162.194.200', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; SEOJuice-SearchBot/1.0; +https://seojuice.io/bot', '', '2026-10-08 01:53:01'),
(627, 2, 'quotia', 'Quotia', 'tools', 'open', '187.19.220.216', 'Mozilla/5.0 (Linux; Android 14; OPPO PCLM6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.5667.97 Mobile Safari/537.36 MicroMessenger/8.0.37(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/catalog/tools', '2026-10-08 11:56:57'),
(628, 3, 'vertex', 'Vertex', 'tools', 'open', '153.117.15.22', 'Mozilla/5.0 (Linux; Android 12; M2102J27) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.5230.116 Mobile Safari/537.36', 'https://davidalmeida.xyz/catalog/tools', '2026-10-09 13:34:56'),
(629, 1, 'fluxa', 'Fluxa', 'home', 'open', '149.56.150.2', 'Mozilla/5.0 (compatible; Dataprovider.com)', '', '2026-10-09 15:22:44'),
(630, 2, 'quotia', 'Quotia', 'home', 'open', '149.56.150.2', 'Mozilla/5.0 (compatible; Dataprovider.com)', '', '2026-10-09 15:22:45'),
(631, 3, 'vertex', 'Vertex', 'home', 'open', '149.56.150.2', 'Mozilla/5.0 (compatible; Dataprovider.com)', '', '2026-10-09 15:22:46'),
(632, 4, 'solis', 'Solis', 'home', 'open', '149.56.150.2', 'Mozilla/5.0 (compatible; Dataprovider.com)', '', '2026-10-09 15:22:47'),
(633, 4, 'solis', 'Solis', 'home', 'open', '106.205.170.24', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1 MicroMessenger/8.0.42(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/?lang=es', '2026-10-10 13:31:55'),
(634, 3, 'vertex', 'Vertex', 'home', 'open', '216.73.217.73', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-10-11 20:23:03'),
(635, 4, 'solis', 'Solis', 'home', 'open', '216.73.217.73', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-10-11 20:23:58'),
(636, 1, 'fluxa', 'Fluxa', 'home', 'open', '216.73.217.73', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-10-11 20:24:12'),
(637, 2, 'quotia', 'Quotia', 'home', 'open', '216.73.217.73', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-10-11 20:24:14'),
(638, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.18', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-12 17:27:17'),
(639, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.11', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-12 17:27:21'),
(640, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.242', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-12 17:27:23'),
(641, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.77', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-12 17:27:29'),
(642, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.77', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-12 17:29:29'),
(643, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.74', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-12 17:29:33'),
(644, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.105', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-12 17:29:45'),
(645, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.104', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-12 17:30:03'),
(646, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.138', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-12 17:31:57'),
(647, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.76', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-12 17:31:59'),
(648, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.88', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-12 17:32:01'),
(649, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.218', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-12 17:32:03'),
(650, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.175', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-12 17:33:15'),
(651, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.150', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-12 17:33:17'),
(652, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.49', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-12 17:33:19'),
(653, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.90', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-12 17:33:21'),
(654, 2, 'quotia', 'Quotia', 'home', 'open', '216.73.217.73', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-10-12 21:52:10'),
(655, 4, 'solis', 'Solis', 'home', 'open', '216.73.217.73', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-10-12 21:54:28'),
(656, 3, 'vertex', 'Vertex', 'home', 'open', '216.73.217.73', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-10-12 21:55:09'),
(657, 1, 'fluxa', 'Fluxa', 'home', 'open', '216.73.217.73', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', '', '2026-10-12 21:56:54'),
(658, 2, 'quotia', 'Quotia', 'home', 'open', '180.214.142.178', 'Mozilla/5.0 (Linux; Android 14; OPPO PCLM1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.5410.110 Mobile Safari/537.36', 'https://davidalmeida.xyz/index.php?lang=pt', '2026-10-13 09:41:15'),
(659, 2, 'quotia', 'Quotia', 'tools', 'open', '113.199.230.206', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1', 'https://davidalmeida.xyz/catalog/tools', '2026-10-14 09:55:29'),
(660, 1, 'fluxa', 'Fluxa', 'home', 'open', '154.70.46.3', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1 MicroMessenger/8.0.45(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/index.php', '2026-10-14 15:44:56'),
(661, 4, 'solis', 'Solis', 'home', 'open', '201.4.69.253', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'https://davidalmeida.xyz/index.php', '2026-10-16 01:50:57'),
(662, 3, 'vertex', 'Vertex', 'home', 'open', '34.105.27.177', 'Mozilla/5.0 (Linux; Android 12; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36', '', '2026-10-16 13:14:05'),
(663, 2, 'quotia', 'Quotia', 'home', 'open', '34.105.27.177', 'Mozilla/5.0 (Linux; Android 12; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36', '', '2026-10-16 13:14:05'),
(664, 4, 'solis', 'Solis', 'home', 'open', '34.105.27.177', 'Mozilla/5.0 (Linux; Android 12; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36', '', '2026-10-16 13:14:06'),
(665, 1, 'fluxa', 'Fluxa', 'home', 'open', '34.105.27.177', 'Mozilla/5.0 (Linux; Android 12; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36', '', '2026-10-16 13:14:07'),
(666, 1, 'fluxa', 'Fluxa', 'tools', 'open', '45.153.25.119', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.2 Mobile/15E148 Safari/604.1 MicroMessenger/8.0.48(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/catalog/tools', '2026-10-16 20:57:28'),
(667, 3, 'vertex', 'Vertex', 'tools', 'open', '88.253.127.27', 'Mozilla/5.0 (Linux; Android 13; M2102J23) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.5657.137 Mobile Safari/537.36', 'https://davidalmeida.xyz/catalog/tools', '2026-10-18 00:53:42'),
(668, 3, 'vertex', 'Vertex', 'home', 'open', '82.102.250.204', 'Mozilla/5.0 (Linux; Android 12; V2180) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.5788.94 Mobile Safari/537.36 MicroMessenger/8.0.36(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/index.php?lang=pt', '2026-10-18 09:18:08'),
(669, 1, 'fluxa', 'Fluxa', 'home', 'open', '43.156.209.141', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36', 'https://davidalmeida.xyz/catalog/tools/open?id=1&src=home', '2026-10-18 16:08:17'),
(670, 4, 'solis', 'Solis', 'tools', 'open', '200.55.244.82', 'Mozilla/5.0 (Linux; Android 12; OPPO PCLM2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.5036.109 Mobile Safari/537.36 MicroMessenger/8.0.30(0x18001B3F) NetType/WIFI Language/zh_CN', 'https://davidalmeida.xyz/catalog/tools', '2026-10-18 17:48:56'),
(671, 3, 'vertex', 'Vertex', 'home', 'open', '43.159.48.229', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '', '2026-10-19 04:42:59'),
(672, 2, 'quotia', 'Quotia', 'home', 'open', '190.80.50.132', 'Mozilla/5.0 (Linux; Android 14; OPPO PCLM3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.5786.158 Mobile Safari/537.36', 'http://davidalmeida.xyz/', '2026-10-19 10:32:48'),
(673, 4, 'solis', 'Solis', 'home', 'open', '159.138.86.153', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '', '2026-10-19 13:11:13'),
(674, 2, 'quotia', 'Quotia', 'home', 'open', '43.163.117.72', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '', '2026-10-19 14:35:47'),
(675, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.24', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-19 17:13:20'),
(676, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.98', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-19 17:13:24'),
(677, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.191', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-19 17:13:26'),
(678, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.145', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-19 17:13:32'),
(679, 1, 'fluxa', 'Fluxa', 'home', 'open', '168.100.149.71', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-19 17:15:32'),
(680, 2, 'quotia', 'Quotia', 'home', 'open', '168.100.149.68', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-19 17:15:36'),
(681, 3, 'vertex', 'Vertex', 'home', 'open', '168.100.149.201', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-19 17:15:48'),
(682, 4, 'solis', 'Solis', 'home', 'open', '168.100.149.34', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-19 17:16:06'),
(683, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.27', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-19 17:18:01'),
(684, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.160', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-19 17:18:03'),
(685, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.116', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-19 17:18:05'),
(686, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.203', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-19 17:18:07'),
(687, 4, 'solis', 'Solis', 'tools', 'open', '168.100.149.132', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-19 17:19:19'),
(688, 3, 'vertex', 'Vertex', 'tools', 'open', '168.100.149.149', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-19 17:19:21'),
(689, 1, 'fluxa', 'Fluxa', 'tools', 'open', '168.100.149.206', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-19 17:19:23'),
(690, 2, 'quotia', 'Quotia', 'tools', 'open', '168.100.149.163', 'Mozilla/5.0 (compatible; AhrefsSiteAudit/6.1; +http://ahrefs.com/robot/site-audit)', '', '2026-10-19 17:19:25');

-- --------------------------------------------------------

--
-- Estrutura para tabela `portfolio_items`
--

CREATE TABLE `portfolio_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(80) NOT NULL DEFAULT 'general',
  `description` text NOT NULL,
  `project_url` varchar(500) NOT NULL DEFAULT '',
  `image_url` varchar(500) NOT NULL DEFAULT '',
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` varchar(25) NOT NULL,
  `updated_at` varchar(25) NOT NULL,
  `title_en` varchar(255) NOT NULL DEFAULT '',
  `title_es` varchar(255) NOT NULL DEFAULT '',
  `description_en` text DEFAULT NULL,
  `description_es` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `site_settings`
--

CREATE TABLE `site_settings` (
  `setting_key` varchar(191) NOT NULL,
  `setting_value` longtext NOT NULL,
  `updated_at` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `site_settings`
--

INSERT INTO `site_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('security_last_cache_clear_at', '2026-08-31 00:44:14', '2026-08-31 00:44:14');

-- --------------------------------------------------------

--
-- Estrutura para tabela `student_accounts`
--

CREATE TABLE `student_accounts` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(190) NOT NULL DEFAULT '',
  `username` varchar(190) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL DEFAULT '',
  `notes` text NOT NULL,
  `can_access_excel` tinyint(1) NOT NULL DEFAULT 0,
  `can_access_cursos` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` varchar(25) NOT NULL DEFAULT '',
  `created_at` varchar(25) NOT NULL,
  `updated_at` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `student_accounts`
--

INSERT INTO `student_accounts` (`id`, `full_name`, `username`, `email`, `password_hash`, `notes`, `can_access_excel`, `can_access_cursos`, `is_active`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'Felipe Carlos Xavier de Lima', 'carlos', 'acesso@davidalmeida.xyz', '$2y$10$nW1JpDNqEkPqJ.4kge89U.o0PgB89YcYDrzuYmUvbSFMlduwMsW6q', '', 1, 0, 1, '2026-09-09 23:02:26', '2026-05-20 19:58:42', '2026-09-09 23:02:26'),
(2, 'Marina Santos', 'mauribenath', 'acesso2@davidalmeida.xyz', '$2y$10$I1auLbSTFSxlr3MAAURuWe/bFeqLKcTtjWlREtCAJkCLVbSBuxRm6', '', 1, 0, 1, '2026-05-26 22:14:58', '2026-05-20 20:05:11', '2026-05-26 22:14:58'),
(3, 'Gabrielli Mita de Farias', 'fariasgaby', 'acesso3@davidalmeida.xyz', '$2y$10$iv3pruWG3XOuLsTXq3MNq.aHKqD.ZhNuklYC7YA4xpbwibeMBXS0y', '', 1, 0, 1, '2026-09-08 23:48:58', '2026-05-20 20:05:36', '2026-09-08 23:48:58'),
(4, 'Nicolsa Dias', 'saru', 'acesso4@davidalmeida.xyz', '$2y$10$FoExw8eke2H1jjwX0FBEDefoVYcKkXC2E7p/Zazm2pBBP5MtQapcW', '', 1, 0, 1, '2026-06-17 12:30:11', '2026-05-20 20:06:16', '2026-06-17 12:30:11'),
(6, 'Demonstração', 'demo2026', 'demo@davidalmeida.xyz', '$2y$10$Mk.I10v3yOanumyMMbPbmuozQg5yduUQgWOtm3P99O4YddjUuGOSW', '', 1, 1, 1, '2026-09-04 18:50:10', '2026-08-10 17:05:17', '2026-09-04 18:50:10'),
(7, 'Mariany Ramos de Souza', 'mariany', 'acesso6@davidalmeida.xyz', '$2y$10$8WP3UfGpraluevM5NMduG.K4LhX5ZlfMbEMomtkG59CcOTfwUFTn6', '', 1, 0, 1, '', '2026-09-04 18:45:00', '2026-09-04 18:45:00'),
(8, 'Sarah Nicole Torres Bispo', 'sarah', 'acesso7@davidalmeida.xyz', '$2y$10$J/LYDPhJQB0IY7IBiiLpyOQWtmNjCcUcVMdlK1KgXkUvHpa7rBp.O', '', 1, 0, 1, '', '2026-09-04 18:47:49', '2026-09-04 18:47:49'),
(9, 'Suellen Ramos de Lima', 'suellen', 'acesso8@davidalmeida.xyz', '$2y$10$/iAWsfF..3AWA9GMfQDFUujTt1ovV3NxYGG4JIx3wCXlAde4NIqWu', '', 1, 0, 1, '2026-09-08 23:49:14', '2026-09-04 18:48:20', '2026-09-08 23:49:14'),
(10, 'Anna Jhulia Rodrigues Silva', 'anna', 'acesso9@davidalmeida.xyz', '$2y$10$083NYGzO.yWGN2t1feWg5eBob3teyOSfRqWEK/xrfUrlBLiWdnTqu', '', 0, 0, 1, '2026-09-08 23:50:10', '2026-09-04 18:48:45', '2026-09-08 23:50:10'),
(11, 'Livia Lino Lima', 'livia', 'acesso10@davidalmeida.xyz', '$2y$10$r6.NroHFMz9vZYma4XGViuItfN9FeoTyBq4XmT8sU.GBj9xCExDzC', '', 0, 0, 1, '2026-09-08 23:49:18', '2026-09-04 18:49:10', '2026-09-08 23:49:18');

-- --------------------------------------------------------

--
-- Estrutura para tabela `student_content_progress`
--

CREATE TABLE `student_content_progress` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `content_id` int(10) UNSIGNED NOT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `completed_at` varchar(25) NOT NULL DEFAULT '',
  `notes` text NOT NULL,
  `created_at` varchar(25) NOT NULL,
  `updated_at` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `student_content_progress`
--

INSERT INTO `student_content_progress` (`id`, `student_id`, `content_id`, `is_completed`, `completed_at`, `notes`, `created_at`, `updated_at`) VALUES
(1, 6, 1, 1, '2026-08-10 17:07:46', '', '2026-08-10 17:07:46', '2026-08-10 17:07:46'),
(2, 6, 2, 1, '2026-08-10 17:07:46', '', '2026-08-10 17:07:46', '2026-08-10 17:07:46'),
(3, 6, 3, 1, '2026-08-10 17:07:46', '', '2026-08-10 17:07:46', '2026-08-10 17:07:46'),
(4, 6, 4, 1, '2026-08-10 17:07:46', '', '2026-08-10 17:07:46', '2026-08-10 17:07:46'),
(5, 6, 5, 1, '2026-08-10 17:08:03', '', '2026-08-10 17:08:03', '2026-08-10 17:08:03'),
(6, 6, 6, 1, '2026-08-10 17:08:03', '', '2026-08-10 17:08:03', '2026-08-10 17:08:03'),
(7, 6, 7, 1, '2026-08-10 17:08:03', '', '2026-08-10 17:08:03', '2026-08-10 17:08:03'),
(8, 6, 8, 1, '2026-08-10 17:08:03', '', '2026-08-10 17:08:03', '2026-08-10 17:08:03'),
(9, 6, 9, 1, '2026-08-10 17:08:03', '', '2026-08-10 17:08:03', '2026-08-10 17:08:03'),
(10, 6, 10, 1, '2026-08-10 17:08:03', '', '2026-08-10 17:08:03', '2026-08-10 17:08:03'),
(11, 6, 11, 1, '2026-08-10 17:08:17', '', '2026-08-10 17:08:17', '2026-08-10 17:08:17'),
(12, 6, 12, 1, '2026-08-10 17:08:17', '', '2026-08-10 17:08:17', '2026-08-10 17:08:17'),
(13, 6, 13, 1, '2026-08-10 17:08:17', '', '2026-08-10 17:08:17', '2026-08-10 17:08:17'),
(14, 6, 14, 1, '2026-08-10 17:08:17', '', '2026-08-10 17:08:17', '2026-08-10 17:08:17'),
(15, 6, 15, 1, '2026-08-10 17:08:17', '', '2026-08-10 17:08:17', '2026-08-10 17:08:17'),
(16, 6, 16, 1, '2026-08-10 17:08:17', '', '2026-08-10 17:08:17', '2026-08-10 17:08:17'),
(17, 6, 17, 1, '2026-08-10 17:08:17', '', '2026-08-10 17:08:17', '2026-08-10 17:08:17'),
(18, 6, 18, 1, '2026-08-10 17:08:25', '', '2026-08-10 17:08:25', '2026-08-10 17:08:25'),
(19, 6, 19, 1, '2026-08-10 17:08:25', '', '2026-08-10 17:08:25', '2026-08-10 17:08:25'),
(20, 6, 20, 1, '2026-08-10 17:08:25', '', '2026-08-10 17:08:25', '2026-08-10 17:08:25'),
(21, 6, 21, 1, '2026-08-10 17:08:25', '', '2026-08-10 17:08:25', '2026-08-10 17:08:25');

-- --------------------------------------------------------

--
-- Estrutura para tabela `student_materials`
--

CREATE TABLE `student_materials` (
  `id` int(10) UNSIGNED NOT NULL,
  `area` varchar(40) NOT NULL DEFAULT 'excel',
  `title` varchar(190) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `relative_path` varchar(500) NOT NULL DEFAULT '',
  `resource_url` varchar(600) NOT NULL DEFAULT '',
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` varchar(25) NOT NULL,
  `updated_at` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `student_materials`
--

INSERT INTO `student_materials` (`id`, `area`, `title`, `description`, `relative_path`, `resource_url`, `is_published`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 'cursos', 'Google Drive - Curso Basico de Informatica', 'Acesso aos materiais e apostilas do curso em nuvem.', '', 'https://drive.google.com/drive/folders/1O_-9XvPjJA9uqOtfX0V2Z4J2PJkWeTCj?usp=sharing', 1, 1, '2026-05-20 19:51:36', '2026-05-20 19:51:36');

-- --------------------------------------------------------

--
-- Estrutura para tabela `student_track_enrollments`
--

CREATE TABLE `student_track_enrollments` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `track_id` int(10) UNSIGNED NOT NULL,
  `enrollment_status` varchar(40) NOT NULL DEFAULT 'active',
  `enrolled_at` varchar(25) NOT NULL DEFAULT '',
  `notes` text NOT NULL,
  `created_at` varchar(25) NOT NULL,
  `updated_at` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `student_track_enrollments`
--

INSERT INTO `student_track_enrollments` (`id`, `student_id`, `track_id`, `enrollment_status`, `enrolled_at`, `notes`, `created_at`, `updated_at`) VALUES
(1, 6, 1, 'active', '2026-08-10 17:07:03', '', '2026-08-10 17:07:03', '2026-08-10 17:07:03'),
(2, 6, 2, 'active', '2026-08-10 19:59:55', '', '2026-08-10 19:59:55', '2026-08-10 19:59:55');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `admin_audit_logs`
--
ALTER TABLE `admin_audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_audit_created` (`created_at`,`id`);

--
-- Índices de tabela `class_attendance`
--
ALTER TABLE `class_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_student_module_attendance_unique` (`student_id`,`module_id`),
  ADD KEY `idx_class_attendance_student` (`student_id`,`id`),
  ADD KEY `idx_class_attendance_module` (`module_id`,`id`);

--
-- Índices de tabela `class_students`
--
ALTER TABLE `class_students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_class_students_unique` (`class_id`,`student_id`),
  ADD KEY `idx_class_students_student` (`student_id`,`id`);

--
-- Índices de tabela `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contacts_status_created` (`status`,`created_at`),
  ADD KEY `idx_contacts_priority_created` (`priority`,`created_at`);

--
-- Índices de tabela `contact_message_events`
--
ALTER TABLE `contact_message_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contact_events_message_created` (`message_id`,`created_at`,`id`);

--
-- Índices de tabela `contact_reply_templates`
--
ALTER TABLE `contact_reply_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `title` (`title`);

--
-- Índices de tabela `course_classes`
--
ALTER TABLE `course_classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_course_classes_track_date` (`track_id`,`class_date`,`start_time`);

--
-- Índices de tabela `course_contents`
--
ALTER TABLE `course_contents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_course_contents_module_order` (`module_id`,`display_order`,`id`);

--
-- Índices de tabela `course_modules`
--
ALTER TABLE `course_modules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_course_modules_track_order` (`track_id`,`display_order`,`id`);

--
-- Índices de tabela `course_tracks`
--
ALTER TABLE `course_tracks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_course_tracks_published_order` (`is_published`,`display_order`,`id`);

--
-- Índices de tabela `external_resources`
--
ALTER TABLE `external_resources`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_resources_published_order` (`is_published`,`display_order`,`updated_at`);

--
-- Índices de tabela `external_resource_clicks`
--
ALTER TABLE `external_resource_clicks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_resource_clicks_resource_created` (`resource_id`,`created_at`),
  ADD KEY `idx_resource_clicks_slug_created` (`resource_slug`,`created_at`);

--
-- Índices de tabela `portfolio_items`
--
ALTER TABLE `portfolio_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_portfolio_published_created` (`is_published`,`created_at`);

--
-- Índices de tabela `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Índices de tabela `student_accounts`
--
ALTER TABLE `student_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `ux_student_accounts_email` (`email`),
  ADD KEY `idx_student_accounts_active_username` (`is_active`,`username`);

--
-- Índices de tabela `student_content_progress`
--
ALTER TABLE `student_content_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_student_content_unique` (`student_id`,`content_id`),
  ADD KEY `idx_student_progress_student` (`student_id`,`id`),
  ADD KEY `idx_student_progress_content` (`content_id`,`id`);

--
-- Índices de tabela `student_materials`
--
ALTER TABLE `student_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_materials_area_published` (`area`,`is_published`,`display_order`,`updated_at`);

--
-- Índices de tabela `student_track_enrollments`
--
ALTER TABLE `student_track_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_student_track_unique` (`student_id`,`track_id`),
  ADD KEY `idx_student_enrollments_student` (`student_id`,`id`),
  ADD KEY `idx_student_enrollments_track` (`track_id`,`id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `admin_audit_logs`
--
ALTER TABLE `admin_audit_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `class_attendance`
--
ALTER TABLE `class_attendance`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `class_students`
--
ALTER TABLE `class_students`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT de tabela `contact_message_events`
--
ALTER TABLE `contact_message_events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `contact_reply_templates`
--
ALTER TABLE `contact_reply_templates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53147;

--
-- AUTO_INCREMENT de tabela `course_classes`
--
ALTER TABLE `course_classes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `course_contents`
--
ALTER TABLE `course_contents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=142;

--
-- AUTO_INCREMENT de tabela `course_modules`
--
ALTER TABLE `course_modules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de tabela `course_tracks`
--
ALTER TABLE `course_tracks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `external_resources`
--
ALTER TABLE `external_resources`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53149;

--
-- AUTO_INCREMENT de tabela `external_resource_clicks`
--
ALTER TABLE `external_resource_clicks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=691;

--
-- AUTO_INCREMENT de tabela `portfolio_items`
--
ALTER TABLE `portfolio_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `student_accounts`
--
ALTER TABLE `student_accounts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `student_content_progress`
--
ALTER TABLE `student_content_progress`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de tabela `student_materials`
--
ALTER TABLE `student_materials`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `student_track_enrollments`
--
ALTER TABLE `student_track_enrollments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

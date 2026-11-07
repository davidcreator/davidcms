#!/usr/bin/env python3
"""Frente BN — David Universe 3D: tema COMPLETO baseado na amostra online
https://davidalmeida.xyz/threejs/?lang=pt

25 fontes Twig allowlistados, conteudo real transcrito, ThreeJS declarativo
(slot [data-scene] + conditions por rota), kaz_scene de 20 camadas.
Zero JS executavel, zero |raw, zero fontes remotas/CDN, zero migration.
"""
import json,os,sys
sys.path.insert(0,'/home/user/validacao')
from bn_css import CSS
OUT='/tmp/bn_config.json'
BRAND='David Universe 3D'
SLUG='david_universe_3d'
SLOT="{% include 'partial/theme-scene.twig' %}"

# ── selos SVG inline (substituem as .webp remotas da amostra) ──────────
def seal(i,hue='#5cc6d0'):
    shapes=[
     '<circle cx="80" cy="60" r="34" fill="none" stroke="H" stroke-width="2"/><circle cx="80" cy="60" r="20" fill="none" stroke="H" stroke-width="1" opacity=".6"/><path d="M20 96h120" stroke="H" stroke-width="1" opacity=".5"/>',
     '<path d="M28 96 80 24l52 72z" fill="none" stroke="H" stroke-width="2"/><path d="M52 96 80 58l28 38" fill="none" stroke="H" stroke-width="1" opacity=".6"/>',
     '<rect x="30" y="30" width="100" height="72" rx="8" fill="none" stroke="H" stroke-width="2"/><path d="M30 60h100M80 30v72" stroke="H" stroke-width="1" opacity=".5"/>',
     '<circle cx="80" cy="60" r="40" fill="none" stroke="H" stroke-width="2"/><ellipse cx="80" cy="60" rx="40" ry="15" fill="none" stroke="H" stroke-width="1" opacity=".6"/>',
     '<path d="M26 90q27-60 54 0t54 0" fill="none" stroke="H" stroke-width="2"/><circle cx="80" cy="46" r="12" fill="none" stroke="H" stroke-width="1"/>',
     '<path d="M40 24h80v84H40z" fill="none" stroke="H" stroke-width="2"/><path d="M40 24l40 42 40-42M40 108l40-42 40 42" stroke="H" stroke-width="1" opacity=".6"/>',
     '<circle cx="60" cy="60" r="26" fill="none" stroke="H" stroke-width="2"/><circle cx="100" cy="60" r="26" fill="none" stroke="H" stroke-width="2"/>',
     '<path d="M24 60h112M80 16v88" stroke="H" stroke-width="2"/><circle cx="80" cy="60" r="30" fill="none" stroke="H" stroke-width="1" opacity=".6"/>',
     '<path d="M80 18 96 50l34 4-25 24 7 34-32-17-32 17 7-34-25-24 34-4z" fill="none" stroke="H" stroke-width="2"/>',
    ]
    s=shapes[i%len(shapes)].replace('H',hue)
    return ('<svg viewBox="0 0 160 120" fill="none" aria-hidden="true" '
            'role="img" aria-label="Selo do projeto">'+s+'</svg>')

PROJECTS=[
 ('Juvenile World Academy','Academia','Prévia do logo','#5cc6d0'),
 ('Steel of Death','RedBubble','6 imagens','#eb767e'),
 ('Farmer Life Simulation','Jogo Roblox','Prévia do logo','#ffdf00'),
 ('Chameleon Digital Art','RedBubble','6 imagens','#7fdc8c'),
 ('Rogério Consultoria','Consultoria','6 imagens','#5cc6d0'),
 ('Pitbul Color','RedBubble','6 imagens','#eb767e'),
 ('Cat Fe','Arte Digital','6 imagens','#ffdf00'),
 ('Classic Car','RedBubble','6 imagens','#a7b3b5'),
 ('Selva Dev','StartUp','6 imagens','#7fdc8c'),
]
LEVELS=[
 ('Conhecendo o Cliente e a Empresa','Na primeira etapa, busco reunir o máximo de informações sobre as características e a personalidade do cliente e da empresa! Uso um questionário detalhado, cheio de perguntas estratégicas sobre o projeto.'),
 ('Pesquisa','Com as informações coletadas, começo a pesquisar a empresa, o mercado em que atua, seus concorrentes e o público-alvo. Essas informações evitam criar algo genérico.'),
 ('Conceito','De acordo com os resultados da pesquisa, inicio um processo de brainstorming, mapeando ideias e esboçando conceitos, sempre em busca de um significado maior, além do visual.'),
 ('Execução','Depois de todos os testes, com uma ideia estabelecida, começo a definir tipografia e cores, além de criar formas, grafismos, padrões e elementos de apoio.'),
 ('Flexibilidade','Com tudo em mãos, começo a etapa de aplicação em produtos como papelaria, uniformes, outdoors e brindes. Garantindo que a marca possa ser usada e identificada em qualquer situação.'),
 ('Entrega','Com o projeto finalizado, crio e envio uma apresentação e o manual de uso da marca, mostrando cada etapa do projeto. Todos os arquivos são salvos, organizados e enviados ao cliente.'),
]
POWERS=[
 ('Unidade','Uma empresa sem identidade visual bem desenvolvida não tem personalidade. Com um manual de marca e uma boa identidade visual, seu público consegue identificá-la onde quer que vá.'),
 ('Credibilidade','Uma identidade visual bem elaborada traz confiança a quem a vê, agregando maior credibilidade e profissionalismo à marca.'),
 ('Atração','Atrair o público certo e ampliar o potencial de consumidores exige uma comunicação clara. Uma identidade visual profissional garante que ela seja a mais adequada.'),
]
SERVICES=[
 ('Design Gráfico',['Design de Marca','Interface de Usuário','Ilustrações','Mídias Sociais','Instalações Digitais']),
 ('Web Design',['Landing Page','CMS','E-commerce','Web App','Institucional']),
 ('SEO',['Análise de Palavras-chave','Otimização On-Page','Criação de Conteúdo','Link Building','Auditoria de SEO']),
]

def phase(n,label,title,inner,num=None):
    tag='FASE %02d'%n
    return ('<section id="fase-%02d" class="du du-phase">\n'%n+
      '<span class="du-phase-num" aria-hidden="true">%02d</span>\n'%(num if num is not None else n)+
      '<div class="du-wrap">\n'
      '<div class="du-phase-tag"><b>'+tag+'</b><span>'+label+'</span></div>\n'
      '<h2>'+title+'</h2>\n'+inner+'\n</div>\n</section>\n')

def card(tag,title,body):
    return ('<article class="du-card"><span class="du-card-tag">'+tag+'</span>'
            '<h3>'+title+'</h3><p>'+body+'</p></article>')

def list_items(items):
    return '<ul class="du-list">'+''.join('<li><b>%02d</b><span>%s</span></li>'%(i+1,x) for i,x in enumerate(items))+'</ul>'

# ── 1. cabecalho (HUD + CSS) ──────────────────────────────────────────
NAV=[('SOBRE','#fase-01'),('PROCESSO','#fase-02'),('HABILIDADES','#fase-04'),
     ('SERVIÇOS','#fase-05'),('CATÁLOGO','#fase-06'),('CONTATO','#fase-08')]
HEADER=("{# David Universe 3D — HUD + folha de estilo. O header.twig do sistema inclui este partial com only. #}\n"
 "<style>\n"+CSS+"\n</style>\n"
 '<div class="du du-hud"><div class="du-hud-in">\n'
 '  <a class="du-brand" href="{{ home|default(\'/\') }}" aria-label="{{ text_brand|default(\'David L. Almeida\') }}">'
 '<span class="du-brand-mark">DA</span>'
 '<span class="du-brand-txt">DAVID L. ALMEIDA<small>Creative Developer</small></span></a>\n'
 '  <nav class="du-nav" aria-label="Navegação principal">\n'
 +''.join('    <a href="%s">%s</a>\n'%(h,t) for t,h in NAV)+
 '  </nav>\n'
 '  <div class="du-orbs" aria-label="Orbes coletados">ORBES 00/10'
 +''.join('<span class="du-orb%s"></span>'%(' on' if i<2 else (' gold' if i==2 else (' rose' if i==3 else (' green' if i==4 else '')))) for i in range(5))
 +'</div>\n'
 '  <nav class="du-orbs" aria-label="{{ text_languages|default(\'Idiomas\') }}">'
 '{% for lang in languages|default([]) %}<a href="{{ lang.href }}" hreflang="{{ lang.code }}">{{ lang.code }}</a>{% endfor %}</nav>\n'
 '</div>\n'
 '<div class="du-status"><span>FASE 00 — DECOLAGEM</span><span>DISTÂNCIA <b>0 KM</b></span></div>\n'
 '</div>\n')

FOOTER=("{# Rodape — fecha o documento. #}\n"
 '<footer class="du du-footer"><div class="du-wrap">\n'
 '<div class="du-footer-ghost" aria-hidden="true">DAVID L. ALMEIDA</div>\n'
 '<div class="du-footer-grid">\n'
 '  <div><h4>David L. Almeida</h4><p style="color:var(--du-muted);font-size:.86rem">'
 'Desenvolvedor Criativo. Posso criar sites, aplicativos, logotipos e outras mídias digitais incríveis. '
 'Casa e Escritório: São Paulo, Brasil.</p>'
 '<p style="font-size:.86rem"><a href="mailto:contact@davidalmeida.xyz">contact@davidalmeida.xyz</a></p></div>\n'
 '  <div><h4>Me encontre em</h4>'
 +''.join('<a href="%s">%s</a>'%(h,t) for t,h in [('GitHub','https://github.com/davidcreator'),('X','https://x.com/DavidAlmeidaDes'),('GitLab','#'),('Instagram','#'),('Pinterest','#'),('RedBubble','#')])
 +'</div>\n'
 '  <div><h4>Navegar</h4>'+''.join('<a href="%s">%s</a>'%(h,t) for t,h in NAV)+'</div>\n'
 '</div>\n'
 '<div class="du-footer-bottom"><span>© 2026 David L. Almeida · Desenvolvedor Criativo</span>'
 '<span>ROLE PARA VOAR · DISTÂNCIA 0 KM</span></div>\n'
 '</div></footer>\n'
 "{% if theme_scene.enabled|default(false) and theme_scene.slot|default('') == 'footer_top' %}"+SLOT+"{% endif %}\n"
 '</body>\n</html>\n')

# ── 2. hero ───────────────────────────────────────────────────────────
HERO=("{# Fase 00 — Decolagem / cockpit #}\n"
 '<section id="fase-00" class="du du-hero"><div class="du-wrap du-hero-in">\n'
 '  <div><div class="du-overline">EU SOU</div>\n'
 '    <h1>DAVID L.<br>ALMEIDA</h1>\n'
 '    <div class="du-hero-sub">{{ text_role|default(\'Desenvolvido\') }}</div>\n'
 '    <p class="du-hero-lead">Posso criar sites, aplicativos, logotipos e outras mídias digitais incríveis.</p>\n'
 '    <div class="du-hero-actions">'
 '<a class="du-btn" href="#fase-01">Iniciar missão →</a>'
 '<a class="du-btn du-btn--ghost" href="#fase-06">Ver catálogo</a></div>\n'
 '    <div class="du-socials" aria-label="Redes sociais">'
 +''.join('<a href="%s" aria-label="%s">%s</a>'%(h,t,t[:2]) for t,h in
   [('Facebook','#'),('Instagram','#'),('Pinterest','#'),('X','https://x.com/DavidAlmeidaDes'),('RedBubble','#')])
 +'</div>\n  </div>\n'
 '  <div class="du-controls"><h3>Controles</h3>'
 '<div class="du-control"><b>MOUSE</b><span>Mova o mouse para pilotar e navegar.</span></div>'
 '<div class="du-control"><b>TECLADO</b><span>Role para voar pelo universo.</span></div>'
 '<div class="du-control"><b>ORBES</b><span>Colete os orbes pelo caminho.</span></div>'
 '<div class="du-control"><b>MISSÃO</b><span>Escolha um nível para explorar o projeto completo.</span></div>'
 '</div>\n</div>\n'
 '<div class="du-wrap du-hint"><span>ROLE PARA VOAR ▾</span><span>DISTÂNCIA 0 KM</span></div>\n'
 '</section>\n')
HERO3D=HERO.replace('Fase 00 — Decolagem / cockpit','Fase 00 — variante de cena 3D')

# ── 3. secoes ─────────────────────────────────────────────────────────
ABOUT=phase(1,'Sobre','QUEM SOU EU?',
 '<div class="du-grid du-grid--2">'
 +card('Classe','SOU UM DESIGNER E DESENVOLVEDOR EXPERIENTE',
   'Como designer e desenvolvedor apaixonado, transformo ideias em realidade com criatividade e responsabilidade. '
   'Com anos de experiência em design gráfico, desenvolvimento de sites e criação de identidades visuais, '
   'entrego soluções inovadoras e de alta qualidade que fortalecem marcas e as destacam no mercado.')
 +card('Jogador 1 · LVL 01','David L. Almeida',
   'Meu portfólio vai de logotipos de impacto a interfaces intuitivas, sempre alinhado às melhores práticas de SEO. '
   'Descubra como posso ajudar a elevar a sua marca ao próximo nível!')
 +'</div>'
 '<div class="du-grid du-grid--2" style="margin-top:20px">'
 +card('Identidade visual','GOSTO DE TRABALHAR EM PROJETOS QUE AJUDAM MARCAS A SE POSICIONAREM',
   'No núcleo de cada marca existe um mundo cuidadosamente construído, onde cada atributo é projetado '
   'estrategicamente para atender às necessidades únicas dos nossos clientes.')
 +card('Universo do design','BEM-VINDO AO UNIVERSO DO DESIGN',
   'Do logotipo às cores, da tipografia aos grafismos, desenhamos uma experiência marcante e personalizada '
   'que ecoa a identidade de cada negócio. Onde damos vida às marcas e contamos histórias por meio de formas, cores e letras.')
 +'</div>'
 '<div class="du-profile" style="margin-top:20px">'
 +'<div class="du-card"><span class="du-card-tag">Ficha do jogador</span><h3>David L. Almeida</h3>'
 +''.join('<div class="du-profile-row"><span>%s</span><b>%s</b></div>'%(k,v) for k,v in
   [('Classe','Desenvolvedor Criativo'),('Local','São Paulo, Brazil'),('E-mail','contact@davidalmeida.xyz'),('Nível','LVL 01')])
 +'</div><div class="du-card"><span class="du-card-tag">Me encontre em</span><h3>Canais</h3>'
 +list_items(['GitHub','GitLab','X / Twitter','Instagram','Pinterest','RedBubble'])+'</div></div>')

METHOD=phase(2,'Processo','NO MEU PROCESSO DE CRIAÇÃO EU USO AS SEGUINTES ETAPAS:',
 '<p class="du-lead">Seis níveis, nenhuma caixa preta: cada etapa termina com uma validação antes da próxima.</p>'
 '<div class="du-grid du-grid--6" style="margin-top:28px">'
 +''.join('<article class="du-card du-level"><div class="du-level-no">%02d</div>'
          '<span class="du-level-tag">Nível %02d · OK</span><h3>%s</h3><p>%s</p></article>'
          %(i+1,i+1,t,b) for i,(t,b) in enumerate(LEVELS))
 +'</div>')

WHY=phase(3,'Por que investir','POR QUE INVESTIR EM UMA IDENTIDADE VISUAL?',
 '<div class="du-grid du-grid--3">'
 +''.join(card('Power-up %02d · +1'%(i+1),t,b) for i,(t,b) in enumerate(POWERS))
 +'</div>')

SKILLS=phase(4,'Habilidades','MINHAS HABILIDADES',
 '<div class="du-grid du-grid--3">'
 +'<div class="du-card"><span class="du-card-tag">Classe</span><h3>Desenvolvedor Criativo</h3>'
 '<p>Posso criar sites, aplicativos, logotipos e outras mídias digitais incríveis.</p>'
 +list_items(['Desenvolvedor','Design de Marca','Identidade Visual','Design de Produto','Ilustrações','Ação de Marca'])
 +'</div>'
 +''.join('<div class="du-card"><span class="du-card-tag">Inventário</span><h3>%s</h3>%s'
          '<p class="du-slots">05 slots</p></div>'%(t,list_items(v)) for t,v in [
   ('Tecnologias',['HTML','CSS','PHP','Javascript','jQuery']),
   ('Plataformas',['Wordpress','Prestashop','Joomla','Blogger','Winter']),
   ('Programas',['VS Code','Illustrator','Photoshop','CorelDRAW','Figma'])])
 +'</div>')

SERVICES_H=phase(5,'Serviços','O QUE EU FAÇO?',
 '<p class="du-lead">MEUS SERVIÇOS SÃO PRESTADOS COM MUITA DEDICAÇÃO E ANOS DE EXPERIÊNCIA.</p>'
 '<div class="du-grid du-grid--3" style="margin-top:28px">'
 +''.join('<div class="du-card"><span class="du-card-tag">%02d</span><h3>%s</h3>%s</div>'
          %(i+1,t,list_items(v)) for i,(t,v) in enumerate(SERVICES))
 +'</div>')

PORTFOLIO=phase(6,'Catálogo','ESCOLHA O SEU NÍVEL',
 '<p class="du-lead">Marcas, identidades e arte digital criadas ao longo do caminho. Escolha um nível para explorar o projeto completo.</p>'
 '<div class="du-grid du-grid--3" style="margin-top:28px">'
 +''.join('<article class="du-card du-level-card"><figure>%s</figure>'
          '<span class="du-card-tag">Nível %02d · %s</span><h3>%s</h3>'
          '<p>%s</p><span class="du-level-meta">%s</span></article>'
          %(seal(i,h),i+1,cat,name,'Projeto completo no catálogo de níveis.',meta)
          for i,(name,cat,meta,h) in enumerate(PROJECTS))
 +'</div>')

TESTIMONIALS=phase(7,'Depoimentos','DEPOIMENTOS DE CLIENTES',
 '<div class="du-card du-quote"><p>“O melhor profissional da área que já conheci. Tato para os detalhes, olhar técnico e preço justo. '
 'David é um profissional sensacional, eu o recomendo!”</p>'
 '<span class="du-quote-by">SCRE &amp; TRABALHO · Cliente</span></div>')

NEWS=phase(7,'Social','ÚLTIMOS POSTS DO INSTAGRAM',
 '<div class="du-social-grid">'+''.join('<div>%s</div>'%seal(i+1,['#5cc6d0','#eb767e','#ffdf00','#7fdc8c','#a7b3b5','#5cc6d0'][i]) for i in range(6))+'</div>'
 '<p style="margin-top:24px"><a class="du-btn du-btn--ghost" href="#">Seguir no Instagram</a></p>')
NEWS=NEWS.replace('id="fase-07"','id="fase-07-social"')

CONTACT=phase(8,'Contato','VAMOS CONVERSAR?',
 '<div class="du-grid du-grid--2">'
 '<div class="du-card"><span class="du-card-tag">Diga olá</span><h3>Endereço</h3>'
 '<p>Casa e Escritório: São Paulo, Brasil</p>'
 '<p><a href="mailto:contact@davidalmeida.xyz">contact@davidalmeida.xyz</a></p>'
 +list_items(['Facebook','Instagram','Pinterest','X','Google Business','RedBubble'])+'</div>'
 '<div class="du-card"><form class="du-form" method="post" action="{{ action|default(\'#\') }}">'
 '<label for="du-nome">Nome</label><input id="du-nome" name="name" type="text" maxlength="120" required>'
 '<label for="du-email">E-mail</label><input id="du-email" name="email" type="email" maxlength="160" required>'
 '<label for="du-msg">Mensagem</label><textarea id="du-msg" name="message" maxlength="4000" required></textarea>'
 '<button class="du-btn" type="submit">Enviar mensagem →</button></form></div>'
 '</div>')

CLIENTS=('<section class="du du-phase" id="fase-clientes"><div class="du-wrap">'
 '<div class="du-phase-tag"><b>FASE 01</b><span>Parceiros</span></div>'
 '<div class="du-grid du-grid--3">'
 +''.join('<div class="du-card"><span class="du-card-tag">%02d</span><h3>%s</h3><p>%s</p></div>'
   %(i+1,t,b) for i,(t,b) in enumerate([
    ('Marcas','Identidades visuais e manuais de uso para empresas de diferentes segmentos.'),
    ('Produtos digitais','Interfaces, sites e aplicativos com foco em clareza e resultado.'),
    ('Conteúdo','Arte digital, ilustrações e peças para redes sociais e campanhas.')]))
 +'</div></div></section>\n')
TOOLS=('<section class="du du-phase" id="fase-ferramentas"><div class="du-wrap">'
 '<div class="du-phase-tag"><b>FASE 04</b><span>Inventário</span></div>'
 '<h2>FERRAMENTAS DA MISSÃO</h2><div class="du-grid du-grid--3">'
 +''.join('<div class="du-card"><span class="du-card-tag">%s</span><h3>%s</h3>%s<p class="du-slots">05 slots</p></div>'
   %(tag,t,list_items(v)) for tag,t,v in [
    ('Tecnologias','Stack',['HTML','CSS','PHP','Javascript','jQuery']),
    ('Plataformas','CMS',['Wordpress','Prestashop','Joomla','Blogger','Winter']),
    ('Programas','Estúdio',['VS Code','Illustrator','Photoshop','CorelDRAW','Figma'])])
 +'</div></div></section>\n')

# ── 4. home ───────────────────────────────────────────────────────────
HOME=("{# Home completa — fases 00 a 09 #}\n{{ header }}\n<main class=\"du\">\n"
 +SLOT+"\n"
 '<div class="du-wrap"><div class="du-scene-caption"><b>UNIVERSO 3D</b>'
 '<span>Cena declarativa · sem código executável no tema</span></div></div>\n'
 +HERO+ABOUT+METHOD+WHY+SKILLS+SERVICES_H+PORTFOLIO+TESTIMONIALS+NEWS+CONTACT+
 '</main>\n{{ footer }}\n')

NOTFOUND=("{# Nível não encontrado #}\n{{ header }}\n<main class=\"du\">\n"
 '<div class="du-wrap du-404"><strong>404</strong><h2>Esta página não existe</h2>'
 '<p class="du-lead" style="margin:0 auto 26px">O universo continua em frente. Volte ao início ou escolha outro nível.</p>'
 '<a class="du-btn" href="{{ home|default(\'/\') }}">Voltar à decolagem →</a></div>\n'
 '</main>\n{{ footer }}\n')

def inner(title,lead,cards):
    return ("{# Página interna #}\n{{ header }}\n<main class=\"du\">\n"+SLOT+"\n"
     '<div class="du-wrap"><section class="du du-phase" style="border:0">'
     '<div class="du-phase-tag"><b>FASE EXTRA</b><span>Catálogo</span></div>'
     '<h2>'+title+'</h2><p class="du-lead">'+lead+'</p>'
     '<div class="du-grid du-grid--3" style="margin-top:28px">'
     +''.join(card('Nível %02d'%(i+1),t,b) for i,(t,b) in enumerate(cards))
     +'</div></section></div>\n</main>\n{{ footer }}\n')

T={}
T['common/home']=HOME
T['common/footer']=FOOTER
T['common/contact']=CONTACT
T['partial/site_header']=HEADER
T['partial/hero']=HERO
T['partial/hero-3d']=HERO3D
T['partial/about']=ABOUT
T['partial/methodology']=METHOD
T['partial/skills']=SKILLS
T['partial/services']=SERVICES_H
T['partial/testimonials']=TESTIMONIALS
T['partial/portfolio']=PORTFOLIO
T['partial/tools']=TOOLS
T['partial/news']=NEWS
T['partial/clients']=CLIENTS
T['partial/contact']=CONTACT
T['cms/page_info']=inner('SOBRE O PROJETO','Cada nível do catálogo é uma missão completa.',[(p[0],p[2]) for p in PROJECTS[:3]])
T['catalog/project_list']=inner('ESCOLHA O SEU NÍVEL',
 'Marcas, identidades e arte digital criadas ao longo do caminho.',[(p[0],p[1]+' · '+p[2]) for p in PROJECTS[:6]])
T['catalog/project_info']=inner('PROJETO COMPLETO','Prévia do nível selecionado.',[(t,'Etapa validada') for t,_ in LEVELS[:3]])
T['blog/blog_list']=inner('ÚLTIMOS POSTS','Notas sobre design, marca e desenvolvimento.',
 [('Unidade','Cores, formas e padrões que identificam uma marca.'),('Credibilidade','Como uma identidade clara gera confiança.'),('Atração','Comunicação certa com o público certo.')])
T['blog/blog_info']=inner('NOTA DO UNIVERSO','Leitura de cinco minutos.',[(t,b) for t,b in POWERS])
T['tool/tool_list']=inner('FERRAMENTAS DA MISSÃO','Inventário completo de tecnologias e plataformas.',
 [('Tecnologias','HTML, CSS, PHP, Javascript e jQuery.'),('Plataformas','Wordpress, Prestashop, Joomla, Blogger e Winter.'),('Programas','VS Code, Illustrator, Photoshop, CorelDRAW e Figma.')])
T['tool/tool_info']=inner('SLOT DO INVENTÁRIO','Cinco itens por categoria.',[(t,'Disponível') for t in ['HTML','CSS','PHP','Javascript','jQuery']])
T['checkout/quote']=inner('SOLICITAR MISSÃO','Conte o objetivo e receba uma proposta com escopo e prazo.',
 [('Descoberta','Entender o cliente e a empresa.'),('Conceito','Brainstorming e esboços.'),('Entrega','Apresentação e manual de uso.')])
T['error/not_found']=NOTFOUND

# ── 5. three / conditions / kaz_scene / tokens ────────────────────────
BASE=json.load(open('/home/user/David-Creator.reamurtheme.json',encoding='utf-8'))['config']
tokens=dict(BASE['tokens'])
tokens.update({'dark_surface':'#121516','dark_text':'#dfdfdf','dark_muted':'#a7b3b5',
               'dark_primary':'#5cc6d0','focus_ring':'#5cc6d0','content_width':'1180',
               'radius':'4','radius_lg':'8','line_height':'1.70','heading_weight':'700'})
THREE=lambda sc,sl,co,cl,sp:{'enabled':True,'scene':sc,'slot':sl,'count':co,'color':cl,'speed':sp}
cond={'version':1,'rules':[
 {'id':'du_home_particles','when':{'field':'route','op':'eq','value':'common/home'},
  'then':{'scene':'hero-particles','three':THREE('hero-particles','hero',520,'#5cc6d0',0.06)}},
 {'id':'du_catalog_3d','when':{'field':'route','op':'in','value':['catalog/project_list','catalog/project_info']},
  'then':{'scene':'portfolio-3d','three':THREE('portfolio-3d','home_top',500,'#eb767e',0.07)}},
 {'id':'du_tools_3d','when':{'field':'route','op':'in','value':['tool/tool_list','tool/tool_info','blog/blog_list']},
  'then':{'scene':'tools-3d','three':THREE('tools-3d','home_top',350,'#ffdf00',0.08)}},
 {'id':'du_not_found','when':{'field':'route','op':'eq','value':'error/not_found'},
  'then':{'scene':'not-found','three':THREE('not-found','footer_top',200,'#a7b3b5',0.04)}},
 {'id':'du_mobile_lite','when':{'field':'mobile','op':'eq','value':True},
  'then':{'classes':['du-scene-lite'],'three':THREE('hero-particles','hero',180,'#5cc6d0',0.04)}},
]}
# 20 camadas declarativas: HUD + planeta + orbes + cards de fase + CTA
C='#5cc6d0';D='#121516';INK='#dfdfdf';M='#a7b3b5';R='#eb767e';G='#ffdf00';P='#0e1011'
L=[]
def layer(i,kind,role,x,y,w,h,fill,text='',font=16,radius=4):
    L.append({'id':'du-%02d'%i,'kind':kind,'role':role,'x':x,'y':y,'w':w,'h':h,
      'fill':fill,'radius':radius,'text':text,'fontSize':font,'visible':True,
      'locked':False,'bindId':''})
layer(1,'rect','primary',  40, 40,880, 70,P,   radius=4)
layer(2,'text','none',     60, 56,360, 38,C,'David Universe 3D',26,0)
layer(3,'text','none',    620, 62,280, 30,M,'FASE 00 — DECOLAGEM',14,0)
layer(4,'card','surface',  40,130,420,220,P,   radius=8)
layer(5,'text','none',     60,150,380, 50,INK,'DAVID L. ALMEIDA',36,0)
layer(6,'text','none',     60,210,380, 36,C,'Desenvolvedor Criativo',20,0)
layer(7,'text','none',     60,258,380, 34,M,'Sites, apps, logotipos e mídias digitais',14,0)
layer(8,'button','component',60,310,220,42,C,'Iniciar missão',16,4)
layer(9,'card','surface', 480,130,440,220,P,   radius=8)
layer(10,'text','none',   500,150,400, 34,C,'CONTROLES',20,0)
layer(11,'text','none',   500,194,400, 30,M,'Mouse · pilotar a nave',14,0)
layer(12,'text','none',   500,230,400, 30,M,'Teclado · role para voar',14,0)
layer(13,'text','none',   500,266,400, 30,M,'Orbes · colete pelo caminho',14,0)
layer(14,'card','surface', 40,372,280,140,P,   radius=8)
layer(15,'text','none',    58,390,244, 34,C,'FASE 01 · SOBRE',18,0)
layer(16,'card','surface',340,372,280,140,P,   radius=8)
layer(17,'text','none',   358,390,244, 34,C,'FASE 02 · PROCESSO',18,0)
layer(18,'card','surface',640,372,280,140,P,   radius=8)
layer(19,'text','none',   658,390,244, 34,C,'FASE 06 · CATÁLOGO',18,0)
layer(20,'button','component',700,470,220,42,R,'Ver catálogo',16,4)

cfg={'name':BRAND,'slug':SLUG,'config':{
 'primary':C,'surface':D,'scene':'hero-particles','parent':'',
 'templates':T,'conditions':cond,'three':THREE('hero-particles','hero',520,C,0.06),
 'tokens':tokens,'kaz_scene':{'version':1,'canvas':{'w':960,'h':540,'grid':8},'layers':L}}}
json.dump(cfg,open(OUT,'w',encoding='utf-8'),ensure_ascii=False,indent=1)
print('fontes:',len(T),'| kaz_scene:',len(L),'camadas | conditions:',len(cond['rules']),'regras')
print('bytes config:',os.path.getsize(OUT))
print('com slot:',sum(SLOT in v for v in T.values()))

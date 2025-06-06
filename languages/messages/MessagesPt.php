<?php
/** Portuguese (português)
 *
 * @file
 * @ingroup Languages
 *
 * @author Alchimista
 * @author Andresilvazito
 * @author Cainamarques
 * @author Capmo
 * @author Crazymadlover
 * @author Daemorris
 * @author DanielTom
 * @author Dannyps
 * @author Dicionarista
 * @author Francisco Leandro
 * @author Fúlvio
 * @author Giro720
 * @author GoEThe
 * @author Hamilton Abreu
 * @author Helder.wiki
 * @author Imperadeiro98
 * @author Indech
 * @author Jens Liebenau
 * @author Jorge Morais
 * @author Josep Maria 15.
 * @author Kaganer
 * @author Leonardo.stabile
 * @author Lijealso
 * @author Luckas
 * @author Luckas Blade
 * @author Lugusto
 * @author MCruz
 * @author MF-Warburg
 * @author Malafaya
 * @author Manuel Menezes de Sequeira
 * @author Masked Rogue
 * @author Matma Rex
 * @author McDutchie
 * @author MetalBrasil
 * @author Minh Nguyen
 * @author Nemo bis
 * @author Nuno Tavares
 * @author OTAVIO1981
 * @author Opraco
 * @author Paulo Juntas
 * @author Pedroca cerebral
 * @author Polyethylen
 * @author Rafael Vargas
 * @author Rei-artur
 * @author Remember the dot
 * @author RmSilva
 * @author Rodrigo Calanca Nishino
 * @author SandroHc
 * @author Sarilho1
 * @author Sir Lestaty de Lioncourt
 * @author Sérgio Ribeiro
 * @author Teles
 * @author Urhixidur
 * @author Villate
 * @author Vitorvicentevalente
 * @author Waldir
 * @author Yves Marques Junqueira
 * @author לערי ריינהארט
 * @author 555
 */

$fallback = 'pt-br';

$namespaceNames = [
	NS_MEDIA            => 'Multimédia',
	NS_SPECIAL          => 'Especial',
	NS_TALK             => 'Discussão',
	NS_USER             => 'Utilizador',
	NS_USER_TALK        => 'Utilizador_Discussão',
	NS_PROJECT_TALK     => '$1_Discussão',
	NS_FILE             => 'Ficheiro',
	NS_FILE_TALK        => 'Ficheiro_Discussão',
	NS_MEDIAWIKI        => 'MediaWiki',
	NS_MEDIAWIKI_TALK   => 'MediaWiki_Discussão',
	NS_TEMPLATE         => 'Predefinição',
	NS_TEMPLATE_TALK    => 'Predefinição_Discussão',
	NS_HELP             => 'Ajuda',
	NS_HELP_TALK        => 'Ajuda_Discussão',
	NS_CATEGORY         => 'Categoria',
	NS_CATEGORY_TALK    => 'Categoria_Discussão',
];

$namespaceAliases = [
	'Usuário'                 => NS_USER,
	'Usuário_Discussão'       => NS_USER_TALK,
	'Usuária'                 => NS_USER, // T33986
	'Usuária_Discussão'       => NS_USER_TALK, // T33986
	'Usuário(a)'              => NS_USER, // T33986
	'Usuário(a)_Discussão'    => NS_USER_TALK, // T33986
	'Utilizador(a)'           => NS_USER, // T33986
	'Utilizador(a)_Discussão' => NS_USER_TALK, // T33986
	'Imagem'                  => NS_FILE,
	'Imagem_Discussão'        => NS_FILE_TALK,
	'Arquivo'                 => NS_FILE,
	'Arquivo_Discussão'       => NS_FILE_TALK,
];

$namespaceGenderAliases = [
	NS_USER => [ 'male' => 'Utilizador', 'female' => 'Utilizadora' ],
	NS_USER_TALK => [ 'male' => 'Utilizador_Discussão', 'female' => 'Utilizadora_Discussão' ],
];

$defaultDateFormat = 'dmy';

$dateFormats = [
	'dmy time' => 'H"h"i"min"',
	'dmy date' => 'j "de" F "de" Y',
	'dmy both' => 'H"h"i"min" "de" j "de" F "de" Y',
];

$separatorTransformTable = [ ',' => "\u{00A0}", '.' => ',' ];
$linkTrail = '/^([áâãàéêẽçíòóôõq̃úüűũa-z]+)(.*)$/sDu'; # T23168, T29633

/** @phpcs-require-sorted-array */
$specialPageAliases = [
	'Activeusers'               => [ 'Utilizadores_activos' ],
	'Allmessages'               => [ 'Todas_as_mensagens', 'Todas_mensagens' ],
	'Allpages'                  => [ 'Todas_as_páginas', 'Todos_os_artigos', 'Todas_páginas', 'Todos_artigos' ],
	'Ancientpages'              => [ 'Páginas_inactivas', 'Páginas_inativas', 'Artigos_inativos' ],
	'Badtitle'                  => [ 'Título_inválido' ],
	'Blankpage'                 => [ 'Página_em_branco' ],
	'Block'                     => [ 'Bloquear', 'Bloquear_IP', 'Bloquear_utilizador', 'Bloquear_usuário' ],
	'BlockList'                 => [ 'Registo_de_bloqueios', 'IPs_bloqueados', 'Utilizadores_bloqueados', 'Registro_de_bloqueios', 'Usuários_bloqueados' ],
	'Booksources'               => [ 'Fontes_de_livros' ],
	'BrokenRedirects'           => [ 'Redireccionamentos_quebrados', 'Redirecionamentos_quebrados' ],
	'Categories'                => [ 'Categorias' ],
	'ChangeEmail'               => [ 'Alterar_e-mail', 'Alterar_correio_electrónico' ],
	'ChangePassword'            => [ 'Reiniciar_palavra-chave', 'Repor_senha', 'Zerar_senha' ],
	'ComparePages'              => [ 'Comparar_páginas' ],
	'Confirmemail'              => [ 'Confirmar_correio_electrónico', 'Confirmar_e-mail', 'Confirmar_email' ],
	'Contributions'             => [ 'Contribuições' ],
	'CreateAccount'             => [ 'Criar_conta' ],
	'Deadendpages'              => [ 'Páginas_sem_saída', 'Artigos_sem_saída' ],
	'DeletedContributions'      => [ 'Contribuições_eliminadas', 'Edições_eliminadas' ],
	'Diff'                      => [ 'Diferenças_entre_edições', 'Mudanças_entre_edições' ],
	'DoubleRedirects'           => [ 'Redireccionamentos_duplos', 'Redirecionamentos_duplos' ],
	'EditWatchlist'             => [ 'Editar_lista_de_páginas_vigiadas' ],
	'Emailuser'                 => [ 'Contactar_utilizador', 'Contactar_usuário', 'Contatar_usuário' ],
	'ExpandTemplates'           => [ 'Expandir_predefinições' ],
	'Export'                    => [ 'Exportar' ],
	'Fewestrevisions'           => [ 'Páginas_com_menos_edições', 'Artigos_com_menos_edições', 'Artigos_menos_editados' ],
	'FileDuplicateSearch'       => [ 'Busca_de_ficheiros_duplicados', 'Busca_de_arquivos_duplicados' ],
	'Filepath'                  => [ 'Directório_de_ficheiro', 'Diretório_de_ficheiro', 'Diretório_de_arquivo' ],
	'Import'                    => [ 'Importar' ],
	'Interwiki'                 => [ 'Interwikis' ],
	'Invalidateemail'           => [ 'Invalidar_correio_electrónico', 'Invalidar_e-mail' ],
	'LinkSearch'                => [ 'Pesquisar_links' ],
	'Listadmins'                => [ 'Administradores', 'Admins', 'Lista_de_administradores', 'Lista_de_admins' ],
	'Listbots'                  => [ 'Robôs', 'Lista_de_robôs', 'Bots', 'Lista_de_bots' ],
	'ListDuplicatedFiles'       => [ 'Lista_de_ficheiros_duplicados', 'Lista_de_arquivos_duplicados' ],
	'Listfiles'                 => [ 'Lista_de_ficheiros', 'Lista_de_imagens', 'Lista_de_arquivos' ],
	'Listgrouprights'           => [ 'Lista_de_privilégios_de_grupos', 'Listar_privilégios_de_grupos' ],
	'Listredirects'             => [ 'Redireccionamentos', 'Redirecionamentos', 'Lista_de_redireccionamentos', 'Lista_de_redirecionamentos' ],
	'Listusers'                 => [ 'Lista_de_utilizadores', 'Lista_de_usuários' ],
	'Lockdb'                    => [ 'Bloquear_base_de_dados', 'Bloquear_a_base_de_dados', 'Bloquear_banco_de_dados' ],
	'Log'                       => [ 'Registo', 'Registos', 'Registro', 'Registros' ],
	'Lonelypages'               => [ 'Páginas_órfãs', 'Páginas_sem_afluentes', 'Artigos_órfãos', 'Artigos_sem_afluentes' ],
	'Longpages'                 => [ 'Páginas_longas', 'Artigos_extensos' ],
	'MergeHistory'              => [ 'Fundir_históricos', 'Fundir_edições' ],
	'MIMEsearch'                => [ 'Busca_MIME' ],
	'Mostcategories'            => [ 'Páginas_com_mais_categorias', 'Artigos_com_mais_categorias' ],
	'Mostimages'                => [ 'Ficheiros_com_mais_afluentes', 'Imagens_com_mais_afluentes', 'Arquivos_com_mais_afluentes' ],
	'Mostinterwikis'            => [ 'Páginas_com_mais_interwikis' ],
	'Mostlinked'                => [ 'Páginas_com_mais_afluentes', 'Artigos_com_mais_afluentes' ],
	'Mostlinkedcategories'      => [ 'Categorias_com_mais_afluentes', 'Categorias_mais_usadas' ],
	'Mostlinkedtemplates'       => [ 'Predefinições_com_mais_afluentes', 'Predefinições_mais_usadas' ],
	'Mostrevisions'             => [ 'Páginas_com_mais_edições', 'Artigos_com_mais_edições' ],
	'Movepage'                  => [ 'Mover_página', 'Mover', 'Mover_artigo' ],
	'Mycontributions'           => [ 'Minhas_contribuições', 'Minhas_edições', 'Minhas_constribuições' ],
	'Mypage'                    => [ 'Minha_página' ],
	'Mytalk'                    => [ 'Minha_discussão' ],
	'Newimages'                 => [ 'Ficheiros_novos', 'Imagens_novas', 'Arquivos_novos' ],
	'Newpages'                  => [ 'Páginas_novas', 'Artigos_novos' ],
	'PagesWithProp'             => [ 'Propriedades_de_página' ],
	'PasswordReset'             => [ 'Redefinir_autenticação' ],
	'PermanentLink'             => [ 'Ligação_permanente', 'Link_permanente' ],
	'Preferences'               => [ 'Preferências' ],
	'Prefixindex'               => [ 'Índice_por_prefixo', 'Índice_de_prefixo' ],
	'Protectedpages'            => [ 'Páginas_protegidas', 'Artigos_protegidos' ],
	'Protectedtitles'           => [ 'Títulos_protegidos' ],
	'RandomInCategory'          => [ 'Aleatória_na_Categoria', 'Aleatório_na_Categoria' ],
	'Randompage'                => [ 'Aleatória', 'Aleatório', 'Página_aleatória', 'Artigo_aleatório' ],
	'Randomredirect'            => [ 'Redireccionamento_aleatório', 'Redirecionamento_aleatório' ],
	'Recentchanges'             => [ 'Mudanças_recentes' ],
	'Recentchangeslinked'       => [ 'Alterações_relacionadas', 'Novidades_relacionadas', 'Mudanças_relacionadas' ],
	'Redirect'                  => [ 'Redirecionar', 'Redireccionar' ],
	'Renameuser'                => [ 'Alterar_nome_de_utilizador' ],
	'ResetTokens'               => [ 'Reiniciar_tokens', 'Reiniciar_os_tokens' ],
	'Revisiondelete'            => [ 'Eliminar_edição', 'Eliminar_revisão', 'Apagar_edição', 'Apagar_revisão' ],
	'Search'                    => [ 'Pesquisar', 'Busca', 'Buscar', 'Procurar', 'Pesquisa' ],
	'Shortpages'                => [ 'Páginas_curtas', 'Artigos_curtos' ],
	'Specialpages'              => [ 'Páginas_especiais' ],
	'Statistics'                => [ 'Estatísticas' ],
	'Tags'                      => [ 'Etiquetas' ],
	'TrackingCategories'        => [ 'Categorias_de_rastreamento', 'Monitoramento_de_Categorias' ],
	'Unblock'                   => [ 'Desbloquear' ],
	'Uncategorizedcategories'   => [ 'Categorias_não_categorizadas', 'Categorias_sem_categorias' ],
	'Uncategorizedimages'       => [ 'Ficheiros_não_categorizados', 'Imagens_não_categorizadas', 'Imagens_sem_categorias', 'Ficheiros_sem_categorias', 'Arquivos_sem_categorias' ],
	'Uncategorizedpages'        => [ 'Páginas_não_categorizadas', 'Páginas_sem_categorias', 'Artigos_sem_categorias' ],
	'Uncategorizedtemplates'    => [ 'Predefinições_não_categorizadas', 'Predefinições_sem_categorias' ],
	'Undelete'                  => [ 'Restaurar', 'Restaurar_páginas_eliminadas', 'Restaurar_artigos_eliminados' ],
	'Unlockdb'                  => [ 'Desbloquear_base_de_dados', 'Desbloquear_a_base_de_dados', 'Desbloquear_banco_de_dados' ],
	'Unusedcategories'          => [ 'Categorias_não_utilizadas', 'Categorias_sem_uso' ],
	'Unusedimages'              => [ 'Ficheiros_não_utilizados', 'Imagens_não_utilizadas' ],
	'Unusedtemplates'           => [ 'Predefinições_não_utilizadas', 'Predefinições_sem_uso' ],
	'Unwatchedpages'            => [ 'Páginas_não_vigiadas', 'Páginas_não-vigiadas', 'Artigos_não-vigiados', 'Artigos_não_vigiados' ],
	'Upload'                    => [ 'Carregar_imagem', 'Carregar_ficheiro', 'Carregar_arquivo', 'Enviar' ],
	'UploadStash'               => [ 'Envios_ocultos', 'Ficheiros_ocultos', 'Arquivos_ocultos', 'Envios_escondidos', 'Ficheiros_escondidos', 'Arquivos_escondidos' ],
	'Userlogin'                 => [ 'Entrar' ],
	'Userlogout'                => [ 'Sair' ],
	'Userrights'                => [ 'Privilégios', 'Direitos', 'Estatutos' ],
	'Version'                   => [ 'Versão', 'Sobre' ],
	'Wantedcategories'          => [ 'Categorias_pedidas', 'Categorias_em_falta', 'Categorias_inexistentes' ],
	'Wantedfiles'               => [ 'Ficheiros_pedidos', 'Imagens_pedidas', 'Ficheiros_em_falta', 'Arquivos_em_falta', 'Imagens_em_falta' ],
	'Wantedpages'               => [ 'Páginas_pedidas', 'Páginas_em_falta', 'Artigos_em_falta', 'Artigos_pedidos' ],
	'Wantedtemplates'           => [ 'Predefinições_pedidas', 'Predefinições_em_falta' ],
	'Watchlist'                 => [ 'Páginas_vigiadas', 'Artigos_vigiados', 'Vigiados' ],
	'Whatlinkshere'             => [ 'Páginas_afluentes', 'Artigos_afluentes' ],
	'Withoutinterwiki'          => [ 'Páginas_sem_interwikis', 'Artigos_sem_interwikis' ],
];

/** @phpcs-require-sorted-array */
$magicWords = [
	'anchorencode'              => [ '0', 'CODIFICAANCORA:', 'CODIFICAÂNCORA:', 'ANCHORENCODE' ],
	'basepagename'              => [ '1', 'NOMEDAPAGINABASE', 'NOMEDAPÁGINABASE', 'BASEPAGENAME' ],
	'basepagenamee'             => [ '1', 'NOMEDAPAGINABASEC', 'NOMEDAPÁGINABASEC', 'BASEPAGENAMEE' ],
	'contentlanguage'           => [ '1', 'IDIOMADOCONTEUDO', 'IDIOMADOCONTEÚDO', 'CONTENTLANGUAGE', 'CONTENTLANG' ],
	'currentday'                => [ '1', 'DIAATUAL', 'CURRENTDAY' ],
	'currentday2'               => [ '1', 'DIAATUAL2', 'CURRENTDAY2' ],
	'currentdayname'            => [ '1', 'NOMEDODIAATUAL', 'CURRENTDAYNAME' ],
	'currentdow'                => [ '1', 'DIADASEMANAATUAL', 'CURRENTDOW' ],
	'currenthour'               => [ '1', 'HORAATUAL', 'CURRENTHOUR' ],
	'currentmonth'              => [ '1', 'MESATUAL', 'MESATUAL2', 'CURRENTMONTH', 'CURRENTMONTH2' ],
	'currentmonth1'             => [ '1', 'MESATUAL1', 'CURRENTMONTH1' ],
	'currentmonthabbrev'        => [ '1', 'MESATUALABREV', 'MESATUALABREVIADO', 'ABREVIATURADOMESATUAL', 'CURRENTMONTHABBREV' ],
	'currentmonthname'          => [ '1', 'NOMEDOMESATUAL', 'CURRENTMONTHNAME' ],
	'currenttime'               => [ '1', 'HORARIOATUAL', 'CURRENTTIME' ],
	'currentversion'            => [ '1', 'REVISAOATUAL', 'REVISÃOATUAL', 'CURRENTVERSION' ],
	'currentweek'               => [ '1', 'SEMANAATUAL', 'CURRENTWEEK' ],
	'currentyear'               => [ '1', 'ANOATUAL', 'CURRENTYEAR' ],
	'defaultsort'               => [ '1', 'ORDENACAOPADRAO', 'ORDENAÇÃOPADRÃO', 'ORDEMPADRAO', 'ORDEMPADRÃO', 'DEFAULTSORT:', 'DEFAULTSORTKEY:', 'DEFAULTCATEGORYSORT:' ],
	'displaytitle'              => [ '1', 'EXIBETITULO', 'EXIBETÍTULO', 'DISPLAYTITLE' ],
	'filepath'                  => [ '0', 'CAMINHODOARQUIVO', 'FILEPATH:' ],
	'forcetoc'                  => [ '0', '__FORCARTDC__', '__FORCARSUMARIO__', '__FORÇARTDC__', '__FORÇARSUMÁRIO__', '__FORCETOC__' ],
	'fullpagename'              => [ '1', 'NOMECOMPLETODAPAGINA', 'NOMECOMPLETODAPÁGINA', 'FULLPAGENAME' ],
	'fullpagenamee'             => [ '1', 'NOMECOMPLETODAPAGINAC', 'NOMECOMPLETODAPÁGINAC', 'FULLPAGENAMEE' ],
	'fullurl'                   => [ '0', 'URLCOMPLETO:', 'FULLURL:' ],
	'fullurle'                  => [ '0', 'URLCOMPLETOC:', 'FULLURLE:' ],
	'gender'                    => [ '0', 'GENERO', 'GÊNERO', 'GENDER:' ],
	'hiddencat'                 => [ '1', '__CATEGORIAOCULTA__', '__CATOCULTA__', '__HIDDENCAT__' ],
	'img_baseline'              => [ '1', 'linhadebase', 'baseline' ],
	'img_border'                => [ '1', 'borda', 'border' ],
	'img_bottom'                => [ '1', 'abaixo', 'bottom' ],
	'img_center'                => [ '1', 'centro', 'center', 'centre' ],
	'img_framed'                => [ '1', 'commoldura', 'comborda', 'frame', 'framed', 'enframed' ],
	'img_frameless'             => [ '1', 'semmoldura', 'semborda', 'frameless' ],
	'img_left'                  => [ '1', 'esquerda', 'left' ],
	'img_link'                  => [ '1', 'ligação=$1', 'link=$1' ],
	'img_manualthumb'           => [ '1', 'miniaturadaimagem=$1', 'miniatura=$1', 'thumbnail=$1', 'thumb=$1' ],
	'img_middle'                => [ '1', 'meio', 'middle' ],
	'img_none'                  => [ '1', 'nenhum', 'none' ],
	'img_page'                  => [ '1', 'página=$1', 'página_$1', 'página $1', 'page=$1', 'page $1' ],
	'img_right'                 => [ '1', 'direita', 'right' ],
	'img_thumbnail'             => [ '1', 'miniaturadaimagem', 'miniatura', 'thumb', 'thumbnail' ],
	'img_top'                   => [ '1', 'acima', 'top' ],
	'img_upright'               => [ '1', 'superiordireito', 'superiordireito=$1', 'superiordireito_$1', 'superiordireito $1', 'upright', 'upright=$1', 'upright $1' ],
	'index'                     => [ '1', '__INDEXAR__', '__INDEX__' ],
	'language'                  => [ '0', '#IDIOMA', '#LANGUAGE' ],
	'lc'                        => [ '0', 'MINUSCULA', 'MINÚSCULA', 'MINUSCULAS', 'MINÚSCULAS', 'LC:' ],
	'lcfirst'                   => [ '0', 'PRIMEIRAMINUSCULA:', 'PRIMEIRAMINÚSCULA:', 'LCFIRST:' ],
	'localday'                  => [ '1', 'DIALOCAL', 'LOCALDAY' ],
	'localday2'                 => [ '1', 'DIALOCAL2', 'LOCALDAY2' ],
	'localdayname'              => [ '1', 'NOMEDODIALOCAL', 'LOCALDAYNAME' ],
	'localdow'                  => [ '1', 'DIADASEMANALOCAL', 'LOCALDOW' ],
	'localhour'                 => [ '1', 'HORALOCAL', 'LOCALHOUR' ],
	'localmonth'                => [ '1', 'MESLOCAL', 'LOCALMONTH', 'LOCALMONTH2' ],
	'localmonth1'               => [ '1', 'MESLOCAL1', 'LOCALMONTH1' ],
	'localmonthabbrev'          => [ '1', 'MESLOCALABREV', 'MESLOCALABREVIADO', 'ABREVIATURADOMESLOCAL', 'LOCALMONTHABBREV' ],
	'localmonthname'            => [ '1', 'NOMEDOMESLOCAL', 'LOCALMONTHNAME' ],
	'localtime'                 => [ '1', 'HORARIOLOCAL', 'LOCALTIME' ],
	'localweek'                 => [ '1', 'SEMANALOCAL', 'LOCALWEEK' ],
	'localyear'                 => [ '1', 'ANOLOCAL', 'LOCALYEAR' ],
	'namespace'                 => [ '1', 'DOMINIO', 'DOMÍNIO', 'ESPACONOMINAL', 'ESPAÇONOMINAL', 'NAMESPACE' ],
	'namespacee'                => [ '1', 'DOMINIOC', 'DOMÍNIOC', 'ESPACONOMINALC', 'ESPAÇONOMINALC', 'NAMESPACEE' ],
	'newsectionlink'            => [ '1', '__LINKDENOVASECAO__', '__LINKDENOVASEÇÃO__', '__LIGACAODENOVASECAO__', '__LIGAÇÃODENOVASEÇÃO__', '__NEWSECTIONLINK__' ],
	'nocontentconvert'          => [ '0', '__SEMCONVERTERCONTEUDO__', '__SEMCONVERTERCONTEÚDO__', '__SEMCC__', '__NOCONTENTCONVERT__', '__NOCC__' ],
	'noeditsection'             => [ '0', '__NÃOEDITARSEÇÃO__', '__SEMEDITARSEÇÃO__', '__NAOEDITARSECAO__', '__SEMEDITARSECAO__', '__NOEDITSECTION__' ],
	'nogallery'                 => [ '0', '__SEMGALERIA__', '__NOGALLERY__' ],
	'noindex'                   => [ '1', '__NAOINDEXAR__', '__NÃOINDEXAR__', '__NOINDEX__' ],
	'nonewsectionlink'          => [ '1', '__SEMLINKDENOVASECAO__', '__SEMLINKDENOVASEÇÃO__', '__SEMLIGACAODENOVASECAO__', '__SEMLIGAÇÃODENOVASEÇÃO__', '__NONEWSECTIONLINK__' ],
	'notitleconvert'            => [ '0', '__SEMCONVERTERTITULO__', '__SEMCONVERTERTÍTULO__', '__SEMCT__', '__NOTITLECONVERT__', '__NOTC__' ],
	'notoc'                     => [ '0', '__SEMTDC__', '__SEMSUMÁRIO__', '__NOTOC__' ],
	'numberingroup'             => [ '1', 'NUMERONOGRUPO', 'NÚMERONOGRUPO', 'NUMBERINGROUP', 'NUMINGROUP' ],
	'numberofactiveusers'       => [ '1', 'NUMERODEUSUARIOSATIVOS', 'NÚMERODEUSUÁRIOSATIVOS', 'NUMBEROFACTIVEUSERS' ],
	'numberofadmins'            => [ '1', 'NUMERODEADMINISTRADORES', 'NÚMERODEADMINISTRADORES', 'NUMBEROFADMINS' ],
	'numberofarticles'          => [ '1', 'NUMERODEARTIGOS', 'NÚMERODEARTIGOS', 'NUMBEROFARTICLES' ],
	'numberofedits'             => [ '1', 'NUMERODEEDICOES', 'NÚMERODEEDIÇÕES', 'NUMBEROFEDITS' ],
	'numberoffiles'             => [ '1', 'NUMERODEARQUIVOS', 'NÚMERODEARQUIVOS', 'NUMBEROFFILES' ],
	'numberofpages'             => [ '1', 'NUMERODEPAGINAS', 'NÚMERODEPÁGINAS', 'NUMBEROFPAGES' ],
	'numberofusers'             => [ '1', 'NUMERODEUSUARIOS', 'NÚMERODEUSUÁRIOS', 'NUMBEROFUSERS' ],
	'pagename'                  => [ '1', 'NOMEDAPAGINA', 'NOMEDAPÁGINA', 'PAGENAME' ],
	'pagenamee'                 => [ '1', 'NOMEDAPAGINAC', 'NOMEDAPÁGINAC', 'PAGENAMEE' ],
	'pagesincategory'           => [ '1', 'PAGINASNACATEGORIA', 'PÁGINASNACATEGORIA', 'PAGINASNACAT', 'PÁGINASNACAT', 'PAGESINCATEGORY', 'PAGESINCAT' ],
	'pagesinnamespace'          => [ '1', 'PAGINASNOESPACONOMINAL', 'PÁGINASNOESPAÇONOMINAL', 'PAGINASNODOMINIO', 'PÁGINASNODOMÍNIO', 'PAGESINNAMESPACE:', 'PAGESINNS:' ],
	'pagesize'                  => [ '1', 'TAMANHODAPAGINA', 'TAMANHODAPÁGINA', 'PAGESIZE' ],
	'protectionlevel'           => [ '1', 'NIVELDEPROTECAO', 'NÍVELDEPROTEÇÃO', 'PROTECTIONLEVEL' ],
	'redirect'                  => [ '0', '#REDIRECIONAMENTO', '#REDIRECT' ],
	'revisionday'               => [ '1', 'DIADAREVISAO', 'DIADAREVISÃO', 'REVISIONDAY' ],
	'revisionday2'              => [ '1', 'DIADAREVISAO2', 'DIADAREVISÃO2', 'REVISIONDAY2' ],
	'revisionid'                => [ '1', 'IDDAREVISAO', 'IDDAREVISÃO', 'REVISIONID' ],
	'revisionmonth'             => [ '1', 'MESDAREVISAO', 'MÊSDAREVISÃO', 'REVISIONMONTH' ],
	'revisionuser'              => [ '1', 'USUARIODAREVISAO', 'USUÁRIODAREVISÃO', 'REVISIONUSER' ],
	'revisionyear'              => [ '1', 'ANODAREVISAO', 'ANODAREVISÃO', 'REVISIONYEAR' ],
	'scriptpath'                => [ '0', 'CAMINHODOSCRIPT', 'SCRIPTPATH' ],
	'server'                    => [ '0', 'SERVIDOR', 'SERVER' ],
	'servername'                => [ '0', 'NOMEDOSERVIDOR', 'SERVERNAME' ],
	'sitename'                  => [ '1', 'NOMEDOSITE', 'NOMEDOSÍTIO', 'NOMEDOSITIO', 'SITENAME' ],
	'staticredirect'            => [ '1', '__REDIRECIONAMENTOESTATICO__', '__REDIRECIONAMENTOESTÁTICO__', '__STATICREDIRECT__' ],
	'subjectpagename'           => [ '1', 'NOMEDAPAGINADECONTEUDO', 'NOMEDAPÁGINADECONTEÚDO', 'SUBJECTPAGENAME', 'ARTICLEPAGENAME' ],
	'subjectpagenamee'          => [ '1', 'NOMEDAPAGINADECONTEUDOC', 'NOMEDAPÁGINADECONTEÚDOC', 'SUBJECTPAGENAMEE', 'ARTICLEPAGENAMEE' ],
	'subjectspace'              => [ '1', 'PAGINADECONTEUDO', 'PAGINADECONTEÚDO', 'SUBJECTSPACE', 'ARTICLESPACE' ],
	'subjectspacee'             => [ '1', 'PAGINADECONTEUDOC', 'PAGINADECONTEÚDOC', 'SUBJECTSPACEE', 'ARTICLESPACEE' ],
	'subpagename'               => [ '1', 'NOMEDASUBPAGINA', 'NOMEDASUBPÁGINA', 'SUBPAGENAME' ],
	'subpagenamee'              => [ '1', 'NOMEDASUBPAGINAC', 'NOMEDASUBPÁGINAC', 'SUBPAGENAMEE' ],
	'talkpagename'              => [ '1', 'NOMEDAPAGINADEDISCUSSAO', 'NOMEDAPÁGINADEDISCUSSÃO', 'TALKPAGENAME' ],
	'talkpagenamee'             => [ '1', 'NOMEDAPAGINADEDISCUSSAOC', 'NOMEDAPÁGINADEDISCUSSÃOC', 'TALKPAGENAMEE' ],
	'talkspace'                 => [ '1', 'PAGINADEDISCUSSAO', 'PÁGINADEDISCUSSÃO', 'TALKSPACE' ],
	'talkspacee'                => [ '1', 'PAGINADEDISCUSSAOC', 'PÁGINADEDISCUSSÃOC', 'TALKSPACEE' ],
	'toc'                       => [ '0', '__TDC__', '__SUMÁRIO__', '__SUMARIO__', '__TOC__' ],
	'uc'                        => [ '0', 'MAIUSCULA', 'MAIÚSCULA', 'MAIUSCULAS', 'MAIÚSCULAS', 'UC:' ],
	'ucfirst'                   => [ '0', 'PRIMEIRAMAIUSCULA:', 'PRIMEIRAMAIÚSCULA:', 'UCFIRST:' ],
	'urlencode'                 => [ '0', 'CODIFICAURL:', 'URLENCODE:' ],
];

<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\AutoSetup\Onboarding;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use Exception;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use WP_Post;
use WP_Query;

/**
 * AutoSetupOnboarding class for intelligent page identification and content extraction
 * for onboarding purposes. Supports multi-language content and various page builders.
 */
class AutoSetupOnboarding
{

    /**
     * Maximum number of pages to check for content extraction
     */
    private const MAX_PAGES_TO_CHECK = 20;

    /**
     * Minimum content length to consider a page valid for extraction
     */
    private const MIN_CONTENT_LENGTH = 200;

    /**
     * Minimum combined content length to consider sufficient for AI processing
     */
    private const MIN_SUFFICIENT_CONTENT_LENGTH = 250;

    /**
     * Maximum number of posts to extract for blog index sites
     */
    private const MAX_BLOG_POSTS_TO_EXTRACT = 15;

    /**
     * Site type constants
     */
    private const SITE_TYPE_BLOG_INDEX = 'blog_index';
    private const SITE_TYPE_STATIC_HOMEPAGE = 'static_homepage';

    /**
     * Priority page types for content extraction
     */
    private const PRIORITY_PAGES = [
        'home' => 100,
        'about' => 90,
        'contact' => 85,
        'services' => 80,
        'team' => 75,
        'company' => 70,
        'welcome' => 65,
        'mission' => 60,
        'vision' => 55,
        'values' => 50,
        'history' => 45,
        'products' => 40,
        'portfolio' => 35,
        'blog' => 30,
        'news' => 25,
        'events' => 20,
    ];

    /**
     * Multilingual page patterns for all 39 supported languages
     */
    private const MULTILINGUAL_PATTERNS = [
        'en' => [
            'home' => ['home', 'homepage', 'welcome', 'index', 'main'],
            'about' => ['about', 'about-us', 'who-we-are', 'our-story', 'company'],
            'contact' => ['contact', 'contact-us', 'get-in-touch', 'reach-us'],
            'services' => ['services', 'what-we-do', 'our-services', 'solutions'],
            'team' => ['team', 'our-team', 'meet-the-team', 'staff', 'people'],
            'company' => ['company', 'our-company', 'corporate', 'enterprise'],
            'welcome' => ['welcome', 'introduction', 'intro'],
            'mission' => ['mission', 'our-mission', 'purpose'],
            'vision' => ['vision', 'our-vision', 'future'],
            'values' => ['values', 'our-values', 'principles'],
            'history' => ['history', 'our-history', 'timeline'],
            'products' => ['products', 'our-products', 'catalog'],
            'portfolio' => ['portfolio', 'work', 'projects', 'case-studies'],
            'blog' => ['blog', 'news', 'articles', 'posts'],
            'news' => ['news', 'latest-news', 'announcements'],
            'events' => ['events', 'upcoming-events', 'calendar'],
        ],
        'de' => [
            'home' => ['startseite', 'home', 'willkommen'],
            'about' => ['uber-uns', 'ueber-uns', 'wir-uber-uns', 'unsere-geschichte'],
            'contact' => ['kontakt', 'kontaktieren-sie-uns', 'kontaktformular'],
            'services' => ['dienstleistungen', 'unsere-dienstleistungen', 'leistungen'],
            'team' => ['team', 'unser-team', 'mitarbeiter'],
            'company' => ['unternehmen', 'unser-unternehmen', 'firma'],
            'welcome' => ['willkommen', 'einleitung'],
            'mission' => ['mission', 'unsere-mission'],
            'vision' => ['vision', 'unsere-vision'],
            'values' => ['werte', 'unsere-werte'],
            'history' => ['geschichte', 'unsere-geschichte'],
            'products' => ['produkte', 'unsere-produkte'],
            'portfolio' => ['portfolio', 'arbeiten', 'projekte'],
            'blog' => ['blog', 'nachrichten', 'artikel'],
            'news' => ['nachrichten', 'neuigkeiten', 'ankuendigungen'],
            'events' => ['veranstaltungen', 'kommende-veranstaltungen'],
        ],
        'fr' => [
            'home' => ['accueil', 'page-d-accueil', 'bienvenue'],
            'about' => ['a-propos', 'qui-sommes-nous', 'notre-histoire'],
            'contact' => ['contact', 'nous-contacter', 'contactez-nous'],
            'services' => ['services', 'nos-services', 'ce-que-nous-faisons'],
            'team' => ['equipe', 'notre-equipe', 'rencontrez-l-equipe'],
            'company' => ['entreprise', 'notre-entreprise', 'societe'],
            'welcome' => ['bienvenue', 'introduction'],
            'mission' => ['mission', 'notre-mission'],
            'vision' => ['vision', 'notre-vision'],
            'values' => ['valeurs', 'nos-valeurs'],
            'history' => ['histoire', 'notre-histoire'],
            'products' => ['produits', 'nos-produits'],
            'portfolio' => ['portfolio', 'travaux', 'projets'],
            'blog' => ['blog', 'actualites', 'articles'],
            'news' => ['actualites', 'nouvelles', 'annonces'],
            'events' => ['evenements', 'prochains-evenements'],
        ],
        'es' => [
            'home' => ['inicio', 'pagina-principal', 'bienvenido'],
            'about' => ['acerca-de', 'quienes-somos', 'nuestra-historia', 'sobre-nosotros'],
            'contact' => ['contacto', 'contactanos', 'contactenos'],
            'services' => ['servicios', 'nuestros-servicios', 'que-hacemos'],
            'team' => ['equipo', 'nuestro-equipo', 'conoce-el-equipo'],
            'company' => ['empresa', 'nuestra-empresa', 'compania'],
            'welcome' => ['bienvenido', 'introduccion'],
            'mission' => ['mision', 'nuestra-mision'],
            'vision' => ['vision', 'nuestra-vision'],
            'values' => ['valores', 'nuestros-valores'],
            'history' => ['historia', 'nuestra-historia'],
            'products' => ['productos', 'nuestros-productos'],
            'portfolio' => ['portafolio', 'trabajos', 'proyectos'],
            'blog' => ['blog', 'noticias', 'articulos'],
            'news' => ['noticias', 'novedades', 'anuncios'],
            'events' => ['eventos', 'proximos-eventos'],
        ],
        'it' => [
            'home' => ['home', 'pagina-principale', 'benvenuto'],
            'about' => ['chi-siamo', 'riguardo-a-noi', 'la-nostra-storia'],
            'contact' => ['contatto', 'contattaci', 'contattateci'],
            'services' => ['servizi', 'i-nostri-servizi', 'cosa-facciamo'],
            'team' => ['team', 'il-nostro-team', 'incontra-il-team'],
            'company' => ['azienda', 'la-nostra-azienda', 'societa'],
            'welcome' => ['benvenuto', 'introduzione'],
            'mission' => ['missione', 'la-nostra-missione'],
            'vision' => ['visione', 'la-nostra-visione'],
            'values' => ['valori', 'i-nostri-valori'],
            'history' => ['storia', 'la-nostra-storia'],
            'products' => ['prodotti', 'i-nostri-prodotti'],
            'portfolio' => ['portfolio', 'lavori', 'progetti'],
            'blog' => ['blog', 'notizie', 'articoli'],
            'news' => ['notizie', 'novita', 'annunci'],
            'events' => ['eventi', 'prossimi-eventi'],
        ],
        'nl' => [
            'home' => ['home', 'voorpagina', 'welkom'],
            'about' => ['over-ons', 'wie-zijn-wij', 'ons-verhaal'],
            'contact' => ['contact', 'neem-contact-op', 'contactformulier'],
            'services' => ['diensten', 'onze-diensten', 'wat-we-doen'],
            'team' => ['team', 'ons-team', 'ontmoet-het-team'],
            'company' => ['bedrijf', 'ons-bedrijf', 'onderneming'],
            'welcome' => ['welkom', 'inleiding'],
            'mission' => ['missie', 'onze-missie'],
            'vision' => ['visie', 'onze-visie'],
            'values' => ['waarden', 'onze-waarden'],
            'history' => ['geschiedenis', 'onze-geschiedenis'],
            'products' => ['producten', 'onze-producten'],
            'portfolio' => ['portfolio', 'werk', 'projecten'],
            'blog' => ['blog', 'nieuws', 'artikelen'],
            'news' => ['nieuws', 'nieuwtjes', 'aankondigingen'],
            'events' => ['evenementen', 'komende-evenementen'],
        ],
        'pt' => [
            'home' => ['inicio', 'pagina-inicial', 'bem-vindo'],
            'about' => ['sobre-nos', 'quem-somos', 'nossa-historia'],
            'contact' => ['contato', 'contate-nos', 'fale-conosco'],
            'services' => ['servicos', 'nossos-servicos', 'o-que-fazemos'],
            'team' => ['equipe', 'nossa-equipe', 'conheca-a-equipe'],
            'company' => ['empresa', 'nossa-empresa', 'companhia'],
            'welcome' => ['bem-vindo', 'introducao'],
            'mission' => ['missao', 'nossa-missao'],
            'vision' => ['visao', 'nossa-visao'],
            'values' => ['valores', 'nossos-valores'],
            'history' => ['historia', 'nossa-historia'],
            'products' => ['produtos', 'nossos-produtos'],
            'portfolio' => ['portfolio', 'trabalhos', 'projetos'],
            'blog' => ['blog', 'noticias', 'artigos'],
            'news' => ['noticias', 'novidades', 'anuncios'],
            'events' => ['eventos', 'proximos-eventos'],
        ],
        'sv' => [
            'home' => ['hem', 'startsida', 'valkommen'],
            'about' => ['om-oss', 'vilka-vi-ar', 'var-historia'],
            'contact' => ['kontakt', 'kontakta-oss', 'kontaktformular'],
            'services' => ['tjanster', 'vara-tjanster', 'vad-vi-gor'],
            'team' => ['team', 'vart-team', 'personal'],
            'company' => ['foretag', 'vart-foretag', 'om-foretaget'],
            'welcome' => ['valkommen', 'introduktion'],
            'mission' => ['mission', 'var-mission', 'uppdrag'],
            'vision' => ['vision', 'var-vision'],
            'values' => ['varden', 'vara-varden'],
            'history' => ['historia', 'var-historia'],
            'products' => ['produkter', 'vara-produkter'],
            'portfolio' => ['portfolio', 'arbeten', 'projekt'],
            'blog' => ['blogg', 'nyheter', 'artiklar'],
            'news' => ['nyheter', 'senaste-nytt', 'meddelanden'],
            'events' => ['evenemang', 'kommande-evenemang'],
        ],
        'da' => [
            'home' => ['hjem', 'forside', 'velkommen'],
            'about' => ['om-os', 'hvem-vi-er', 'vores-historie'],
            'contact' => ['kontakt', 'kontakt-os', 'kontaktformular'],
            'services' => ['tjenester', 'vores-tjenester', 'hvad-vi-laver'],
            'team' => ['team', 'vores-team', 'medarbejdere'],
            'company' => ['virksomhed', 'vores-virksomhed', 'om-virksomheden'],
            'welcome' => ['velkommen', 'introduktion'],
            'mission' => ['mission', 'vores-mission'],
            'vision' => ['vision', 'vores-vision'],
            'values' => ['vaerdier', 'vores-vaerdier'],
            'history' => ['historie', 'vores-historie'],
            'products' => ['produkter', 'vores-produkter'],
            'portfolio' => ['portfolio', 'arbejder', 'projekter'],
            'blog' => ['blog', 'nyheder', 'artikler'],
            'news' => ['nyheder', 'seneste-nyt', 'meddelelser'],
            'events' => ['begivenheder', 'kommende-begivenheder'],
        ],
        'fi' => [
            'home' => ['koti', 'etusivu', 'tervetuloa'],
            'about' => ['meista', 'keitae-me-olemme', 'tarinamme'],
            'contact' => ['yhteystiedot', 'ota-yhteytta', 'yhteydenotto'],
            'services' => ['palvelut', 'palvelumme', 'mita-teemme'],
            'team' => ['tiimi', 'tiimimme', 'henkilosto'],
            'company' => ['yritys', 'yrityksemme', 'tietoa-yrityksesta'],
            'welcome' => ['tervetuloa', 'johdanto'],
            'mission' => ['missio', 'missiomme'],
            'vision' => ['visio', 'visiomme'],
            'values' => ['arvot', 'arvomme'],
            'history' => ['historia', 'historiamme'],
            'products' => ['tuotteet', 'tuotteemme'],
            'portfolio' => ['portfolio', 'tyot', 'projektit'],
            'blog' => ['blogi', 'uutiset', 'artikkelit'],
            'news' => ['uutiset', 'viimeisimmat', 'tiedotteet'],
            'events' => ['tapahtumat', 'tulevat-tapahtumat'],
        ],
        'no' => [
            'home' => ['hjem', 'forside', 'velkommen'],
            'about' => ['om-oss', 'hvem-vi-er', 'var-historie'],
            'contact' => ['kontakt', 'kontakt-oss', 'kontaktskjema'],
            'services' => ['tjenester', 'vare-tjenester', 'hva-vi-gjor'],
            'team' => ['team', 'vart-team', 'ansatte'],
            'company' => ['bedrift', 'var-bedrift', 'om-bedriften'],
            'welcome' => ['velkommen', 'introduksjon'],
            'mission' => ['misjon', 'var-misjon'],
            'vision' => ['visjon', 'var-visjon'],
            'values' => ['verdier', 'vare-verdier'],
            'history' => ['historie', 'var-historie'],
            'products' => ['produkter', 'vare-produkter'],
            'portfolio' => ['portfolio', 'arbeider', 'prosjekter'],
            'blog' => ['blogg', 'nyheter', 'artikler'],
            'news' => ['nyheter', 'siste-nytt', 'kunngjoeringer'],
            'events' => ['arrangementer', 'kommende-arrangementer'],
        ],
        'pl' => [
            'home' => ['strona-glowna', 'home', 'witamy'],
            'about' => ['o-nas', 'kim-jestesmy', 'nasza-historia'],
            'contact' => ['kontakt', 'skontaktuj-sie', 'formularz-kontaktowy'],
            'services' => ['uslugi', 'nasze-uslugi', 'co-robimy'],
            'team' => ['zespol', 'nasz-zespol', 'pracownicy'],
            'company' => ['firma', 'nasza-firma', 'o-firmie'],
            'welcome' => ['witamy', 'wprowadzenie'],
            'mission' => ['misja', 'nasza-misja'],
            'vision' => ['wizja', 'nasza-wizja'],
            'values' => ['wartosci', 'nasze-wartosci'],
            'history' => ['historia', 'nasza-historia'],
            'products' => ['produkty', 'nasze-produkty'],
            'portfolio' => ['portfolio', 'prace', 'projekty'],
            'blog' => ['blog', 'aktualnosci', 'artykuly'],
            'news' => ['aktualnosci', 'nowosci', 'ogloszenia'],
            'events' => ['wydarzenia', 'nadchodzace-wydarzenia'],
        ],
        'ro' => [
            'home' => ['acasa', 'pagina-principala', 'bun-venit'],
            'about' => ['despre-noi', 'cine-suntem', 'povestea-noastra'],
            'contact' => ['contact', 'contacteaza-ne', 'formular-contact'],
            'services' => ['servicii', 'serviciile-noastre', 'ce-facem'],
            'team' => ['echipa', 'echipa-noastra', 'personal'],
            'company' => ['companie', 'compania-noastra', 'despre-firma'],
            'welcome' => ['bun-venit', 'introducere'],
            'mission' => ['misiune', 'misiunea-noastra'],
            'vision' => ['viziune', 'viziunea-noastra'],
            'values' => ['valori', 'valorile-noastre'],
            'history' => ['istorie', 'istoria-noastra'],
            'products' => ['produse', 'produsele-noastre'],
            'portfolio' => ['portofoliu', 'lucrari', 'proiecte'],
            'blog' => ['blog', 'stiri', 'articole'],
            'news' => ['stiri', 'noutati', 'anunturi'],
            'events' => ['evenimente', 'evenimente-viitoare'],
        ],
        'tr' => [
            'home' => ['anasayfa', 'home', 'hosgeldiniz'],
            'about' => ['hakkimizda', 'biz-kimiz', 'hikayemiz'],
            'contact' => ['iletisim', 'bize-ulasin', 'iletisim-formu'],
            'services' => ['hizmetler', 'hizmetlerimiz', 'ne-yapiyoruz'],
            'team' => ['ekip', 'ekibimiz', 'calisanlar'],
            'company' => ['sirket', 'sirketimiz', 'hakkinda'],
            'welcome' => ['hosgeldiniz', 'giris'],
            'mission' => ['misyon', 'misyonumuz'],
            'vision' => ['vizyon', 'vizyonumuz'],
            'values' => ['degerler', 'degerlerimiz'],
            'history' => ['tarihce', 'tarihimiz'],
            'products' => ['urunler', 'urunlerimiz'],
            'portfolio' => ['portfolio', 'calismalar', 'projeler'],
            'blog' => ['blog', 'haberler', 'makaleler'],
            'news' => ['haberler', 'son-haberler', 'duyurular'],
            'events' => ['etkinlikler', 'yaklasan-etkinlikler'],
        ],
        'cs' => [
            'home' => ['uvod', 'hlavni-stranka', 'vitejte'],
            'about' => ['o-nas', 'kdo-jsme', 'nas-pribeh'],
            'contact' => ['kontakt', 'kontaktujte-nas', 'kontaktni-formular'],
            'services' => ['sluzby', 'nase-sluzby', 'co-delame'],
            'team' => ['tym', 'nas-tym', 'zamestnanci'],
            'company' => ['spolecnost', 'nase-spolecnost', 'o-firme'],
            'welcome' => ['vitejte', 'uvod'],
            'mission' => ['mise', 'nase-mise', 'poslani'],
            'vision' => ['vize', 'nase-vize'],
            'values' => ['hodnoty', 'nase-hodnoty'],
            'history' => ['historie', 'nase-historie'],
            'products' => ['produkty', 'nase-produkty'],
            'portfolio' => ['portfolio', 'prace', 'projekty'],
            'blog' => ['blog', 'novinky', 'clanky'],
            'news' => ['novinky', 'aktuality', 'oznameni'],
            'events' => ['akce', 'nadchazejici-akce', 'udalosti'],
        ],
        'sk' => [
            'home' => ['uvod', 'hlavna-stranka', 'vitajte'],
            'about' => ['o-nas', 'kto-sme', 'nas-pribeh'],
            'contact' => ['kontakt', 'kontaktujte-nas', 'kontaktny-formular'],
            'services' => ['sluzby', 'nase-sluzby', 'co-robime'],
            'team' => ['tim', 'nas-tim', 'zamestnanci'],
            'company' => ['spolocnost', 'nasa-spolocnost', 'o-firme'],
            'welcome' => ['vitajte', 'uvod'],
            'mission' => ['misia', 'nasa-misia', 'poslanie'],
            'vision' => ['vizia', 'nasa-vizia'],
            'values' => ['hodnoty', 'nase-hodnoty'],
            'history' => ['historia', 'nasa-historia'],
            'products' => ['produkty', 'nase-produkty'],
            'portfolio' => ['portfolio', 'prace', 'projekty'],
            'blog' => ['blog', 'novinky', 'clanky'],
            'news' => ['novinky', 'aktuality', 'oznamenia'],
            'events' => ['akcie', 'nadchadzajuce-akcie', 'udalosti'],
        ],
        'hu' => [
            'home' => ['fooldal', 'kezdolap', 'udvozoljuk'],
            'about' => ['rolunk', 'kik-vagyunk', 'tortenetunk'],
            'contact' => ['kapcsolat', 'kerjen-ajanlat', 'kapcsolatfelvetel'],
            'services' => ['szolgaltatasok', 'szolgaltatasaink', 'mit-csinlunk'],
            'team' => ['csapat', 'csapatunk', 'munkatarsak'],
            'company' => ['ceg', 'cegunk', 'cegrol'],
            'welcome' => ['udvozoljuk', 'bevezetes'],
            'mission' => ['misszio', 'missziónk', 'kuldetesunk'],
            'vision' => ['vizio', 'vízionk', 'jovokepe'],
            'values' => ['ertekek', 'ertekeink'],
            'history' => ['tortenet', 'tortenetunk'],
            'products' => ['termekek', 'termekeink'],
            'portfolio' => ['portfolio', 'munkak', 'projektek'],
            'blog' => ['blog', 'hirek', 'cikkek'],
            'news' => ['hirek', 'ujdonsagok', 'kozlemenyek'],
            'events' => ['esemenyek', 'kozelgo-esemenyek'],
        ],
        'bg' => [
            'home' => ['nachalo', 'nachalna-stranitsa', 'dobre-doshli'],
            'about' => ['za-nas', 'koi-sme-nie', 'nashata-istoriya'],
            'contact' => ['kontakti', 'svurzhete-se-s-nas', 'kontaktna-forma'],
            'services' => ['uslugi', 'nashite-uslugi', 'kakvo-pravim'],
            'team' => ['ekip', 'nashiyat-ekip', 'sluzhiteli'],
            'company' => ['kompaniya', 'nashata-kompaniya', 'za-firmata'],
            'welcome' => ['dobre-doshli', 'vuvedenie'],
            'mission' => ['misiya', 'nashata-misiya'],
            'vision' => ['viziya', 'nashata-viziya'],
            'values' => ['tsennosti', 'nashite-tsennosti'],
            'history' => ['istoriya', 'nashata-istoriya'],
            'products' => ['produkti', 'nashite-produkti'],
            'portfolio' => ['portfolio', 'raboti', 'proekti'],
            'blog' => ['blog', 'novini', 'statii'],
            'news' => ['novini', 'posledni-novini', 'suobshteniya'],
            'events' => ['subitiya', 'predstoyashti-subitiya'],
        ],
        'el' => [
            'home' => ['arxiki', 'kentriki-selida', 'kalosorisat'],
            'about' => ['sxetika', 'poioi-eimaste', 'i-istoria-mas'],
            'contact' => ['epikoinonia', 'epikoinoniste', 'forma-epikoinonias'],
            'services' => ['ypiresies', 'oi-ypiresies-mas', 'ti-kanoume'],
            'team' => ['omada', 'i-omada-mas', 'prosopioko'],
            'company' => ['etaireia', 'i-etaireia-mas', 'gia-tin-etaireia'],
            'welcome' => ['kalosorisat', 'eisagogi'],
            'mission' => ['apostoli', 'i-apostoli-mas'],
            'vision' => ['orama', 'to-orama-mas'],
            'values' => ['axies', 'oi-axies-mas'],
            'history' => ['istoria', 'i-istoria-mas'],
            'products' => ['proionta', 'ta-proionta-mas'],
            'portfolio' => ['portfolio', 'erga', 'ergasies'],
            'blog' => ['blog', 'nea', 'arthra'],
            'news' => ['nea', 'teleftaia-nea', 'anakoinoseis'],
            'events' => ['ekdiloseis', 'erxomenes-ekdiloseis'],
        ],
        'et' => [
            'home' => ['avaleht', 'pealeht', 'tere-tulemast'],
            'about' => ['meist', 'kes-me-oleme', 'meie-lugu'],
            'contact' => ['kontakt', 'vota-uhendust', 'kontaktivorm'],
            'services' => ['teenused', 'meie-teenused', 'mida-teeme'],
            'team' => ['meeskond', 'meie-meeskond', 'tootajad'],
            'company' => ['ettevote', 'meie-ettevote', 'firmast'],
            'welcome' => ['tere-tulemast', 'sissejuhatus'],
            'mission' => ['missioon', 'meie-missioon'],
            'vision' => ['visioon', 'meie-visioon'],
            'values' => ['vaartused', 'meie-vaartused'],
            'history' => ['ajalugu', 'meie-ajalugu'],
            'products' => ['tooted', 'meie-tooted'],
            'portfolio' => ['portfolio', 'tood', 'projektid'],
            'blog' => ['blogi', 'uudised', 'artiklid'],
            'news' => ['uudised', 'viimased-uudised', 'teated'],
            'events' => ['sundmused', 'tulevased-sundmused'],
        ],
        'lv' => [
            'home' => ['sakums', 'galvena-lapa', 'laipni-ludzam'],
            'about' => ['par-mums', 'kas-mes-esam', 'musu-stasts'],
            'contact' => ['kontakti', 'sazinaties', 'kontaktu-forma'],
            'services' => ['pakalpojumi', 'musu-pakalpojumi', 'ko-mes-daram'],
            'team' => ['komanda', 'musu-komanda', 'darbinieki'],
            'company' => ['uznemums', 'musu-uznemums', 'par-uznemumu'],
            'welcome' => ['laipni-ludzam', 'ievads'],
            'mission' => ['misija', 'musu-misija'],
            'vision' => ['vizija', 'musu-vizija'],
            'values' => ['vertibas', 'musu-vertibas'],
            'history' => ['vesture', 'musu-vesture'],
            'products' => ['produkti', 'musu-produkti'],
            'portfolio' => ['portfolio', 'darbi', 'projekti'],
            'blog' => ['blogs', 'jaunumi', 'raksti'],
            'news' => ['jaunumi', 'jaunakie-jaunumi', 'pazinojumi'],
            'events' => ['pasakumi', 'tuvojoties-pasakumi'],
        ],
        'lt' => [
            'home' => ['pradzia', 'pagrindinis-puslapis', 'sveiki'],
            'about' => ['apie-mus', 'kas-mes-esame', 'musu-istorija'],
            'contact' => ['kontaktai', 'susisiekite', 'kontaktu-forma'],
            'services' => ['paslaugos', 'musu-paslaugos', 'ka-mes-darome'],
            'team' => ['komanda', 'musu-komanda', 'darbuotojai'],
            'company' => ['imone', 'musu-imone', 'apie-imone'],
            'welcome' => ['sveiki', 'ivadas'],
            'mission' => ['misija', 'musu-misija'],
            'vision' => ['vizija', 'musu-vizija'],
            'values' => ['vertybes', 'musu-vertybes'],
            'history' => ['istorija', 'musu-istorija'],
            'products' => ['produktai', 'musu-produktai'],
            'portfolio' => ['portfolio', 'darbai', 'projektai'],
            'blog' => ['blogas', 'naujienos', 'straipsniai'],
            'news' => ['naujienos', 'naujausios-naujienos', 'pranesimai'],
            'events' => ['renginiai', 'artejantys-renginiai'],
        ],
        'sl' => [
            'home' => ['domov', 'zacetna-stran', 'dobrodosli'],
            'about' => ['o-nas', 'kdo-smo', 'nasa-zgodba'],
            'contact' => ['kontakt', 'kontaktirajte-nas', 'kontaktni-obrazec'],
            'services' => ['storitve', 'nase-storitve', 'kaj-delamo'],
            'team' => ['ekipa', 'nasa-ekipa', 'zaposleni'],
            'company' => ['podjetje', 'nase-podjetje', 'o-podjetju'],
            'welcome' => ['dobrodosli', 'uvod'],
            'mission' => ['poslanstvo', 'nase-poslanstvo'],
            'vision' => ['vizija', 'nasa-vizija'],
            'values' => ['vrednote', 'nase-vrednote'],
            'history' => ['zgodovina', 'nasa-zgodovina'],
            'products' => ['izdelki', 'nasi-izdelki'],
            'portfolio' => ['portfolio', 'dela', 'projekti'],
            'blog' => ['blog', 'novice', 'clanki'],
            'news' => ['novice', 'zadnje-novice', 'obvestila'],
            'events' => ['dogodki', 'prihajajoci-dogodki'],
        ],
        'hr' => [
            'home' => ['pocetna', 'naslovnica', 'dobrodosli'],
            'about' => ['o-nama', 'tko-smo', 'nasa-prica'],
            'contact' => ['kontakt', 'kontaktirajte-nas', 'kontakt-obrazac'],
            'services' => ['usluge', 'nase-usluge', 'sto-radimo'],
            'team' => ['tim', 'nas-tim', 'zaposlenici'],
            'company' => ['tvrtka', 'nasa-tvrtka', 'o-tvrtki'],
            'welcome' => ['dobrodosli', 'uvod'],
            'mission' => ['misija', 'nasa-misija'],
            'vision' => ['vizija', 'nasa-vizija'],
            'values' => ['vrijednosti', 'nase-vrijednosti'],
            'history' => ['povijest', 'nasa-povijest'],
            'products' => ['proizvodi', 'nasi-proizvodi'],
            'portfolio' => ['portfolio', 'radovi', 'projekti'],
            'blog' => ['blog', 'vijesti', 'clanci'],
            'news' => ['vijesti', 'najnovije-vijesti', 'objave'],
            'events' => ['dogadanja', 'nadolazeca-dogadanja'],
        ],
        'sr' => [
            'home' => ['pocetna', 'naslovna', 'dobrodosli'],
            'about' => ['o-nama', 'ko-smo', 'nasa-prica'],
            'contact' => ['kontakt', 'kontaktirajte-nas', 'kontakt-forma'],
            'services' => ['usluge', 'nase-usluge', 'sta-radimo'],
            'team' => ['tim', 'nas-tim', 'zaposleni'],
            'company' => ['kompanija', 'nasa-kompanija', 'o-firmi'],
            'welcome' => ['dobrodosli', 'uvod'],
            'mission' => ['misija', 'nasa-misija'],
            'vision' => ['vizija', 'nasa-vizija'],
            'values' => ['vrednosti', 'nase-vrednosti'],
            'history' => ['istorija', 'nasa-istorija'],
            'products' => ['proizvodi', 'nasi-proizvodi'],
            'portfolio' => ['portfolio', 'radovi', 'projekti'],
            'blog' => ['blog', 'vesti', 'clanci'],
            'news' => ['vesti', 'najnovije-vesti', 'obavestenja'],
            'events' => ['dogadjaji', 'predstojecii-dogadjaji'],
        ],
        'mk' => [
            'home' => ['pocetna', 'glavna-stranica', 'dobrodojdovte'],
            'about' => ['za-nas', 'koi-sme', 'nasata-prikazna'],
            'contact' => ['kontakt', 'kontaktirajte-ne', 'kontakt-forma'],
            'services' => ['uslugi', 'nasi-uslugi', 'sto-pravime'],
            'team' => ['tim', 'nas-tim', 'vraboteni'],
            'company' => ['kompanija', 'nasa-kompanija', 'za-firmata'],
            'welcome' => ['dobrodojdovte', 'voved'],
            'mission' => ['misija', 'nasa-misija'],
            'vision' => ['vizija', 'nasa-vizija'],
            'values' => ['vrednosti', 'nasi-vrednosti'],
            'history' => ['istorija', 'nasa-istorija'],
            'products' => ['proizvodi', 'nasi-proizvodi'],
            'portfolio' => ['portfolio', 'raboti', 'proekti'],
            'blog' => ['blog', 'novosti', 'statii'],
            'news' => ['novosti', 'najnovi-novosti', 'izvestuvanja'],
            'events' => ['nastani', 'idni-nastani'],
        ],
        'sq' => [
            'home' => ['fillimi', 'faqja-kryesore', 'mireserddhjet'],
            'about' => ['rreth-nesh', 'kush-jemi', 'historia-jone'],
            'contact' => ['kontakt', 'na-kontaktoni', 'formulari-kontaktit'],
            'services' => ['sherbime', 'sherbimet-tona', 'cfare-bejme'],
            'team' => ['ekipi', 'ekipi-yne', 'punonjesit'],
            'company' => ['kompania', 'kompania-jone', 'rreth-kompanise'],
            'welcome' => ['mireserddhjet', 'hyrje'],
            'mission' => ['misioni', 'misioni-yne'],
            'vision' => ['vizioni', 'vizioni-yne'],
            'values' => ['vlerat', 'vlerat-tona'],
            'history' => ['historia', 'historia-jone'],
            'products' => ['produkte', 'produktet-tona'],
            'portfolio' => ['portfolio', 'pune', 'projekte'],
            'blog' => ['blog', 'lajme', 'artikuj'],
            'news' => ['lajme', 'lajmet-e-fundit', 'njoftime'],
            'events' => ['ngjarje', 'ngjarjet-e-ardhshme'],
        ],
        'ru' => [
            'home' => ['glavnaya', 'domashnyaya', 'dobro-pozhalovat'],
            'about' => ['o-nas', 'kto-my', 'nasha-istoriya'],
            'contact' => ['kontakty', 'svyazatsya-s-nami', 'kontaktnaya-forma'],
            'services' => ['uslugi', 'nashi-uslugi', 'chto-my-delajem'],
            'team' => ['komanda', 'nasha-komanda', 'vstrechajte-komandu'],
            'company' => ['kompaniya', 'nasha-kompaniya', 'predpriyatie'],
            'welcome' => ['dobro-pozhalovat', 'vvedenie'],
            'mission' => ['missiya', 'nasha-missiya'],
            'vision' => ['videnie', 'nasha-videnie'],
            'values' => ['tsennosti', 'nashi-tsennosti'],
            'history' => ['istoriya', 'nasha-istoriya'],
            'products' => ['produkty', 'nashi-produkty'],
            'portfolio' => ['portfolio', 'raboty', 'proekty'],
            'blog' => ['blog', 'novosti', 'statji'],
            'news' => ['novosti', 'poslednie-novosti', 'obyavleniya'],
            'events' => ['sobitiya', 'predstoyashchie-sobitiya'],
        ],
        'uk' => [
            'home' => ['golovna', 'pochatkova', 'laskavo-prosymo'],
            'about' => ['pro-nas', 'hto-my', 'nasha-istoriya'],
            'contact' => ['kontakty', 'zviazatysia', 'kontaktna-forma'],
            'services' => ['poslugy', 'nashi-poslugy', 'shcho-my-robimo'],
            'team' => ['komanda', 'nasha-komanda', 'spivrobitniky'],
            'company' => ['kompaniya', 'nasha-kompaniya', 'pro-firmu'],
            'welcome' => ['laskavo-prosymo', 'vstup'],
            'mission' => ['misiya', 'nasha-misiya'],
            'vision' => ['viziya', 'nasha-viziya'],
            'values' => ['tsinnosti', 'nashi-tsinnosti'],
            'history' => ['istoriya', 'nasha-istoriya'],
            'products' => ['produkty', 'nashi-produkty'],
            'portfolio' => ['portfolio', 'roboty', 'proekty'],
            'blog' => ['blog', 'novyny', 'statti'],
            'news' => ['novyny', 'ostanni-novyny', 'ogoloshennya'],
            'events' => ['podiyi', 'majbutni-podiyi'],
        ],
        'ja' => [
            'home' => ['home', 'top', 'welcome', 'shoukai'],
            'about' => ['about', 'company', 'about-us', 'kaisha-gaiyou'],
            'contact' => ['contact', 'contact-us', 'inquiry', 'toiawase'],
            'services' => ['services', 'service', 'what-we-do', 'jigyou'],
            'team' => ['team', 'our-team', 'member', 'sutaffu'],
            'company' => ['company', 'corporate', 'enterprise', 'kaisha'],
            'welcome' => ['welcome', 'introduction', 'shoukai'],
            'mission' => ['mission', 'our-mission', 'rinen'],
            'vision' => ['vision', 'our-vision', 'mirai'],
            'values' => ['values', 'our-values', 'kachi'],
            'history' => ['history', 'our-history', 'enkaku'],
            'products' => ['products', 'our-products', 'seihin'],
            'portfolio' => ['portfolio', 'works', 'projects', 'jisseki'],
            'blog' => ['blog', 'news', 'article', 'oshirase'],
            'news' => ['news', 'topics', 'announcement', 'nyuusu'],
            'events' => ['events', 'event', 'schedule', 'ibento'],
        ],
        'zh' => [
            'home' => ['home', 'index', 'shouye', 'zhuye'],
            'about' => ['about', 'about-us', 'guanyu', 'guanyuwomen'],
            'contact' => ['contact', 'lianxi', 'lianxiwomen'],
            'services' => ['services', 'fuwu', 'womenfuwu'],
            'team' => ['team', 'tuandui', 'womentuandui'],
            'company' => ['company', 'gongsi', 'qiye'],
            'welcome' => ['welcome', 'huanying'],
            'mission' => ['mission', 'shiming', 'womenshiming'],
            'vision' => ['vision', 'yuanjing', 'womenyuanjing'],
            'values' => ['values', 'jiazhiguan'],
            'history' => ['history', 'lishi', 'fazhanlicheng'],
            'products' => ['products', 'chanpin', 'womenchanpin'],
            'portfolio' => ['portfolio', 'zuopin', 'xiangmu'],
            'blog' => ['blog', 'boke', 'xinwen'],
            'news' => ['news', 'xinwen', 'zuixinxinwen'],
            'events' => ['events', 'huodong', 'jinqihuodong'],
        ],
        'ko' => [
            'home' => ['home', 'main', 'chome', 'sijak'],
            'about' => ['about', 'about-us', 'soge', 'hoesa'],
            'contact' => ['contact', 'munui', 'yeonrak'],
            'services' => ['services', 'seobiseu', 'uriui'],
            'team' => ['team', 'tim', 'jikwon'],
            'company' => ['company', 'hoesa', 'gieob'],
            'welcome' => ['welcome', 'hwanyeong'],
            'mission' => ['mission', 'sa-myeong'],
            'vision' => ['vision', 'bijeun'],
            'values' => ['values', 'gachi'],
            'history' => ['history', 'yeonhyeok'],
            'products' => ['products', 'jepum'],
            'portfolio' => ['portfolio', 'poteupol'],
            'blog' => ['blog', 'beullogeu', 'sosik'],
            'news' => ['news', 'nyuseu', 'sosiK'],
            'events' => ['events', 'haengsa', 'iljeong'],
        ],
        'ar' => [
            'home' => ['home', 'alraiyisiya', 'ahlan'],
            'about' => ['about', 'hawlana', 'man-nahnu'],
            'contact' => ['contact', 'itasal-bina', 'tawasul'],
            'services' => ['services', 'khadamatuna', 'alkhadamat'],
            'team' => ['team', 'fariquna', 'alfariq'],
            'company' => ['company', 'alsharikat', 'almunazama'],
            'welcome' => ['welcome', 'marhaba', 'ahlan'],
            'mission' => ['mission', 'risalatuna', 'alrisala'],
            'vision' => ['vision', 'ruyatuna', 'alruya'],
            'values' => ['values', 'qiyamuna', 'alqiyam'],
            'history' => ['history', 'tarikhuna', 'altarikh'],
            'products' => ['products', 'almuntajat', 'muntajatuna'],
            'portfolio' => ['portfolio', 'aamaluna', 'masharia'],
            'blog' => ['blog', 'almudawwana', 'akhbar'],
            'news' => ['news', 'akhbar', 'jadid'],
            'events' => ['events', 'ahdath', 'faaliyat'],
        ],
        'he' => [
            'home' => ['home', 'bayit', 'rashi'],
            'about' => ['about', 'odotenu', 'mi-anachnu'],
            'contact' => ['contact', 'kesher', 'tzor-kesher'],
            'services' => ['services', 'sherutim', 'sherutenu'],
            'team' => ['team', 'tzevet', 'hatzevet-shelanu'],
            'company' => ['company', 'hachevra', 'chevra'],
            'welcome' => ['welcome', 'bruchim-habaim'],
            'mission' => ['mission', 'hayiud-shelanu', 'masa'],
            'vision' => ['vision', 'hazon', 'hahazon-shelanu'],
            'values' => ['values', 'arachim', 'haarachim-shelanu'],
            'history' => ['history', 'historia', 'hahistoria-shelanu'],
            'products' => ['products', 'mutzarim', 'hamutzarim-shelanu'],
            'portfolio' => ['portfolio', 'tik-avoda', 'proyektim'],
            'blog' => ['blog', 'yoman', 'hadashot'],
            'news' => ['news', 'hadashot', 'hodaot'],
            'events' => ['events', 'eruim', 'eruim-kruvim'],
        ],
        'hi' => [
            'home' => ['home', 'mukhprishth', 'swagat'],
            'about' => ['about', 'hamare-bare-mein', 'parichay'],
            'contact' => ['contact', 'sampark', 'sampark-karen'],
            'services' => ['services', 'sevayen', 'hamari-sevayen'],
            'team' => ['team', 'team', 'hamari-team'],
            'company' => ['company', 'kampani', 'hamari-kampani'],
            'welcome' => ['welcome', 'swagat'],
            'mission' => ['mission', 'dhyey', 'hamara-dhyey'],
            'vision' => ['vision', 'drishti', 'hamari-drishti'],
            'values' => ['values', 'mulya', 'hamare-mulya'],
            'history' => ['history', 'itihas', 'hamara-itihas'],
            'products' => ['products', 'utpad', 'hamare-utpad'],
            'portfolio' => ['portfolio', 'kary', 'pariyojana'],
            'blog' => ['blog', 'blog', 'samachar'],
            'news' => ['news', 'samachar', 'ghoshana'],
            'events' => ['events', 'karyakram', 'aagami-karyakram'],
        ],
        'th' => [
            'home' => ['home', 'naa-raek', 'yindee-tonrab'],
            'about' => ['about', 'kiew-kab-rao', 'prawat'],
            'contact' => ['contact', 'tit-tor', 'tit-tor-rao'],
            'services' => ['services', 'borigar', 'borigar-kong-rao'],
            'team' => ['team', 'team', 'team-kong-rao'],
            'company' => ['company', 'borisat', 'borisat-kong-rao'],
            'welcome' => ['welcome', 'yindee-tonrab'],
            'mission' => ['mission', 'parakid', 'parakid-kong-rao'],
            'vision' => ['vision', 'wisaitat', 'wisaitat-kong-rao'],
            'values' => ['values', 'kha-niyom', 'kha-niyom-kong-rao'],
            'history' => ['history', 'prawat', 'prawat-kong-rao'],
            'products' => ['products', 'phalit-ta-phan', 'sin-kha'],
            'portfolio' => ['portfolio', 'phon-ngan', 'project'],
            'blog' => ['blog', 'blog', 'khao'],
            'news' => ['news', 'khao', 'khao-mai'],
            'events' => ['events', 'kijakam', 'kijakam-thi-ja-ma'],
        ],
        'vi' => [
            'home' => ['trang-chu', 'home', 'chao-mung'],
            'about' => ['ve-chung-toi', 'gioi-thieu', 'chung-toi-la-ai'],
            'contact' => ['lien-he', 'lien-lac', 'form-lien-he'],
            'services' => ['dich-vu', 'dich-vu-cua-chung-toi', 'chung-toi-lam-gi'],
            'team' => ['doi-ngu', 'doi-ngu-cua-chung-toi', 'nhan-vien'],
            'company' => ['cong-ty', 'cong-ty-cua-chung-toi', 've-cong-ty'],
            'welcome' => ['chao-mung', 'gioi-thieu'],
            'mission' => ['su-menh', 'su-menh-cua-chung-toi'],
            'vision' => ['tam-nhin', 'tam-nhin-cua-chung-toi'],
            'values' => ['gia-tri', 'gia-tri-cua-chung-toi'],
            'history' => ['lich-su', 'lich-su-cua-chung-toi'],
            'products' => ['san-pham', 'san-pham-cua-chung-toi'],
            'portfolio' => ['portfolio', 'du-an', 'cong-trinh'],
            'blog' => ['blog', 'tin-tuc', 'bai-viet'],
            'news' => ['tin-tuc', 'tin-moi', 'thong-bao'],
            'events' => ['su-kien', 'su-kien-sap-toi'],
        ],
        'id' => [
            'home' => ['beranda', 'home', 'selamat-datang'],
            'about' => ['tentang-kami', 'siapa-kami', 'cerita-kami'],
            'contact' => ['kontak', 'hubungi-kami', 'formulir-kontak'],
            'services' => ['layanan', 'layanan-kami', 'apa-yang-kami-lakukan'],
            'team' => ['tim', 'tim-kami', 'karyawan'],
            'company' => ['perusahaan', 'perusahaan-kami', 'tentang-perusahaan'],
            'welcome' => ['selamat-datang', 'pengantar'],
            'mission' => ['misi', 'misi-kami'],
            'vision' => ['visi', 'visi-kami'],
            'values' => ['nilai', 'nilai-kami'],
            'history' => ['sejarah', 'sejarah-kami'],
            'products' => ['produk', 'produk-kami'],
            'portfolio' => ['portfolio', 'karya', 'proyek'],
            'blog' => ['blog', 'berita', 'artikel'],
            'news' => ['berita', 'berita-terbaru', 'pengumuman'],
            'events' => ['acara', 'acara-mendatang'],
        ],
        'ms' => [
            'home' => ['utama', 'home', 'selamat-datang'],
            'about' => ['tentang-kami', 'siapa-kami', 'kisah-kami'],
            'contact' => ['hubungi', 'hubungi-kami', 'borang-hubungi'],
            'services' => ['perkhidmatan', 'perkhidmatan-kami', 'apa-yang-kami-buat'],
            'team' => ['pasukan', 'pasukan-kami', 'kakitangan'],
            'company' => ['syarikat', 'syarikat-kami', 'tentang-syarikat'],
            'welcome' => ['selamat-datang', 'pengenalan'],
            'mission' => ['misi', 'misi-kami'],
            'vision' => ['visi', 'visi-kami'],
            'values' => ['nilai', 'nilai-kami'],
            'history' => ['sejarah', 'sejarah-kami'],
            'products' => ['produk', 'produk-kami'],
            'portfolio' => ['portfolio', 'kerja', 'projek'],
            'blog' => ['blog', 'berita', 'artikel'],
            'news' => ['berita', 'berita-terkini', 'pengumuman'],
            'events' => ['acara', 'acara-akan-datang'],
        ],
    ];

    /**
     * Pages to exclude from content extraction.
     * Includes common system pages and WordPress default pages in multiple languages.
     *
     * Organized by:
     * 1. Common system pages in English
     * 2. WordPress default pages (sample-page, hello-world) in all languages
     * 3. System page translations for main European languages (de_DE, fr_FR, es_ES, it_IT, pt_PT, nl_NL, pl_PL, ro_RO, cs_CZ, sk_SK)
     */
    private const EXCLUDED_PAGES = [
        // =====================================================================
        // COMMON SYSTEM PAGES - English (en)
        // =====================================================================
        'shop', 'cart', 'checkout', 'account', 'login', 'register', 'lost-password',
        'search', '404', 'error', 'privacy-policy', 'terms-of-service', 'cookie-policy',
        'sitemap', 'feed', 'rss', 'admin', 'wp-admin', 'dashboard', 'profile',
        'logout', 'reset-password', 'confirm-email', 'unsubscribe', 'subscribe',
        'thank-you', 'success', 'error-page', 'maintenance', 'coming-soon',

        // =====================================================================
        // WORDPRESS DEFAULT PAGES (sample-page, hello-world) - All Languages
        // =====================================================================

        // English (en)
        'sample-page', 'hello-world',

        // German (de_DE)
        'beispiel-seite', 'hallo-welt',

        // French (fr_FR)
        'page-exemple', 'bonjour-tout-le-monde',

        // Spanish (es_ES)
        'pagina-de-ejemplo', 'hola-mundo',

        // Italian (it_IT)
        'pagina-di-esempio', 'ciao-mondo',

        // Portuguese (pt_PT/pt_BR)
        'pagina-de-exemplo', 'ola-mundo',

        // Dutch (nl_NL)
        'voorbeeldpagina', 'hallo-wereld',

        // Polish (pl_PL)
        'strona-przykladowa', 'witaj-swiecie',

        // Romanian (ro_RO)
        'pagina-exemplu', 'salut-lume',

        // Russian (ru_RU)
        'primer-stranicy', 'privet-mir',

        // Swedish (sv_SE)
        'exempelsida', 'hej-varlden',

        // Danish (da_DK)
        'eksempelside', 'hej-verden',

        // Norwegian (nb_NO)
        'eksempelside', 'hei-verden',

        // Finnish (fi)
        'mallisivu', 'hei-maailma',

        // Czech (cs_CZ)
        'ukazkova-stranka', 'ahoj-svete', 'ahoj-vsichni',

        // Slovak (sk_SK)
        'vzorova-stranka', 'ahoj-svet',

        // Hungarian (hu_HU)
        'minta-oldal', 'hello-vilag',

        // Turkish (tr_TR)
        'ornek-sayfa', 'merhaba-dunya',

        // Japanese (ja)
        'サンプルページ',

        // Chinese Simplified (zh_CN)
        '示例页面', 'ni-hao-shi-jie',

        // Korean (ko_KR)
        '샘플-페이지', 'annyeong-sesang',

        // Arabic (ar)
        'صفحة-نموذجية', 'مرحبا-بالعالم',

        // Hebrew (he_IL)
        'דף-לדוגמה', 'שלום-עולם',

        // Greek (el)
        'δείγμα-σελίδας', 'γεια-σου-κόσμε',

        // =====================================================================
        // SYSTEM PAGE TRANSLATIONS - German (de_DE)
        // =====================================================================
        'magazin',           // shop
        'warenkorb',         // cart
        'kasse',             // checkout
        'konto',             // account
        'anmelden',          // login
        'registrieren',      // register
        'passwort-vergessen', // lost-password
        'suche',             // search
        'datenschutz',       // privacy-policy
        'agb',               // terms-of-service
        'cookie-richtlinie', // cookie-policy
        'danke',             // thank-you
        'wartung',           // maintenance
        'demnachst',         // coming-soon

        // =====================================================================
        // SYSTEM PAGE TRANSLATIONS - French (fr_FR)
        // =====================================================================
        'boutique',                      // shop
        'panier',                        // cart
        'paiement',                      // checkout
        'compte',                        // account
        'connexion',                     // login
        'inscription',                   // register
        'mot-de-passe-perdu',            // lost-password
        'recherche',                     // search
        'politique-de-confidentialite',  // privacy-policy
        'conditions-generales',          // terms-of-service
        'politique-de-cookies',          // cookie-policy
        'merci',                         // thank-you
        'maintenance',                   // maintenance
        'bientot',                       // coming-soon

        // =====================================================================
        // SYSTEM PAGE TRANSLATIONS - Spanish (es_ES)
        // =====================================================================
        'tienda',                    // shop
        'carrito',                   // cart
        'pago',                      // checkout
        'cuenta',                    // account
        'iniciar-sesion',            // login
        'registro',                  // register
        'contrasena-perdida',        // lost-password
        'buscar',                    // search
        'politica-de-privacidad',    // privacy-policy
        'terminos-de-servicio',      // terms-of-service
        'politica-de-cookies',       // cookie-policy
        'gracias',                   // thank-you
        'mantenimiento',             // maintenance
        'proximamente',              // coming-soon

        // =====================================================================
        // SYSTEM PAGE TRANSLATIONS - Italian (it_IT)
        // =====================================================================
        'negozio',                   // shop
        'carrello',                  // cart
        'cassa',                     // checkout
        // 'account' same as English
        'accedi',                    // login
        'registrati',                // register
        'password-dimenticata',      // lost-password
        'cerca',                     // search
        'privacy',                   // privacy-policy
        'termini-di-servizio',       // terms-of-service
        // 'cookie-policy' same as English
        'grazie',                    // thank-you
        'manutenzione',              // maintenance
        'prossimamente',             // coming-soon

        // =====================================================================
        // SYSTEM PAGE TRANSLATIONS - Portuguese (pt_PT/pt_BR)
        // =====================================================================
        'loja',                      // shop
        'carrinho',                  // cart
        'pagamento',                 // checkout
        'conta',                     // account
        'entrar',                    // login
        'registrar',                 // register
        'senha-perdida',             // lost-password
        'pesquisa',                  // search
        'privacidade',               // privacy-policy
        'termos-de-servico',         // terms-of-service
        'politica-de-cookies',       // cookie-policy (same as Spanish)
        'obrigado',                  // thank-you
        'manutencao',                // maintenance
        'em-breve',                  // coming-soon

        // =====================================================================
        // SYSTEM PAGE TRANSLATIONS - Dutch (nl_NL)
        // =====================================================================
        'winkel',                    // shop
        'winkelwagen',               // cart
        'afrekenen',                 // checkout
        // 'account' same as English
        'inloggen',                  // login
        'registreren',               // register
        'wachtwoord-vergeten',       // lost-password
        'zoeken',                    // search
        'privacybeleid',             // privacy-policy
        'algemene-voorwaarden',      // terms-of-service
        'cookiebeleid',              // cookie-policy
        'bedankt',                   // thank-you
        'onderhoud',                 // maintenance
        'binnenkort',                // coming-soon

        // =====================================================================
        // SYSTEM PAGE TRANSLATIONS - Polish (pl_PL)
        // =====================================================================
        'sklep',                     // shop
        'koszyk',                    // cart
        'zamowienie',                // checkout
        // 'konto' same as German
        'logowanie',                 // login
        'rejestracja',               // register
        'zapomniane-haslo',          // lost-password
        'szukaj',                    // search
        'polityka-prywatnosci',      // privacy-policy
        'regulamin',                 // terms-of-service
        'polityka-cookies',          // cookie-policy
        'dziekujemy',                // thank-you
        'konserwacja',               // maintenance
        'wkrotce',                   // coming-soon

        // =====================================================================
        // SYSTEM PAGE TRANSLATIONS - Romanian (ro_RO)
        // =====================================================================
        'magazin',                       // shop (same as German)
        'cos',                           // cart
        'plata',                         // checkout
        'cont',                          // account
        'autentificare',                 // login
        'inregistrare',                  // register
        'parola-uitata',                 // lost-password
        'cautare',                       // search
        'politica-confidentialitate',    // privacy-policy
        'termeni-si-conditii',           // terms-of-service
        'politica-cookie',               // cookie-policy
        'multumim',                      // thank-you
        'mentenanta',                    // maintenance
        'in-curand',                     // coming-soon

        // =====================================================================
        // SYSTEM PAGE TRANSLATIONS - Czech (cs_CZ)
        // =====================================================================
        'obchod',                        // shop
        'kosik',                         // cart
        'pokladna',                      // checkout
        'ucet',                          // account
        'muj-ucet',                      // my-account
        'prihlaseni',                    // login
        'registrace',                    // register
        'zapomenute-heslo',              // lost-password
        'hledat',                        // search
        'vyhledavani',                   // search
        'ochrana-osobnich-udaju',        // privacy-policy
        'zasady-ochrany-osobnich-udaju', // privacy-policy
        'obchodni-podminky',             // terms-of-service
        'zasady-pouzivani-cookies',      // cookie-policy
        'dekujeme',                      // thank-you
        'udrzba',                        // maintenance
        'jiz-brzy',                      // coming-soon

        // =====================================================================
        // SYSTEM PAGE TRANSLATIONS - Slovak (sk_SK)
        // =====================================================================
        // 'obchod', 'kosik', 'pokladna', 'ucet', 'udrzba' same as Czech
        'moj-ucet',                      // my-account
        'prihlasenie',                   // login
        'registracia',                   // register
        'zabudnute-heslo',               // lost-password
        'hladat',                        // search
        'vyhladavanie',                  // search
        'ochrana-osobnych-udajov',       // privacy-policy
        'zasady-ochrany-osobnych-udajov', // privacy-policy
        'obchodne-podmienky',            // terms-of-service
        'zasady-pouzivania-cookies',     // cookie-policy
        'dakujeme',                      // thank-you
        'uz-coskoro',                    // coming-soon
    ];

    /**
     * Maximum token limit (~8000 tokens = ~32000 characters safe)
     */
    private const MAX_TOKEN_LIMIT = 8000;
    private const MAX_CHAR_LIMIT = 32000;

    /**
     * Cache duration in seconds (1 day)
     */
    private const CACHE_DURATION = DAY_IN_SECONDS;

    /**
     * Maximum number of pages to process
     */
    private int $maxPages = 10;

    /**
     * Minimum content length required
     */
    private int $minContentLength = 100;

    /**
     * Detected site type (cached after first detection)
     */
    private ?string $detectedSiteType = null;

    public function __construct() {
        // Initialization if needed
        $this->setMaxPages(self::MAX_PAGES_TO_CHECK);
        $this->setMinContentLength(self::MIN_CONTENT_LENGTH);
    }

    /**
     * Set maximum number of pages to process
     */
    public function setMaxPages(int $maxPages): self
    {
        $this->maxPages = max(1, $maxPages);
        return $this;
    }

    /**
     * Set minimum content length
     */
    public function setMinContentLength(int $minContentLength): self
    {
        $this->minContentLength = max(0, $minContentLength);
        return $this;
    }

    /**
     * Get the detected site type
     */
    public function getSiteType(): string
    {
        if ($this->detectedSiteType === null) {
            $this->detectedSiteType = $this->detectSiteType();
        }
        return $this->detectedSiteType;
    }

    /**
     * Get onboarding content from identified pages or posts
     * Automatically detects site type and uses appropriate extraction strategy
     *
     * @return string Combined and cleaned content from priority pages or blog posts
     * @throws Exception
     */
    public function getOnboardingContent(bool $useCache = false): string
    {
        try {
            // Check cache first
            $cacheKey = $this->generateCacheKey();
            if ($useCache) {
                $cached = get_transient($cacheKey);
                if ($cached !== false) {
                    return $cached;
                }
            }

            // Detect site type and use appropriate strategy
            $siteType = $this->getSiteType();
            $combinedContent = '';

            if ($siteType === self::SITE_TYPE_BLOG_INDEX) {
                // Primary strategy for blog index sites: extract from posts
                $combinedContent = $this->extractBlogIndexContent();

                // Fallback: if blog posts don't yield enough content, try pages too
                if (empty($combinedContent) || strlen($combinedContent) < $this->minContentLength * 2) {
                    $pageContent = $this->extractStaticHomepageContent();
                    if (!empty($pageContent)) {
                        $combinedContent = !empty($combinedContent)
                            ? $combinedContent . "\n=============\n" . $pageContent
                            : $pageContent;
                    }
                }
            } else {
                // Primary strategy for static homepage sites: extract from pages
                $combinedContent = $this->extractStaticHomepageContent();

                // Fallback: if pages don't yield enough content, try blog posts too
                if (empty($combinedContent) || strlen($combinedContent) < $this->minContentLength * 2) {
                    $blogContent = $this->extractBlogIndexContent();
                    if (!empty($blogContent)) {
                        $combinedContent = !empty($combinedContent)
                            ? $combinedContent . "\n=============\n" . $blogContent
                            : $blogContent;
                    }
                }
            }

            if (empty($combinedContent)) {
                $this->logWarning('No content extracted from site (type: ' . $siteType . ')');
                return '';
            }

            // Apply token-aware truncation
            $combinedContent = $this->applyTokenAwareTruncation($combinedContent);

            // Check if content meets minimum threshold for AI processing
            if (strlen($combinedContent) < self::MIN_SUFFICIENT_CONTENT_LENGTH) {
                $this->logWarning('Content insufficient for AI processing: ' . strlen($combinedContent) . ' chars (minimum: ' . self::MIN_SUFFICIENT_CONTENT_LENGTH . ')');
                return '';
            }

            // Cache the result
            set_transient($cacheKey, $combinedContent, self::CACHE_DURATION);

            return $combinedContent;

        } catch (Exception $e) {
            $this->logError('Failed to get onboarding content: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Detect site type: blog index or static homepage
     *
     * Blog index: Front page displays latest posts (no static homepage set)
     * Static homepage: A specific page is set as the front page
     *
     * @return string Site type constant
     */
    private function detectSiteType(): string
    {
        // WordPress setting: 'posts' or 'page'
        $showOnFront = get_option('show_on_front');

        if ($showOnFront === 'posts') {
            return self::SITE_TYPE_BLOG_INDEX;
        }

        // Even if set to 'page', verify a front page is actually configured
        $frontPageId = (int)get_option('page_on_front');
        if (empty($frontPageId) || $frontPageId === 0) {
            return self::SITE_TYPE_BLOG_INDEX;
        }

        // Verify the front page actually exists and is published
        $frontPage = get_post($frontPageId);
        if (!$frontPage || $frontPage->post_status !== 'publish') {
            return self::SITE_TYPE_BLOG_INDEX;
        }

        return self::SITE_TYPE_STATIC_HOMEPAGE;
    }

    /**
     * Extract content for blog index sites (front page shows latest posts)
     * This method extracts content from recent blog posts instead of static pages
     *
     * @return string Combined content from blog posts
     */
    private function extractBlogIndexContent(): string
    {
        $posts = $this->getRecentBlogPosts();

        if (empty($posts)) {
            $this->logWarning('No blog posts found for blog index content extraction');
            return '';
        }

        $contentParts = [];
        $processedCount = 0;

        foreach ($posts as $post) {
            // Limit processing to avoid performance issues
            if ($processedCount >= self::MAX_BLOG_POSTS_TO_EXTRACT) {
                break;
            }

            $content = $this->extractPageContent($post);
            if (!empty($content) && strlen($content) >= $this->minContentLength) {
                // Prepend post title for context
                $titlePrefix = !empty($post->post_title) ? $post->post_title . ': ' : '';
                $contentParts[] = $titlePrefix . $content;
                $processedCount++;
            }
        }

        if (empty($contentParts)) {
            $this->logWarning('No content extracted from blog posts');
            return '';
        }

        return $this->combineContent($contentParts);
    }

    /**
     * Get recent blog posts for content extraction
     *
     * @return WP_Post[] Array of recent blog posts
     */
    private function getRecentBlogPosts(): array
    {
        $args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => self::MAX_BLOG_POSTS_TO_EXTRACT * 2, // Fetch extra to filter
            'orderby' => 'date',
            'order' => 'DESC',
            'ignore_sticky_posts' => false, // Include sticky posts at top
        ];

        $query = new WP_Query($args);
        $posts = $query->posts ?? [];

        if (empty($posts)) {
            return [];
        }

        // Filter out posts that are too short or excluded
        $filteredPosts = [];
        foreach ($posts as $post) {
            // Skip excluded posts
            if ($this->shouldExcludePage($post->post_name)) {
                continue;
            }

            // Check content length
            $contentLength = strlen(wp_strip_all_tags($post->post_content));
            if ($contentLength >= $this->minContentLength) {
                $filteredPosts[] = $post;
            }
        }

        // If we filtered too aggressively, include some shorter posts
        if (count($filteredPosts) < 3 && count($posts) > count($filteredPosts)) {
            foreach ($posts as $post) {
                if (!in_array($post, $filteredPosts, true)) {
                    $filteredPosts[] = $post;
                    if (count($filteredPosts) >= 5) {
                        break;
                    }
                }
            }
        }

        // Limit to max
        return array_slice($filteredPosts, 0, self::MAX_BLOG_POSTS_TO_EXTRACT);
    }

    /**
     * Extract content for static homepage sites
     * Uses priority page identification strategy
     *
     * @return string Combined content from priority pages
     */
    private function extractStaticHomepageContent(): string
    {
        // Identify priority pages
        $pages = $this->identifyPriorityPages();

        if (empty($pages)) {
            $this->logWarning('No priority pages found for content extraction');
            return '';
        }

        // Extract content from identified pages
        $contentParts = [];
        foreach ($pages as $page) {
            if ($this->shouldExcludePage($page->post_name)) {
                continue;
            }
            $content = $this->extractPageContent($page);
            if (!empty($content)) {
                if ($content < $this->minContentLength) {
                    continue;
                }
                $contentParts[] = $content;
            }
        }

        if (empty($contentParts)) {
            $this->logWarning('No content extracted from identified pages');
            return '';
        }

        return $this->combineContent($contentParts);
    }

    /**
     * Generate cache key for the current extraction context
     */
    private function generateCacheKey(): string
    {
        $blogId = get_current_blog_id();
        $siteType = $this->getSiteType();
        $pageIds = $this->getPageIdsForCaching();
        return "rankingcoach_autoonboarding_{$blogId}_{$siteType}_" . md5(serialize($pageIds));
    }

    /**
     * Get page IDs for cache key generation
     */
    private function getPageIdsForCaching(): array
    {
        $siteType = $this->getSiteType();

        if ($siteType === self::SITE_TYPE_BLOG_INDEX) {
            // For blog index, use recent post IDs
            $args = [
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => self::MAX_BLOG_POSTS_TO_EXTRACT,
                'fields' => 'ids',
                'orderby' => 'modified',
                'order' => 'DESC',
            ];
        } else {
            // For static homepage, use page IDs
            $args = [
                'post_type' => ALLOWED_RANKINGCOACH_CUSTOM_TYPES,
                'post_status' => 'publish',
                'posts_per_page' => $this->maxPages,
                'fields' => 'ids',
                'orderby' => 'modified',
                'order' => 'DESC',
            ];
        }

        $query = new WP_Query($args);
        return $query->posts ?? [];
    }

    /**
     * Estimate token count for content
     * Formula: intval(strlen($text)/4) + str_word_count($text)
     */
    private function estimateTokenCount(string $text): int
    {
        return intval(strlen($text) / 4) + str_word_count($text);
    }

    /**
     * Apply token-aware truncation to content
     */
    private function applyTokenAwareTruncation(string $content): string
    {
        $estimatedTokens = $this->estimateTokenCount($content);

        if ($estimatedTokens <= self::MAX_TOKEN_LIMIT && strlen($content) <= self::MAX_CHAR_LIMIT) {
            return $content;
        }

        // Truncate by character limit first (safe ~32k chars)
        if (strlen($content) > self::MAX_CHAR_LIMIT) {
            $content = substr($content, 0, self::MAX_CHAR_LIMIT);
            // Find last complete sentence or word
            $lastPeriod = strrpos($content, '.');
            $lastSpace = strrpos($content, ' ');
            $cutPoint = max($lastPeriod ?: 0, $lastSpace ?: 0);
            if ($cutPoint > self::MAX_CHAR_LIMIT * 0.9) {
                $content = substr($content, 0, $cutPoint);
            }
        }

        // Re-check token count and further truncate if needed
        while ($this->estimateTokenCount($content) > self::MAX_TOKEN_LIMIT && strlen($content) > 1000) {
            $content = substr($content, 0, (int)(strlen($content) * 0.9));
            $lastPeriod = strrpos($content, '.');
            if ($lastPeriod && $lastPeriod > strlen($content) * 0.8) {
                $content = substr($content, 0, $lastPeriod + 1);
            }
        }

        return trim($content);
    }

    /**
     * Identify priority pages for content extraction
     *
     * @return WP_Post[] Array of priority pages sorted by relevance
     */
    private function identifyPriorityPages(): array
    {
        $currentLanguage = $this->detectCurrentLanguage();
        $patterns = $this->getMultilingualPatterns($currentLanguage);

        // Get all published pages
        $args = [
            'post_type' => ALLOWED_RANKINGCOACH_CUSTOM_TYPES,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ];

        $query = new WP_Query($args);
        $pages = $query->posts ?? [];

        // Also get pages from sitemap if available
        $sitemapPages = $this->getPagesFromSitemap();
        if (!empty($sitemapPages)) {
            $existingIds = array_column($pages, 'ID');
            foreach ($sitemapPages as $sitemapPage) {
                if (!in_array($sitemapPage->ID, $existingIds)) {
                    $pages[] = $sitemapPage;
                }
            }
        }

        // Get custom post type pages
        $customPostTypes = $this->getCustomPostTypePages();
        if (!empty($customPostTypes)) {
            $existingIds = array_column($pages, 'ID');
            foreach ($customPostTypes as $cptPage) {
                if (!in_array($cptPage->ID, $existingIds)) {
                    $pages[] = $cptPage;
                }
            }
        }

        if (empty($pages)) {
            return [];
        }

        $scoredPages = [];
        if (count($pages) > $this->maxPages) {
            // Score and sort pages by priority
            foreach ($pages as $page) {
                $score = $this->calculatePageScore($page, $patterns);
                if ($score > 0) {
                    $scoredPages[] = [
                        'page' => $page,
                        'score' => $score,
                    ];
                }
            }
        } else {
            // All pages qualify if under max limit
            foreach ($pages as $page) {
                $scoredPages[] = [
                    'page' => $page,
                    'score' => 1,
                ];
            }
        }

        // Sort by score descending, then by menu order
        usort($scoredPages, function ($a, $b) {
            if ($a['score'] === $b['score']) {
                return ($a['page']->menu_order ?? 0) <=> ($b['page']->menu_order ?? 0);
            }
            return $b['score'] <=> $a['score'];
        });

        // Limit to max pages
        $scoredPages = array_slice($scoredPages, 0, $this->maxPages);

        return array_column($scoredPages, 'page');
    }

    /**
     * Detect current language using multiple methods
     */
    private function detectCurrentLanguage(): string
    {
        // Check Polylang first
        if (function_exists('pll_current_language')) {
            $lang = pll_current_language();
            if ($lang) {
                return substr($lang, 0, 2);
            }
        }

        // Check WPML
        if (defined('ICL_LANGUAGE_CODE')) {
            return substr(ICL_LANGUAGE_CODE, 0, 2);
        }

        if (function_exists('wpml_current_language')) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            $lang = apply_filters('wpml_current_language', null);
            if ($lang) {
                return substr($lang, 0, 2);
            }
        }

        // Use WordPress locale
        $locale = get_locale();
        if ($locale) {
            return substr($locale, 0, 2);
        }

        // Fallback to WordpressHelpers if available
        if (method_exists(WordpressHelpers::class, 'getCurrentLanguage')) {
            return WordpressHelpers::getCurrentLanguage();
        }

        return 'en';
    }

    /**
     * Get pages from sitemap if available
     */
    private function getPagesFromSitemap(): array
    {
        $pages = [];

        // Check for sitemap URL in options
        $sitemapUrl = get_option('sitemap_url', '');
        if (empty($sitemapUrl)) {
            // Try common sitemap locations
            $homeUrl = home_url('/');
            $possibleSitemaps = [
                $homeUrl . 'sitemap.xml',
                $homeUrl . 'sitemap_index.xml',
                $homeUrl . 'wp-sitemap.xml',
            ];

            foreach ($possibleSitemaps as $url) {
                $response = wp_remote_head($url, ['timeout' => 5]);
                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                    $sitemapUrl = $url;
                    break;
                }
            }
        }

        if (empty($sitemapUrl)) {
            return $pages;
        }

        try {
            $response = wp_remote_get($sitemapUrl, ['timeout' => 10]);
            if (is_wp_error($response)) {
                return $pages;
            }

            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                return $pages;
            }

            // Parse sitemap XML
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);
            if ($xml === false) {
                return $pages;
            }

            // Extract URLs from sitemap
            $namespaces = $xml->getNamespaces(true);
            $urls = [];

            if (isset($xml->url)) {
                foreach ($xml->url as $urlEntry) {
                    $loc = (string)$urlEntry->loc;
                    if (!empty($loc)) {
                        $urls[] = $loc;
                    }
                }
            }

            // Convert URLs to post IDs
            foreach ($urls as $url) {
                $postId = url_to_postid($url);
                if ($postId > 0) {
                    $post = get_post($postId);
                    if ($post && $post->post_status === 'publish') {
                        $pages[] = $post;
                    }
                }
            }

        } catch (Exception $e) {
            $this->logWarning("Failed to parse sitemap: " . $e->getMessage());
        }

        return $pages;
    }

    /**
     * Get pages from custom post types
     */
    private function getCustomPostTypePages(): array
    {
        $pages = [];

        // Get public custom post types
        $postTypes = get_post_types([
            'public' => true,
            '_builtin' => false,
        ], 'names');

        // Exclude some common non-content post types
        $excludedTypes = ['product', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset'];
        $postTypes = array_diff($postTypes, $excludedTypes);

        if (empty($postTypes)) {
            return $pages;
        }

        $args = [
            'post_type' => array_values($postTypes),
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $query = new WP_Query($args);
        $pages = $query->posts ?? [];

        return $pages;
    }

    /**
     * Calculate relevance score for a page
     */
    private function calculatePageScore(WP_Post $page, array $patterns): int
    {
        $score = 0;
        $slug = $page->post_name;
        $title = strtolower($page->post_title);

        // Check if page should be excluded
        if ($this->shouldExcludePage($slug)) {
            return 0;
        }

        // Score based on slug/title matching patterns
        foreach ($patterns as $pageType => $typePatterns) {
            foreach ($typePatterns as $pattern) {
                // Exact slug match gets highest score
                if ($slug === $pattern) {
                    $score += self::PRIORITY_PAGES[$pageType] * 3;
                } // Slug contains pattern
                elseif (str_contains($slug, $pattern)) {
                    $score += self::PRIORITY_PAGES[$pageType] * 2;
                } // Title contains pattern
                elseif (str_contains($title, $pattern)) {
                    $score += self::PRIORITY_PAGES[$pageType];
                }
            }
        }

        // Boost score for pages in main menu
        if ($this->isInMainMenu($page->ID)) {
            $score += 20;
        }

        // Boost score for front page
        if (WordpressHelpers::is_front_page($page->ID)) {
            $score += 50;
        }

        // Boost score for home/blog page
        if (WordpressHelpers::is_blog_archive_page($page->ID)) {
            $score += 40;
        }

        return $score;
    }

    /**
     * Check if page should be excluded from content extraction
     */
    private function shouldExcludePage(string $slug): bool
    {
        foreach (self::EXCLUDED_PAGES as $excluded) {
            if (str_contains($slug, $excluded)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if page is in main navigation menu
     */
    private function isInMainMenu(int $pageId): bool
    {
        $menus = wp_get_nav_menus();
        if (empty($menus)) {
            return false;
        }

        foreach ($menus as $menu) {
            $menuItems = wp_get_nav_menu_items($menu->term_id);
            if (empty($menuItems)) {
                continue;
            }

            foreach ($menuItems as $item) {
                if ($item->object === 'page' && (int)$item->object_id === $pageId) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get multilingual patterns for current language
     */
    private function getMultilingualPatterns(string $language): array
    {
        // Return patterns for specific language if available
        if (isset(self::MULTILINGUAL_PATTERNS[$language])) {
            return self::MULTILINGUAL_PATTERNS[$language];
        }

        // Fallback to English patterns
        return self::MULTILINGUAL_PATTERNS['en'];
    }

    /**
     * Extract content from a single page with page builder support
     */
    private function extractPageContent(WP_Post $page): string
    {
        try {
            $content = '';

            // Page builder fallback chain: Divi → Beaver Builder → Gutenberg → Elementor → WPBakery → standard

            // Method 1: Divi Builder content
            $content = $this->renderDiviContent($page);

            // Method 2: Beaver Builder content
            if (empty($content)) {
                $content = $this->renderBeaverBuilderContent($page);
            }

            // Method 3: Gutenberg/Block content
            if (empty($content)) {
                $content = $this->renderGutenbergContent($page);
            }

            // Method 4: Elementor content
            if (empty($content)) {
                $content = $this->renderElementorContent($page);
            }

            // Method 5: WPBakery content
            if (empty($content)) {
                try {
                    $content = WordpressHelpers::render_wpbakery_content($page);
                } catch (Exception $e) {
                    $this->logWarning('WPBakery render failed for page ' . $page->ID . ': ' . $e->getMessage());
                }
            }

            // Method 6: Standard WordPress content retrieval
            if (empty($content)) {
                try {
                    $content = WordpressHelpers::retrieve_post_content($page);
                } catch (Exception $e) {
                    $this->logWarning('Standard content retrieval failed for page ' . $page->ID . ': ' . $e->getMessage());
                }
            }

            // Method 7: Raw post content as final fallback
            if (empty($content)) {
                $content = $page->post_content;
            }

            if (empty($content)) {
                $this->logWarning('No content found for page ID: ' . $page->ID);
                return '';
            }

            // Clean and parse the content
            $cleanedContent = $this->cleanAndParseContent($content);

            if (strlen($cleanedContent) < $this->minContentLength) {
                return '';
            }

            return $cleanedContent;

        } catch (Exception $e) {
            $this->logError('Failed to extract content from page ID: ' . $page->ID . ' - ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Render Elementor content
     */
    private function renderElementorContent(WP_Post $page): string
    {
        try {
            $content = WordpressHelpers::render_elementor_content($page->ID);
        } catch (Exception $e) {
            $this->logWarning('Elementor render failed for page ' . $page->ID . ': ' . $e->getMessage());
        }
        return $content ?? '';
    }

    /**
     * Render Divi Builder content
     */
    private function renderDiviContent(WP_Post $page): string
    {
        try {
            // Check if Divi is active
            if (!defined('ET_BUILDER_VERSION') && !function_exists('et_pb_is_pagebuilder_used')) {
                return '';
            }

            // Check if post uses Divi builder
            $usesDivi = get_post_meta($page->ID, '_et_pb_use_builder', true);
            if ($usesDivi !== 'on') {
                return '';
            }

            // Get Divi content
            $diviContent = get_post_meta($page->ID, '_et_pb_post_content', true);
            if (!empty($diviContent)) {
                return $diviContent;
            }

            // Fallback: apply Divi shortcodes to post content
            if (function_exists('et_builder_init_global_settings')) {
                et_builder_init_global_settings();
            }

            $content = $page->post_content;
            if (has_shortcode($content, 'et_pb_section') || has_shortcode($content, 'et_pb_row')) {
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                $content = apply_filters('the_content', $content);
                return $content;
            }

            return '';

        } catch (Exception $e) {
            $this->logWarning('Divi render failed for page ' . $page->ID . ': ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Render Beaver Builder content
     */
    private function renderBeaverBuilderContent(WP_Post $page): string
    {
        try {
            // Check if Beaver Builder is active
            if (!class_exists('FLBuilder') && !class_exists('FLBuilderModel')) {
                return '';
            }

            // Check for Beaver Builder data
            $flBuilderData = get_post_meta($page->ID, '_fl_builder_data', true);
            if (empty($flBuilderData)) {
                $flBuilderData = get_post_meta($page->ID, 'fl_builder_data', true);
            }

            if (empty($flBuilderData)) {
                return '';
            }

            // Try to render using Beaver Builder
            if (class_exists('FLBuilder') && method_exists('FLBuilder', 'render_content_by_id')) {
                ob_start();
                FLBuilder::render_content_by_id($page->ID);
                $content = ob_get_clean();
                if (!empty($content)) {
                    return $content;
                }
            }

            // Fallback: extract text from builder data
            if (is_array($flBuilderData)) {
                return $this->extractTextFromBeaverData($flBuilderData);
            }

            return '';

        } catch (Exception $e) {
            $this->logWarning('Beaver Builder render failed for page ' . $page->ID . ': ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Extract text content from Beaver Builder data array
     */
    private function extractTextFromBeaverData(array $data): string
    {
        $texts = [];

        foreach ($data as $item) {
            if (is_array($item)) {
                // Recursively extract text
                $nested = $this->extractTextFromBeaverData($item);
                if (!empty($nested)) {
                    $texts[] = $nested;
                }
            } elseif (is_object($item)) {
                // Check for common text properties
                $textProps = ['text', 'content', 'html', 'heading', 'title', 'description'];
                foreach ($textProps as $prop) {
                    if (isset($item->$prop) && is_string($item->$prop) && !empty($item->$prop)) {
                        $texts[] = $item->$prop;
                    }
                }
                // Check settings object
                if (isset($item->settings) && is_object($item->settings)) {
                    foreach ($textProps as $prop) {
                        if (isset($item->settings->$prop) && is_string($item->settings->$prop) && !empty($item->settings->$prop)) {
                            $texts[] = $item->settings->$prop;
                        }
                    }
                }
            }
        }

        return implode(' ', $texts);
    }

    /**
     * Render Gutenberg/Block content
     */
    private function renderGutenbergContent(WP_Post $page): string
    {
        try {
            $content = $page->post_content;

            // Check if content has blocks
            if (!has_blocks($content)) {
                return '';
            }

            // Render blocks using do_blocks
            if (function_exists('do_blocks')) {
                $rendered = do_blocks($content);
                if (!empty($rendered)) {
                    return $rendered;
                }
            }

            return '';

        } catch (Exception $e) {
            $this->logWarning('Gutenberg render failed for page ' . $page->ID . ': ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Clean and parse content for onboarding use
     */
    private function cleanAndParseContent(string $content): string
    {
        // Remove scripts, styles, and other code injections first
        $content = $this->removeCodeInjections($content);

        // Extract address content before stripping HTML
        $addressContent = $this->extractAddressContent($content);

        // Remove HTML tags but preserve structure
        $content = $this->stripHtmlTags($content);

        // Append extracted address content
        if (!empty($addressContent)) {
            $content .= ' ' . $addressContent;
        }

        // Remove shortcodes and their content
        $content = $this->removeShortcodes($content);

        // Decode HTML entities
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Clean excessive whitespace
        $content = $this->cleanWhitespace($content);

        // Remove non-text content
        $content = $this->removeNonTextContent($content);

        return trim($content);
    }

    /**
     * Extract content from address tags before HTML stripping
     */
    private function extractAddressContent(string $content): string
    {
        $addresses = [];

        // Extract content from <address> tags
        if (preg_match_all('/<address[^>]*>(.*?)<\/address>/is', $content, $matches)) {
            foreach ($matches[1] as $match) {
                $text = wp_strip_all_tags($match);
                $text = trim($text);
                if (!empty($text)) {
                    $addresses[] = $text;
                }
            }
        }

        return implode(' ', $addresses);
    }

    /**
     * Strip HTML tags while preserving some structure
     */
    private function stripHtmlTags(string $content): string
    {
        // Allow basic formatting tags including address and emphasis tags
        $allowedTags = '<p><br><h1><h2><h3><h4><h5><h6><strong><b><em><i><u><ul><ol><li><blockquote><address>';

        // Strip tags but keep allowed ones
        $content = wp_strip_all_tags($content, $allowedTags);

        // Convert headers to paragraphs for consistency
        $content = preg_replace('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i', '<p>$1</p>', $content);

        // Convert line breaks to spaces
        $content = preg_replace('/<br[^>]*>/i', ' ', $content);

        // Extract address tag content and convert to text
        $content = preg_replace('/<address[^>]*>(.*?)<\/address>/is', ' $1 ', $content);

        // Keep strong/b/em for semantic meaning but will be stripped later
        $content = preg_replace('/<(strong|b|em|i)[^>]*>(.*?)<\/\1>/is', ' $2 ', $content);

        // Clean up paragraph tags
        $content = preg_replace('/<\/p>\s*<p>/i', "\n\n", $content);
        $content = wp_strip_all_tags($content);

        return $content;
    }

    /**
     * Remove shortcodes and their content
     */
    private function removeShortcodes(string $content): string
    {
        // Remove shortcodes with content
        $content = preg_replace('/\[([^\]]+)\](.*?)\[\/\1\]/s', '', $content);

        // Remove self-closing shortcodes
        $content = preg_replace('/\[([^\]]+)\]/', '', $content);

        return $content;
    }

    /**
     * Remove scripts, styles, and other code injections
     */
    private function removeCodeInjections(string $content): string
    {
        // Remove script tags
        $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);

        // Remove style tags
        $content = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $content);

        // Remove PHP code blocks (unlikely but possible)
        $content = preg_replace('/<\?php.*?\?>/s', '', $content);

        // Remove comments
        $content = preg_replace('/<!--.*?-->/s', '', $content);

        // Remove noscript tags
        $content = preg_replace('/<noscript[^>]*>.*?<\/noscript>/is', '', $content);

        // Remove SVG tags
        $content = preg_replace('/<svg[^>]*>.*?<\/svg>/is', '', $content);

        return $content;
    }

    /**
     * Clean excessive whitespace
     */
    private function cleanWhitespace(string $content): string
    {
        // Replace multiple spaces with single space
        $content = preg_replace('/\s+/', ' ', $content);

        // Replace multiple newlines with double newline
        $content = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $content);

        // Trim each line
        $lines = explode("\n", $content);
        $lines = array_map('trim', $lines);
        $content = implode("\n", array_filter($lines));

        return trim($content);
    }

    /**
     * Remove non-text content like URLs, email addresses, etc.
     */
    private function removeNonTextContent(string $content): string
    {
        // Remove URLs
        $content = preg_replace('/https?:\/\/[^\s]+/i', '', $content);

        // Remove email addresses
        $content = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '', $content);

        // Remove phone numbers (basic pattern)
        $content = preg_replace('/\+?[\d\s\-\(\)]{7,}/', '', $content);

        // Remove excessive punctuation
        $content = preg_replace('/[^\w\s\p{P}]/u', '', $content);

        return trim($content);
    }

    /**
     * Combine content from multiple pages
     */
    private function combineContent(array $contentParts): string
    {
        // Filter out empty parts
        $contentParts = array_filter($contentParts, function ($content) {
            return !empty(trim($content));
        });

        if (empty($contentParts)) {
            return '';
        }

        // Join with double newlines
        $combined = implode("\n=============\n", $contentParts);

        // Apply final security filtering
        $combined = $this->applySecurityFiltering($combined);

        // Final cleanup
        $combined = $this->cleanWhitespace($combined);

        return $combined;
    }

    /**
     * Apply final security filtering
     */
    private function applySecurityFiltering(string $content): string
    {
        // Remove any remaining HTML tags
        $content = wp_strip_all_tags($content);

        // Remove potential XSS vectors
        $content = $this->sanitizeContent($content);

        return $content;
    }

    /**
     * Sanitize content for safe usage
     */
    private function sanitizeContent(string $content): string
    {
        // Use WordPress sanitization functions
        if (function_exists('wp_kses')) {
            $allowedHtml = [
                'p' => [],
                'br' => [],
                'strong' => [],
                'b' => [],
                'em' => [],
                'i' => [],
                'u' => [],
                'ul' => [],
                'ol' => [],
                'li' => [],
                'address' => [],
            ];
            $content = wp_kses($content, $allowedHtml);
        }

        // Final strip of all HTML
        $content = wp_strip_all_tags($content);

        return $content;
    }

    /**
     * Log warning message
     */
    private function logWarning(string $message): void
    {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[BeyondSEO] DEBUG: AutoSetupOnboarding Warning: ' . $message);
        }
    }

    /**
     * Log error message
     */
    private function logError(string $message): void
    {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[BeyondSEO] DEBUG: AutoSetupOnboarding Error: ' . $message);
        }
    }
}

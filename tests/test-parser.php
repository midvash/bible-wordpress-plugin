<?php
/**
 * Parser tests. No WordPress and no PHPUnit needed:
 *
 *     php tests/test-parser.php
 *
 * Stubs the handful of WordPress functions the parser calls, then checks what
 * MVBR_Parser::link_content() makes of real-world snippets.
 *
 * @package Midvash_Bible_References
 */

// phpcs:disable -- Test runner, not plugin code.

define( 'ABSPATH', __DIR__ . '/' );
define( 'MVBR_SITE_URL', 'https://midvash.com' );

$mvbr_test_options = array();
$mvbr_enqueued     = array();

function get_option( $name, $default = false ) {
	global $mvbr_test_options;
	return $mvbr_test_options ? $mvbr_test_options : $default;
}
function add_filter() {}
function wp_enqueue_style( $handle ) {
	global $mvbr_enqueued;
	$mvbr_enqueued[ $handle ] = true;
}
function wp_enqueue_script( $handle ) {
	global $mvbr_enqueued;
	$mvbr_enqueued[ $handle ] = true;
}
function esc_url( $url ) {
	return htmlspecialchars( $url, ENT_QUOTES );
}
function esc_attr( $text ) {
	return htmlspecialchars( $text, ENT_QUOTES );
}
function esc_html( $text ) {
	return htmlspecialchars( $text, ENT_QUOTES );
}
function __( $text ) {
	return $text;
}
function esc_attr__( $text ) {
	return esc_attr( $text );
}

require __DIR__ . '/../includes/class-mvbr-books.php';
require __DIR__ . '/../includes/class-mvbr-parser.php';

/**
 * Parses $html with a fresh parser configured for $locale.
 */
function mvbr_parse( $html, $locale = 'pt-br', $extra = array() ) {
	global $mvbr_test_options;
	$mvbr_test_options = array_merge(
		array(
			'locale'    => $locale,
			'versao'    => MVBR_Books::get_default_version( $locale ),
			'css_class' => 'mvbr-link',
			'new_tab'   => false,
		),
		$extra
	);
	$parser = new MVBR_Parser();
	$parser->init();
	return $parser->link_content( $html );
}

/**
 * Hrefs of the links the parser added, in order.
 */
function mvbr_hrefs( $html ) {
	preg_match_all( '/<a href="([^"]+)" class="mvbr-link"/', $html, $m );
	return $m[1];
}

$mvbr_failures = 0;
$mvbr_passes   = 0;
function check( $label, $expected, $actual ) {
	global $mvbr_failures, $mvbr_passes;
	if ( $expected === $actual ) {
		++$mvbr_passes;
		return;
	}
	++$mvbr_failures;
	echo "FAIL: $label\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n";
}

// --- References in Portuguese -------------------------------------------

check(
	'full name, chapter and verse',
	array( 'https://midvash.com/pt-br/onbv/joao/3#v=16' ),
	mvbr_hrefs( mvbr_parse( '<p>Leia João 3:16 hoje.</p>' ) )
);
check(
	'verse range',
	array( 'https://midvash.com/pt-br/onbv/romanos/8#v=28-30' ),
	mvbr_hrefs( mvbr_parse( '<p>Romanos 8:28-30</p>' ) )
);
check(
	'chapter only, full name',
	array( 'https://midvash.com/pt-br/onbv/salmos/23' ),
	mvbr_hrefs( mvbr_parse( '<p>Salmos 23 é conhecido.</p>' ) )
);
check(
	'three-digit chapter and numbered book',
	array( 'https://midvash.com/pt-br/onbv/salmos/119#v=105', 'https://midvash.com/pt-br/onbv/1-corintios/13#v=4' ),
	mvbr_hrefs( mvbr_parse( '<p>Salmos 119:105 e 1 Coríntios 13:4</p>' ) )
);
check(
	'name without accent',
	array( 'https://midvash.com/pt-br/onbv/joao/3#v=16' ),
	mvbr_hrefs( mvbr_parse( '<p>Joao 3:16</p>' ) )
);
check(
	'abbreviation with verse',
	array( 'https://midvash.com/pt-br/onbv/genesis/1#v=1', 'https://midvash.com/pt-br/onbv/oseias/6#v=6' ),
	mvbr_hrefs( mvbr_parse( '<p>Gn 1:1 e Os 6:6</p>' ) )
);

// --- Words that only look like abbreviations ----------------------------

check( '"Os 13" is an article', array(), mvbr_hrefs( mvbr_parse( '<p>Os 13 apóstolos chegaram.</p>' ) ) );
check( 'lowercase "os" is never Hosea', array(), mvbr_hrefs( mvbr_parse( '<p>os 13:4 foram</p>' ) ) );
check( '"Am 5" without verse', array(), mvbr_hrefs( mvbr_parse( '<p>Am 5 dias.</p>' ) ) );
check( '"Ex 3" without verse', array(), mvbr_hrefs( mvbr_parse( '<p>Ex 3 colegas</p>' ) ) );
check( 'lowercase book name', array(), mvbr_hrefs( mvbr_parse( '<p>atos 2:1</p>' ) ) );
check( 'English book on a Portuguese site', array(), mvbr_hrefs( mvbr_parse( '<p>John 3:16</p>' ) ) );
check( 'chapter out of range', array(), mvbr_hrefs( mvbr_parse( '<p>Judas 2:1</p>' ) ) );
check( 'part of a longer word', array(), mvbr_hrefs( mvbr_parse( '<p>MarcosX 3:1</p>' ) ) );

// --- English --------------------------------------------------------------

check(
	'English links have no /en/ prefix',
	array( 'https://midvash.com/bsb/john/3#v=16' ),
	mvbr_hrefs( mvbr_parse( '<p>Read John 3:16.</p>', 'en' ) )
);
check( '"at 10:30" is a time', array(), mvbr_hrefs( mvbr_parse( '<p>Meet at 10:30, then at 10:45.</p>', 'en' ) ) );
check( '"is 5" is a verb', array(), mvbr_hrefs( mvbr_parse( '<p>The answer is 5.</p>', 'en' ) ) );
check( '"Mar 5" is a date', array(), mvbr_hrefs( mvbr_parse( '<p>Mar 5 was cold.</p>', 'en' ) ) );
check(
	'abbreviation with verse',
	array( 'https://midvash.com/bsb/mark/5#v=1' ),
	mvbr_hrefs( mvbr_parse( '<p>Mar 5:1</p>', 'en' ) )
);

// --- Other locales --------------------------------------------------------

check(
	'Italian default version is riveduta',
	array( 'https://midvash.com/it/riveduta/giovanni/3#v=16' ),
	mvbr_hrefs( mvbr_parse( '<p>Giovanni 3:16</p>', 'it' ) )
);
check(
	'Spanish "Es 5" is not a book',
	array(),
	mvbr_hrefs( mvbr_parse( '<p>Es 5 veces mejor.</p>', 'es' ) )
);
check(
	'Chinese without spaces',
	1,
	count( mvbr_hrefs( mvbr_parse( '<p>请读约翰福音3:16。</p>', 'zh' ) ) )
);
check(
	'Korean particle after the reference',
	1,
	count( mvbr_hrefs( mvbr_parse( '<p>요한복음 3:16에서</p>', 'ko' ) ) )
);

// --- HTML is left alone ---------------------------------------------------

$mvbr_img = '<p><img src="/uploads/Bíblia-João-3-16.jpg" srcset="/uploads/Bíblia-João-3-16-300.jpg 300w" alt="Bíblia aberta em João 3:16"></p>';
check( 'attributes are untouched', $mvbr_img, mvbr_parse( $mvbr_img, 'pt-br', array( 'link_biblia' => true ) ) );
check( 'existing links are skipped', '<p><a href="/x">João 3:16</a></p>', mvbr_parse( '<p><a href="/x">João 3:16</a></p>' ) );
check( 'headings are skipped', '<h2>João 3:16</h2>', mvbr_parse( '<h2>João 3:16</h2>' ) );
check( 'code is skipped', '<pre><code>João 3:16</code></pre>', mvbr_parse( '<pre><code>João 3:16</code></pre>' ) );
check(
	'text after a skipped tag is linked',
	1,
	count( mvbr_hrefs( mvbr_parse( '<h2>Título</h2><p>João 3:16</p>' ) ) )
);

// --- The word "Bíblia" ----------------------------------------------------

$mvbr_bible = mvbr_parse( '<p>A Bíblia diz. A Bíblia ensina. Bíblia!</p>', 'pt-br', array( 'link_biblia' => true ) );
check( 'only the first "Bíblia" is linked', array( 'https://midvash.com/pt-br' ), mvbr_hrefs( $mvbr_bible ) );
check( 'English "Bible" goes to the root', array( 'https://midvash.com' ), mvbr_hrefs( mvbr_parse( '<p>The Bible says.</p>', 'en', array( 'link_biblia' => true ) ) ) );
check( '"Bíblia" off by default', array(), mvbr_hrefs( mvbr_parse( '<p>A Bíblia diz.</p>' ) ) );

// --- Numbered books ---------------------------------------------------------

foreach ( array( '1 Jo 4:8', '1Jo 4:8', 'I João 4:8', 'Primeira João 4:8', '1ª João 4:8', '1 João 4:8', '1 Joao 4:8' ) as $mvbr_ref ) {
	check( "$mvbr_ref is 1 John", array( 'https://midvash.com/pt-br/onbv/1-joao/4#v=8' ), mvbr_hrefs( mvbr_parse( "<p>$mvbr_ref</p>" ) ) );
}
check( '"1. João" is not the Gospel', array(), mvbr_hrefs( mvbr_parse( '<p>1. João 4:8</p>' ) ) );
check( 'a verse number before a book is not a book number', 2, count( mvbr_hrefs( mvbr_parse( '<p>Mt 5:3 Lc 6:20</p>' ) ) ) );
check( 'II and short numbered forms', array( 'https://midvash.com/pt-br/onbv/2-corintios/5#v=17', 'https://midvash.com/pt-br/onbv/2-corintios/5#v=17', 'https://midvash.com/pt-br/onbv/1-samuel/3#v=1' ), mvbr_hrefs( mvbr_parse( '<p>II Coríntios 5:17, 2 Co 5:17 e 1 Sm 3:1</p>' ) ) );
check( 'I with chapter only', array( 'https://midvash.com/pt-br/onbv/1-corintios/13' ), mvbr_hrefs( mvbr_parse( '<p>I Coríntios 13</p>' ) ) );
check( 'English Roman and ordinal', array( 'https://midvash.com/bsb/1-john/4#v=8', 'https://midvash.com/bsb/2-peter/1#v=3' ), mvbr_hrefs( mvbr_parse( '<p>I John 4:8 and Second Peter 1:3</p>', 'en' ) ) );
check( 'Spanish Roman', array( 'https://midvash.com/es/rvr1909/1-juan/4#v=8' ), mvbr_hrefs( mvbr_parse( '<p>I Juan 4:8</p>', 'es' ) ) );

// --- Other common spellings -------------------------------------------------

check( 'Salmo and Cantares', array( 'https://midvash.com/pt-br/onbv/salmos/23', 'https://midvash.com/pt-br/onbv/canticos/2#v=1' ), mvbr_hrefs( mvbr_parse( '<p>Salmo 23 e Cantares 2:1</p>' ) ) );
check( 'Ex with verse', array( 'https://midvash.com/pt-br/onbv/exodo/20#v=3' ), mvbr_hrefs( mvbr_parse( '<p>Ex 20:3</p>' ) ) );
check( 'English Psalm', array( 'https://midvash.com/bsb/psalms/23' ), mvbr_hrefs( mvbr_parse( '<p>Psalm 23</p>', 'en' ) ) );
check( '"Phil 3" needs a verse', array(), mvbr_hrefs( mvbr_parse( '<p>Phil 3 was here.</p>', 'en' ) ) );

// --- Single-chapter books -----------------------------------------------------

check( 'Judas 3 is the verse', array( 'https://midvash.com/pt-br/onbv/judas/1#v=3' ), mvbr_hrefs( mvbr_parse( '<p>Judas 3</p>' ) ) );
check( '3 João 4 is the verse', array( 'https://midvash.com/pt-br/onbv/3-joao/1#v=4' ), mvbr_hrefs( mvbr_parse( '<p>3 João 4</p>' ) ) );
check( 'Judas 1:3 still works', array( 'https://midvash.com/pt-br/onbv/judas/1#v=3' ), mvbr_hrefs( mvbr_parse( '<p>Judas 1:3</p>' ) ) );
check( 'number past the last verse', array(), mvbr_hrefs( mvbr_parse( '<p>Judas 30</p>' ) ) );

// --- Comma -------------------------------------------------------------------

check( 'comma separates chapter and verse', array( 'https://midvash.com/pt-br/onbv/joao/3#v=16' ), mvbr_hrefs( mvbr_parse( '<p>João 3,16</p>' ) ) );
check( 'comma with a space is not a verse', array( 'https://midvash.com/pt-br/onbv/joao/3' ), mvbr_hrefs( mvbr_parse( '<p>João 3, 16 vezes</p>' ) ) );
$mvbr_more = mvbr_parse( '<p>João 3:16,18-20 diz</p>' );
check( 'more verses get their own links', array( 'https://midvash.com/pt-br/onbv/joao/3#v=16', 'https://midvash.com/pt-br/onbv/joao/3#v=18-20' ), mvbr_hrefs( $mvbr_more ) );
check( 'the extra link has the full reference', 1, substr_count( $mvbr_more, 'data-midvash-ref="João 3:18-20"' ) );
check( 'the comma stays in the text', true, false !== strpos( $mvbr_more, '</a>,<a ' ) );
$mvbr_span = mvbr_parse( '<p>João 3:16-4:2</p>' );
check( 'range into the next chapter opens at its start', array( 'https://midvash.com/pt-br/onbv/joao/3#v=16' ), mvbr_hrefs( $mvbr_span ) );
check( 'the whole range is one link', 1, substr_count( $mvbr_span, 'data-midvash-ref="João 3:16-4:2"' ) );
check( 'no verse range in the anchor of a span', array( 'https://midvash.com/pt-br/onbv/joao/3#v=16' ), mvbr_hrefs( mvbr_parse( '<p>João 3:16-4:20</p>' ) ) );
check( 'span with spaces', array( 'https://midvash.com/pt-br/onbv/mateus/5#v=1' ), mvbr_hrefs( mvbr_parse( '<p>Mateus 5:1 - 7:29</p>' ) ) );
check( 'same-chapter range and one more verse', array( 'https://midvash.com/pt-br/onbv/joao/3#v=16-18', 'https://midvash.com/pt-br/onbv/joao/3#v=20' ), mvbr_hrefs( mvbr_parse( '<p>João 3:16-18,20</p>' ) ) );
$mvbr_p = MVBR_Books::parse_reference( 'João 3:16-4:2', 'pt-br' );
check( 'parse reads the span', array( 3, 16, 4, 2 ), array( $mvbr_p['chapter'], $mvbr_p['verse'], $mvbr_p['chapter_end'], $mvbr_p['verse_end'] ) );
check( 'a span going backwards is not a reference', null, MVBR_Books::parse_reference( 'João 4:2-3:16', 'pt-br' ) );
check( 'a span past the last chapter is not a reference', null, MVBR_Books::parse_reference( 'Judas 1:3-2:1', 'pt-br' ) );

// --- Counts are not chapters ----------------------------------------------------

check( '"Marcos 12 anos" is an age', array(), mvbr_hrefs( mvbr_parse( '<p>Marcos 12 anos</p>' ) ) );
check( '"João 3 vezes" is a count', array(), mvbr_hrefs( mvbr_parse( '<p>João 3 vezes</p>' ) ) );
check( 'English count', array(), mvbr_hrefs( mvbr_parse( '<p>Mark 12 years old</p>', 'en' ) ) );
check( 'a word that starts like a count word', array( 'https://midvash.com/pt-br/onbv/joao/3' ), mvbr_hrefs( mvbr_parse( '<p>João 3 anoitece</p>' ) ) );
check( 'with a verse a count word does not matter', array( 'https://midvash.com/pt-br/onbv/efesios/5#v=25' ), mvbr_hrefs( mvbr_parse( '<p>Efésios 5:25 homens</p>' ) ) );

// --- Version of an output -------------------------------------------------------

$mvbr_opts = array( 'locale' => 'pt-br', 'versao' => 'almeida-livre' );
check( 'asked version wins', 'kjv', MVBR_Books::version_for_output( 'kjv', 'en', $mvbr_opts ) );
check( 'language override uses its default', 'bsb', MVBR_Books::version_for_output( '', 'en', $mvbr_opts ) );
check( 'same language uses the setting', 'almeida-livre', MVBR_Books::version_for_output( '', 'pt-br', $mvbr_opts ) );

// --- Parsing used by the API -----------------------------------------------------

check( 'Jn is Jonah in Portuguese', 32, MVBR_Books::parse_reference( 'Jn 1:1', 'pt-br' )['book_id'] );
check( 'parse accepts a comma', 16, MVBR_Books::parse_reference( 'João 3,16', 'pt-br' )['verse'] );

// --- Assets ---------------------------------------------------------------

$mvbr_enqueued = array();
mvbr_parse( '<p>Nada aqui.</p>' );
check( 'no assets without a reference', array(), $mvbr_enqueued );
mvbr_parse( '<p>João 3:16</p>' );
check( 'assets with a reference', array( 'mvbr-style' => true, 'mvbr-tooltip' => true ), $mvbr_enqueued );

echo "$mvbr_passes passed, $mvbr_failures failed\n";
exit( $mvbr_failures ? 1 : 0 );

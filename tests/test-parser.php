<?php
/**
 * Parser tests. No WordPress and no PHPUnit needed:
 *
 *     php tests/test-parser.php
 *
 * Stubs the handful of WordPress functions the parser calls, then checks what
 * BBMV_Parser::link_content() makes of real-world snippets.
 *
 * @package Bible_By_Midvash
 */

// phpcs:disable -- Test runner, not plugin code.

define( 'ABSPATH', __DIR__ . '/' );
define( 'BBMV_SITE_URL', 'https://midvash.com' );

$bbmv_test_options = array();
$bbmv_enqueued     = array();

function get_option( $name, $default = false ) {
	global $bbmv_test_options;
	return $bbmv_test_options ? $bbmv_test_options : $default;
}
function add_filter() {}
function wp_enqueue_style( $handle ) {
	global $bbmv_enqueued;
	$bbmv_enqueued[ $handle ] = true;
}
function wp_enqueue_script( $handle ) {
	global $bbmv_enqueued;
	$bbmv_enqueued[ $handle ] = true;
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

require __DIR__ . '/../includes/class-bbmv-books.php';
require __DIR__ . '/../includes/class-bbmv-parser.php';

/**
 * Parses $html with a fresh parser configured for $locale.
 */
function bbmv_parse( $html, $locale = 'pt-br', $extra = array() ) {
	global $bbmv_test_options;
	$bbmv_test_options = array_merge(
		array(
			'locale'    => $locale,
			'versao'    => BBMV_Books::get_default_version( $locale ),
			'css_class' => 'bbm-link',
			'new_tab'   => false,
		),
		$extra
	);
	$parser = new BBMV_Parser();
	$parser->init();
	return $parser->link_content( $html );
}

/**
 * Hrefs of the links the parser added, in order.
 */
function bbmv_hrefs( $html ) {
	preg_match_all( '/<a href="([^"]+)" class="bbm-link"/', $html, $m );
	return $m[1];
}

$bbmv_failures = 0;
$bbmv_passes   = 0;
function check( $label, $expected, $actual ) {
	global $bbmv_failures, $bbmv_passes;
	if ( $expected === $actual ) {
		++$bbmv_passes;
		return;
	}
	++$bbmv_failures;
	echo "FAIL: $label\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n";
}

// --- References in Portuguese -------------------------------------------

check(
	'full name, chapter and verse',
	array( 'https://midvash.com/pt-br/onbv/joao/3#v=16' ),
	bbmv_hrefs( bbmv_parse( '<p>Leia João 3:16 hoje.</p>' ) )
);
check(
	'verse range',
	array( 'https://midvash.com/pt-br/onbv/romanos/8#v=28-30' ),
	bbmv_hrefs( bbmv_parse( '<p>Romanos 8:28-30</p>' ) )
);
check(
	'chapter only, full name',
	array( 'https://midvash.com/pt-br/onbv/salmos/23' ),
	bbmv_hrefs( bbmv_parse( '<p>Salmos 23 é conhecido.</p>' ) )
);
check(
	'three-digit chapter and numbered book',
	array( 'https://midvash.com/pt-br/onbv/salmos/119#v=105', 'https://midvash.com/pt-br/onbv/1-corintios/13#v=4' ),
	bbmv_hrefs( bbmv_parse( '<p>Salmos 119:105 e 1 Coríntios 13:4</p>' ) )
);
check(
	'name without accent',
	array( 'https://midvash.com/pt-br/onbv/joao/3#v=16' ),
	bbmv_hrefs( bbmv_parse( '<p>Joao 3:16</p>' ) )
);
check(
	'abbreviation with verse',
	array( 'https://midvash.com/pt-br/onbv/genesis/1#v=1', 'https://midvash.com/pt-br/onbv/oseias/6#v=6' ),
	bbmv_hrefs( bbmv_parse( '<p>Gn 1:1 e Os 6:6</p>' ) )
);

// --- Words that only look like abbreviations ----------------------------

check( '"Os 13" is an article', array(), bbmv_hrefs( bbmv_parse( '<p>Os 13 apóstolos chegaram.</p>' ) ) );
check( 'lowercase "os" is never Hosea', array(), bbmv_hrefs( bbmv_parse( '<p>os 13:4 foram</p>' ) ) );
check( '"Am 5" without verse', array(), bbmv_hrefs( bbmv_parse( '<p>Am 5 dias.</p>' ) ) );
check( '"Ex 3" without verse', array(), bbmv_hrefs( bbmv_parse( '<p>Ex 3 colegas</p>' ) ) );
check( 'lowercase book name', array(), bbmv_hrefs( bbmv_parse( '<p>atos 2:1</p>' ) ) );
check( 'English book on a Portuguese site', array(), bbmv_hrefs( bbmv_parse( '<p>John 3:16</p>' ) ) );
check( 'chapter out of range', array(), bbmv_hrefs( bbmv_parse( '<p>Judas 2:1</p>' ) ) );
check( 'part of a longer word', array(), bbmv_hrefs( bbmv_parse( '<p>MarcosX 3:1</p>' ) ) );

// --- English --------------------------------------------------------------

check(
	'English links have no /en/ prefix',
	array( 'https://midvash.com/bsb/john/3#v=16' ),
	bbmv_hrefs( bbmv_parse( '<p>Read John 3:16.</p>', 'en' ) )
);
check( '"at 10:30" is a time', array(), bbmv_hrefs( bbmv_parse( '<p>Meet at 10:30, then at 10:45.</p>', 'en' ) ) );
check( '"is 5" is a verb', array(), bbmv_hrefs( bbmv_parse( '<p>The answer is 5.</p>', 'en' ) ) );
check( '"Mar 5" is a date', array(), bbmv_hrefs( bbmv_parse( '<p>Mar 5 was cold.</p>', 'en' ) ) );
check(
	'abbreviation with verse',
	array( 'https://midvash.com/bsb/mark/5#v=1' ),
	bbmv_hrefs( bbmv_parse( '<p>Mar 5:1</p>', 'en' ) )
);

// --- Other locales --------------------------------------------------------

check(
	'Italian default version is riveduta',
	array( 'https://midvash.com/it/riveduta/giovanni/3#v=16' ),
	bbmv_hrefs( bbmv_parse( '<p>Giovanni 3:16</p>', 'it' ) )
);
check(
	'Spanish "Es 5" is not a book',
	array(),
	bbmv_hrefs( bbmv_parse( '<p>Es 5 veces mejor.</p>', 'es' ) )
);
check(
	'Chinese without spaces',
	1,
	count( bbmv_hrefs( bbmv_parse( '<p>请读约翰福音3:16。</p>', 'zh' ) ) )
);
check(
	'Korean particle after the reference',
	1,
	count( bbmv_hrefs( bbmv_parse( '<p>요한복음 3:16에서</p>', 'ko' ) ) )
);

// --- HTML is left alone ---------------------------------------------------

$bbmv_img = '<p><img src="/uploads/Bíblia-João-3-16.jpg" srcset="/uploads/Bíblia-João-3-16-300.jpg 300w" alt="Bíblia aberta em João 3:16"></p>';
check( 'attributes are untouched', $bbmv_img, bbmv_parse( $bbmv_img, 'pt-br', array( 'link_biblia' => true ) ) );
check( 'existing links are skipped', '<p><a href="/x">João 3:16</a></p>', bbmv_parse( '<p><a href="/x">João 3:16</a></p>' ) );
check( 'headings are skipped', '<h2>João 3:16</h2>', bbmv_parse( '<h2>João 3:16</h2>' ) );
check( 'code is skipped', '<pre><code>João 3:16</code></pre>', bbmv_parse( '<pre><code>João 3:16</code></pre>' ) );
check(
	'text after a skipped tag is linked',
	1,
	count( bbmv_hrefs( bbmv_parse( '<h2>Título</h2><p>João 3:16</p>' ) ) )
);

// --- The word "Bíblia" ----------------------------------------------------

$bbmv_bible = bbmv_parse( '<p>A Bíblia diz. A Bíblia ensina. Bíblia!</p>', 'pt-br', array( 'link_biblia' => true ) );
check( 'only the first "Bíblia" is linked', array( 'https://midvash.com/pt-br' ), bbmv_hrefs( $bbmv_bible ) );
check( 'English "Bible" goes to the root', array( 'https://midvash.com' ), bbmv_hrefs( bbmv_parse( '<p>The Bible says.</p>', 'en', array( 'link_biblia' => true ) ) ) );
check( '"Bíblia" off by default', array(), bbmv_hrefs( bbmv_parse( '<p>A Bíblia diz.</p>' ) ) );

// --- Assets ---------------------------------------------------------------

$bbmv_enqueued = array();
bbmv_parse( '<p>Nada aqui.</p>' );
check( 'no assets without a reference', array(), $bbmv_enqueued );
bbmv_parse( '<p>João 3:16</p>' );
check( 'assets with a reference', array( 'bbm-style' => true, 'bbm-tooltip' => true ), $bbmv_enqueued );

echo "$bbmv_passes passed, $bbmv_failures failed\n";
exit( $bbmv_failures ? 1 : 0 );

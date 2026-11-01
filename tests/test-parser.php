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

// --- Assets ---------------------------------------------------------------

$mvbr_enqueued = array();
mvbr_parse( '<p>Nada aqui.</p>' );
check( 'no assets without a reference', array(), $mvbr_enqueued );
mvbr_parse( '<p>João 3:16</p>' );
check( 'assets with a reference', array( 'mvbr-style' => true, 'mvbr-tooltip' => true ), $mvbr_enqueued );

echo "$mvbr_passes passed, $mvbr_failures failed\n";
exit( $mvbr_failures ? 1 : 0 );

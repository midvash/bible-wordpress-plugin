<?php
// Roda dentro do WordPress do Playground (ver scripts/smoke-blueprint.json).
$log = array();
set_error_handler( function ( $no, $str, $file, $line ) use ( &$log ) {
	if ( false !== strpos( $file, 'bible-by-midvash' ) ) {
		$log[] = "PHP[$no] $str @ " . basename( $file ) . ":$line";
	}
	return false;
} );
$out = array( 'php' => PHP_VERSION, 'wp' => get_bloginfo( 'version' ), 'active' => is_plugin_active( 'bible-by-midvash/bible-by-midvash.php' ) );
try {
	$opts = get_option( 'bbm_options' );
	$opts['link_biblia'] = true;
	update_option( 'bbm_options', $opts );
	$id = wp_insert_post( array(
		'post_status'  => 'publish',
		'post_title'   => 'Teste',
		'post_content' => '<p><img src="/Bíblia-João-3-16.jpg" alt="Bíblia em João 3:16"></p><p>A Bíblia diz em João 3:16 e Os 6:6. Os 13 apóstolos. A Bíblia de novo.</p>',
	) );
	// Re-run init so the parser picks up the new options.
	$parser = new BBMV_Parser();
	$parser->init();
	global $wp_query, $post;
	$wp_query = new WP_Query( array( 'p' => $id ) );
	$wp_query->the_post();
	do_action( 'wp_enqueue_scripts' );
	$out['tooltip_before'] = wp_script_is( 'bbm-tooltip', 'enqueued' );
	$html = $parser->parse_content( get_the_content() );
	$out['html'] = $html;
	$out['tooltip_after'] = wp_script_is( 'bbm-tooltip', 'enqueued' );
	$out['script_src'] = wp_scripts()->registered['bbm-tooltip']->src;
	$out['img_ok'] = false !== strpos( $html, 'alt="Bíblia em João 3:16"' );
	$out['bible_links'] = substr_count( $html, 'href="https://midvash.com/pt-br" ' );
	$out['ref_links'] = substr_count( $html, 'data-midvash-ref' );
	// The blueprint sets the site to pt_BR, so this goes through the same
	// just-in-time loading as a real request (the widget translates early).
	$out['i18n_pt'] = __( 'General Settings', 'bible-by-midvash' );
	$out['version_label'] = wp_scripts()->get_data( 'bbm-tooltip', 'data' );
	$out['version_label'] = preg_match( '/"version_label":"([^"]*)"/', (string) $out['version_label'], $lm ) ? $lm[1] : null;
	$label_api = new BBMV_API();
	$label_api->get_versions();
	$out['label_almeida'] = $label_api->get_version_label( 'almeida-livre' );
	// Plugin key: sent as a header from PHP when set, absent when empty.
	$sent = null;
	add_filter( 'http_request_args', function ( $args, $url ) use ( &$sent ) {
		if ( false !== strpos( $url, 'api.midvash.com' ) ) {
			$sent = isset( $args['headers']['X-Midvash-Plugin-Key'] );
		}
		return $args;
	}, 10, 2 );
	delete_transient( BBMV_Books::CATALOG_CACHE_KEY );
	( new BBMV_API() )->get_versions();
	$out['key_header_ok'] = ( '' !== BBMV_PLUGIN_KEY ) === $sent;
	// A version with reserved rights is kept only when the catalogue has it.
	$catalog = get_transient( BBMV_Books::CATALOG_CACHE_KEY );
	$has_nvi = is_array( $catalog ) && in_array( 'nvi', wp_list_pluck( $catalog, 'slug' ), true );
	$out['nvi_resolves_to'] = BBMV_Books::resolve_version( 'nvi' ) . ( $has_nvi ? ' (catalogue lists nvi)' : ' (catalogue without nvi)' );
	set_transient( BBMV_Books::CATALOG_CACHE_KEY, array_merge( (array) $catalog, array( array( 'slug' => 'nvi', 'language' => 'pt-br', 'restricted' => true ) ) ), HOUR_IN_SECONDS );
	$out['nvi_with_key_catalogue'] = BBMV_Books::resolve_version( 'nvi' );
	set_transient( BBMV_Books::CATALOG_CACHE_KEY, $catalog, HOUR_IN_SECONDS );
	// Another post's content (a page builder's posts widget) is left alone.
	$other = wp_insert_post( array( 'post_status' => 'publish', 'post_title' => 'Outro', 'post_content' => 'João 3:16' ) );
	$GLOBALS['post'] = get_post( $other );
	setup_postdata( $GLOBALS['post'] );
	$out['other_post_linked'] = false !== strpos( $parser->parse_content( 'João 3:16' ), 'data-midvash-ref' );
	wp_reset_postdata();
	wp_set_current_user( 1 );
	set_current_screen( 'settings_page_bbm' );
	$admin = new BBMV_Admin();
	$admin->init();
	do_action( 'admin_init' );
	ob_start();
	$admin->options_page();
	$out['admin_len'] = strlen( ob_get_clean() );
	$out['votd'] = substr( wp_strip_all_tags( bbmv_votd_shortcode( array() ) ), 0, 80 );
	$block = new BBMV_Block();
	$out['block'] = substr( wp_strip_all_tags( $block->render( array( 'reference' => 'João 3:16' ) ) ), 0, 80 );
} catch ( Throwable $e ) {
	$log[] = 'FATAL ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
}
$out['errors'] = $log;
file_put_contents( '/wordpress/smoke-out/result-' . PHP_MAJOR_VERSION . PHP_MINOR_VERSION . '.json', wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

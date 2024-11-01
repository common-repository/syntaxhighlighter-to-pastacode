<?php

namespace Wabeo\S2p;

/**
 * Plugin name: SyntaxHighlighter Evolved to Pastacode
 * Plugin URI: http://pastacode.wabeo.fr
 * Description: The only use of this plugin is to convert SyntaxHighliter Evolved's shortcodes into Pastacode's Shortcodes.
 * Author: Willy Bahuaud
 * Author uri: https://wabeo.fr
 * Version: 1.0
 * Contributors: willybahuaud
 * Text Domain: syntax-highlighter-to-pastacode
 * Domain Path: /languages
 * Stable tag: 1.0
 */

define( 'SHE_2_PASTACODE_VERSION', '1.0' );

add_action( 'plugins_loaded', '\Wabeo\S2p\load_languages' );
function load_languages() {
    load_plugin_textdomain( 'syntax-highlighter-to-pastacode', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' ); 
}

/**
 * 1. Ajout de menu
 */
add_action( 'admin_menu', '\Wabeo\S2p\menu' );
function menu() {
	add_management_page( __( 'Migrate from Syntax Highlighter Evolved to Pastacode', 'syntax-highlighter-to-pastacode' ), __( 'Migrate to Pastacode', 'syntax-highlighter-to-pastacode' ), 'manage_options', 'migrate_s2p', '\Wabeo\S2p\migration_page' );
}

/**
 * 2. Formulaire d'import
 */
function migration_page() {
	$log = get_option( '_s2p_log', array() );
	echo '<div class="wrap">';
		screen_icon( 'options-general' );
		echo '<h2>' . __( 'Migrate from Syntax Highlighter Evolved to Pastacode', 'syntax-highlighter-to-pastacode' ) . '</h2>';
		echo '<form method="post" action="' . admin_url( 'admin-post.php' ) . '" id="s2p-migration">';
		echo '<input type="hidden" name="action" value="s2p-process-migration"/>';
			echo '<p>';
			submit_button( __( 'Detect and replace old shortcodes', 'syntax-highlighter-to-pastacode' ), 'primary', 'submit', false );
		echo ' <em>(' . __( 'The operation is reversible', 'syntax-highlighter-to-pastacode' ) . ')</em></p>';
		echo '</form>';
		echo '<div class="widefat" id="s2p-content-infos"><p>'
			. nl2br( implode( PHP_EOL, $log ) )
			. '</p>' 
			. '</div>';
		echo '<p id="s2p-erase-log" ' . ( empty( $log ) ? 'style="display:none;"' : '' ) . '><a href="' . admin_url( 'admin-post.php?action=s2p-delete-logs') . '" class="button button-primary primary">' . __( 'Validate the conversion and remove the logs and backups', 'syntax-highlighter-to-pastacode' ) . '</a></p>';
	echo '</div>';

	wp_enqueue_script( 'wabeo-s2p-migration' );
	wp_localize_script( 'wabeo-s2p-migration', 's2p_nonce', wp_create_nonce( 'migrate-since-' . $_SERVER['REMOTE_ADDR']  ) );
}

/**
 * register scripts
 */
add_action( 'admin_enqueue_scripts', '\Wabeo\S2p\register_scripts' );
function register_scripts() {
	wp_register_script( 'wabeo-s2p-migration', plugins_url( 'script.js', __FILE__ ), array( 'jquery' ), SHE_2_PASTACODE_VERSION, true );
}

add_action( 'wp_ajax_s2p-process-migration', '\Wabeo\S2p\process_migration' );
function process_migration() {
	if ( current_user_can( 'manage_options' ) 
	  && wp_verify_nonce( $_POST['nonce'], 'migrate-since-' . $_SERVER['REMOTE_ADDR'] ) ) {
		$log = get_option( '_s2p_log', array() );
		$args = array(
			'suppress_filters'       => false,
			'no_found_rows'          => false,
			'update_post_term_cache' => false,
			'post_type'              => apply_filters( 's2p_posttype_to_migrate', array( 'post', 'page' ) ),
			'posts_per_page'         => 40,
			'meta_query'             => array(
				's2p_found' => array(
					'key'     => '_s2p-found',
					'compare' => 'NOT EXISTS',
					),
				),
			);
		$contents = get_posts( $args );
		$data = fetch_post_contents( $contents );
		$log = $log + $data;
		update_option( '_s2p_log', $log );
		wp_send_json_success( $data );
	}
	wp_send_json_error();
}

add_action( 'admin_post_s2p-delete-logs', '\Wabeo\S2p\delete_logs' );
function delete_logs() {
	if ( current_user_can( 'manage_options' ) ) {
		delete_metadata ( 'post', null, '_s2p-found', null, true );
		delete_metadata ( 'post', null, '_sp2-old-content', null, true );
		delete_option( '_s2p_log' );
		wp_redirect( admin_url( 'tools.php?page=migrate_s2p' ) );
	}
	exit();
}

add_action( 'admin_post_restore-s2p-migration', '\Wabeo\S2p\restore_content' );
function restore_content() {
	$id = intval( $_GET['id'] );
	$log = get_option( '_s2p_log' );
	if ( $id 
	  && current_user_can( 'manage_options' )
	  && in_array( $id, array_keys( $log ) )
	  && in_array( get_post_type( $id ), apply_filters( 's2p_posttype_to_migrate', array( 'post', 'page' ) ) ) ) {

		$old_content = get_post_meta( $id, '_s2p-old-content', true );
	  	$update = wp_update_post( array(
	  	    'ID' => $id,
	  	    'post_content' => $old_content,
	  	    ), true );
	  	if ( ! is_wp_error( $update ) ) {
			delete_post_meta( $id, '_s2p-old-content' );
			delete_post_meta( $id, '_s2p-found' );
			$log[ $id ] = sprintf( __( '[%1$s] %2$s : old shortcodes restored.', 'syntax-highlighter-to-pastacode' ), get_post_type( $id ), get_the_title( $id ) );
		}
		update_option( '_s2p_log', $log );
	}
	wp_redirect( admin_url( 'tools.php?page=migrate_s2p' ) );
	exit();
}

function get_syntaxhighlighter_shortcodes_tags() {
	$s2p_shortcodes = array( 'sourcecode', 'source', 'code' );
	$s2p_brushes = apply_filters( 'syntaxhighlighter_brushes', array(
			'as3'           => 'as3',
			'actionscript3' => 'as3',
			'bash'          => 'bash',
			'shell'         => 'bash',
			'coldfusion'    => 'coldfusion',
			'cf'            => 'coldfusion',
			'clojure'       => 'clojure',
			'clj'           => 'clojure',
			'cpp'           => 'cpp',
			'c'             => 'cpp',
			'c-sharp'       => 'csharp',
			'csharp'        => 'csharp',
			'css'           => 'css',
			'delphi'        => 'delphi',
			'pas'           => 'delphi',
			'pascal'        => 'delphi',
			'diff'          => 'diff',
			'patch'         => 'diff',
			'erl'           => 'erlang',
			'erlang'        => 'erlang',
			'fsharp'        => 'fsharp',
			'groovy'        => 'groovy',
			'java'          => 'java',
			'jfx'           => 'javafx',
			'javafx'        => 'javafx',
			'js'            => 'javascript',
			'jscript'       => 'javascript',
			'javascript'    => 'javascript',
			'latex'         => 'latex', // Not used as a shortcode
			'tex'           => 'latex',
			'matlab'        => 'matlabkey',
			'objc'          => 'objc',
			'obj-c'         => 'objc',
			'perl'          => 'perl',
			'pl'            => 'perl',
			'php'           => 'php',
			'plain'         => 'plain',
			'text'          => 'plain',
			'ps'            => 'powershell',
			'powershell'    => 'powershell',
			'py'            => 'python',
			'python'        => 'python',
			'r'             => 'r', // Not used as a shortcode
			'splus'         => 'r',
			'rails'         => 'ruby',
			'rb'            => 'ruby',
			'ror'           => 'ruby',
			'ruby'          => 'ruby',
			'scala'         => 'scala',
			'sql'           => 'sql',
			'vb'            => 'vb',
			'vbnet'         => 'vb',
			'xml'           => 'xml',
			'xhtml'         => 'xml',
			'xslt'          => 'xml',
			'html'          => 'xml',
		) );
	return array( $s2p_shortcodes, $s2p_brushes );
}

function fetch_post_contents( $contents ) {
	$out = array();
	$tags = get_syntaxhighlighter_shortcodes_tags();

	foreach ( $contents as $post ) {

		preg_match_all( '@\[([^<>&/\[\]\x00-\x20=]++)@', $post->post_content, $matches );
		$tagnames = array_intersect( array_merge( array_keys( $tags[1] ), $tags[0] ), $matches[1] );
		if ( ! empty( $tagnames ) ) {
			// Do something…
			$pattern = get_shortcode_regex( $tagnames );
			$content = preg_replace_callback( "/$pattern/", '\Wabeo\S2p\replace_callback', $post->post_content );

			$update = wp_update_post( array(
				'ID'           => $post->ID,
				'post_content' => $content,
				), true );

			$liens = ' <a href="' . get_edit_post_link( $post->ID ) . '" target="_blank">' . __( 'Verify', 'syntax-highlighter-to-pastacode' ) . '</a> ' 
				. '| <a href="' . admin_url( 'admin-post.php?action=restore-s2p-migration&id=' . $post->ID ) . '" target="_blank">' . __( 'Restore', 'syntax-highlighter-to-pastacode' ) . '</a>';

			if ( ! is_wp_error( $update ) ) {
				update_post_meta( $post->ID, '_s2p-old-content', $post->post_content );
				update_post_meta( $post->ID, '_s2p-found', $tagnames );
				
				$out[ $post->ID ] = sprintf( __( '[%1$s] %2$s : shortcodes [%3$s] finds and replaced. %4$s', 'syntax-highlighter-to-pastacode' ), $post->post_type, $post->post_title, implode( ',', $tagnames ), $liens );
			} else {
				$out[ $post->ID ] = sprintf( __( '[%1$s] %2$s : shortcodes [%3$s] find, but update failed. %4$s', 'syntax-highlighter-to-pastacode' ), $post->post_type, $post->post_title, implode( ',', $tagnames ), $liens );
			}

		} else {
			// Do nothing…
			$out[ $post->ID ] = sprintf( __( '[%1$s] %2$s : no shortcode need to be converted.', 'syntax-highlighter-to-pastacode' ), $post->post_type, $post->post_title );
			update_post_meta( $post->ID, '_s2p-found', 0 );
		}
	}
	return $out;
}

function replace_callback( $matches ) {
	$tags = get_syntaxhighlighter_shortcodes_tags();
	$atts = array( 'provider' => 'provider="manual"' );

	// lang
	if ( in_array( $matches[2], $tags[0] ) ) {
		if ( preg_match( '/(?:language|lang)="([^"]+)"/', $matches[3], $atts_langs ) ) {
			$atts['lang'] = sprintf( 'lang="%s"', get_lang( $atts_langs[1] ) );
		}
	} else {
		$atts['lang'] = sprintf( 'lang="%s"', get_lang( $tags[1][ $matches[2] ] ) );
	}

	// line-highlight
	if ( preg_match( '/highlight="([0-9,-]+)"/', $matches[3], $line_highlight ) ) {
		$atts['highlight'] = sprintf( 'highlight="%s"', $line_highlight[1] );
	}

	// title
	if ( preg_match( '/title="([^"]+)"/', $matches[3], $title ) ) {
		$atts['message'] = sprintf( 'message="%s"', $title[1] );
	}

	// code
	$atts['manual'] = 'manual="' . wrap_code( $matches[5] ) . '"';

	$pastacode = sprintf( '[pastacode %s]', implode( ' ', $atts ), $code );
	return $pastacode;
}

function wrap_code( $code ) {
    $revert = array( '%21'=> '!', '%2A'=> '*', '%27'=> "'", '%28'=> '(', '%29'=>')' );
    return strtr( rawurlencode( $code ), $revert );
}

function get_lang( $lang ) {
	switch ( $lang ) {
		case 'js':
		case 'jscript':
			return 'javascript';
			break;
		case 'ps':
		case 'powershell':
		case 'shell':
			return 'bash';
			break;
		case 'rails':
		case 'ror':
		case 'rb':
			return 'ruby';
			break;
		case 'xml':
		case 'xhtml':
		case 'xslt':
		case 'html':
		case 'xhtml':
		case 'plain':
			return 'markup';
			break;
		default:
			return $lang;
	}
}

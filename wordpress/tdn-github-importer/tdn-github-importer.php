<?php
/**
 * Plugin Name: The Daily News – GitHub Story Importer
 * Description: Imports new story JSON files from a private GitHub repo as WordPress drafts every 15 minutes. Never publishes.
 * Version: 1.2.0
 * Author: The Daily News
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TDN_GitHub_Importer {
	const OPT      = 'tdn_importer_settings';
	const LOG      = 'tdn_importer_log';
	const DONE     = 'tdn_importer_done';
	const HOOK     = 'tdn_importer_run';
	const SCHEDULE = 'tdn_every_15_min';
	const CATS     = array( 'Australia', 'Business', 'Property', 'Technology', 'World' );
	const PHOTO_HOSTS = array( 'upload.wikimedia.org' );
	const UA       = 'TheDailyNewsBot/1.2 (+https://thedailynews.news)';

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_tdn_import_now', array( __CLASS__, 'import_now' ) );
		add_action( 'admin_post_tdn_self_update', array( __CLASS__, 'self_update' ) );
		add_action( 'init', array( __CLASS__, 'ensure_scheduled' ) );
	}

	public static function schedules( $s ) {
		$s[ self::SCHEDULE ] = array( 'interval' => 15 * MINUTE_IN_SECONDS, 'display' => 'Every 15 minutes' );
		return $s;
	}

	public static function ensure_scheduled() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 60, self::SCHEDULE, self::HOOK );
		}
	}

	public static function activate() {
		foreach ( self::CATS as $c ) {
			if ( ! term_exists( $c, 'category' ) ) {
				wp_insert_term( $c, 'category' );
			}
		}
		$defaults = array( 'owner' => 'mambojuali', 'repo' => 'thedailynews-stories', 'branch' => 'main', 'token' => '' );
		add_option( self::OPT, $defaults );
		self::ensure_scheduled();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	private static function settings() {
		return wp_parse_args( get_option( self::OPT, array() ), array( 'owner' => '', 'repo' => '', 'branch' => 'main', 'token' => '' ) );
	}

	private static function log( $msg ) {
		$log   = get_option( self::LOG, array() );
		$log[] = current_time( 'mysql' ) . ' — ' . $msg;
		update_option( self::LOG, array_slice( $log, -50 ), false );
	}

	private static function gh( $path, $raw = false ) {
		$s    = self::settings();
		$args = array(
			'timeout' => 20,
			'headers' => array(
				'Accept'               => $raw ? 'application/vnd.github.raw+json' : 'application/vnd.github+json',
				'Authorization'        => 'Bearer ' . $s['token'],
				'X-GitHub-Api-Version' => '2022-11-28',
				'User-Agent'           => 'tdn-github-importer',
			),
		);
		$res = wp_remote_get( 'https://api.github.com/repos/' . rawurlencode( $s['owner'] ) . '/' . rawurlencode( $s['repo'] ) . $path, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		if ( 200 !== $code ) {
			return new WP_Error( 'gh_' . $code, 'GitHub returned HTTP ' . $code . ' for ' . $path );
		}
		return wp_remote_retrieve_body( $res );
	}

	public static function run() {
		$s = self::settings();
		if ( empty( $s['token'] ) || empty( $s['owner'] ) || empty( $s['repo'] ) ) {
			self::log( 'Skipped: GitHub settings incomplete.' );
			return 0;
		}
		$body = self::gh( '/git/trees/' . rawurlencode( $s['branch'] ) . '?recursive=1' );
		if ( is_wp_error( $body ) ) {
			self::log( 'Error: ' . $body->get_error_message() );
			return 0;
		}
		$tree = json_decode( $body, true );
		$done = get_option( self::DONE, array() );
		$new  = 0;
		$pngs = array();
		foreach ( (array) ( $tree['tree'] ?? array() ) as $item ) {
			$p = $item['path'] ?? '';
			if ( 'blob' === ( $item['type'] ?? '' ) && 0 === strpos( $p, 'stories/' ) && '.png' === substr( $p, -4 ) ) {
				$pngs[ $p ] = true;
			}
		}

		self::ensure_terms();

		// Backfill already-imported stories: licensed photo first, headline card as fallback.
		$budget = 6; // limit photo lookups per run
		foreach ( $done as $jpath => $pid ) {
			if ( ! is_int( $pid ) || ! get_post( $pid ) ) {
				continue;
			}
			if ( $budget > 0 && ! get_post_meta( $pid, '_tdn_photo_status', true ) ) {
				$budget--;
				$raw = self::gh( '/contents/' . implode( '/', array_map( 'rawurlencode', explode( '/', $jpath ) ) ) . '?ref=' . rawurlencode( $s['branch'] ), true );
				$st  = is_wp_error( $raw ) ? null : json_decode( $raw, true );
				if ( is_array( $st ) ) {
					self::attach_photo( $pid, $st );
				}
			}
			$png = substr( $jpath, 0, -5 ) . '.png';
			if ( isset( $pngs[ $png ] ) && ! has_post_thumbnail( $pid ) ) {
				self::attach_card( $pid, $png );
			}
		}

		foreach ( (array) ( $tree['tree'] ?? array() ) as $item ) {
			$path = $item['path'] ?? '';
			if ( 'blob' !== ( $item['type'] ?? '' ) || 0 !== strpos( $path, 'stories/' ) || '.json' !== substr( $path, -5 ) ) {
				continue;
			}
			if ( isset( $done[ $path ] ) ) {
				continue;
			}
			$raw = self::gh( '/contents/' . implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) ) . '?ref=' . rawurlencode( $s['branch'] ), true );
			if ( is_wp_error( $raw ) ) {
				self::log( 'Error fetching ' . $path . ': ' . $raw->get_error_message() );
				continue;
			}
			$story = json_decode( $raw, true );
			if ( ! is_array( $story ) || empty( $story['title'] ) || empty( $story['content_html'] ) ) {
				self::log( 'Invalid story JSON: ' . $path );
				$done[ $path ] = 'invalid';
				continue;
			}
			$post_id = self::create_draft( $story, $path );
			if ( $post_id ) {
				self::attach_photo( $post_id, $story );
				$png = substr( $path, 0, -5 ) . '.png';
				if ( isset( $pngs[ $png ] ) && ! has_post_thumbnail( $post_id ) ) {
					self::attach_card( $post_id, $png );
				}
				$done[ $path ] = $post_id;
				$new++;
				self::log( 'Draft #' . $post_id . ' created: ' . $story['title'] );
			}
		}
		update_option( self::DONE, $done, false );
		if ( 0 === $new ) {
			self::log( 'Checked GitHub: no new stories.' );
		}
		return $new;
	}

	private static function ensure_terms() {
		foreach ( self::CATS as $c ) {
			if ( ! term_exists( $c, 'category' ) ) {
				wp_insert_term( $c, 'category' );
			}
		}
	}

	/**
	 * Find a freely licensed photo on Wikimedia Commons for the story's image_query
	 * and set it as the featured image, with a credit caption. Returns attachment ID or 0.
	 */
	private static function attach_photo( $post_id, $story ) {
		$queries = $story['image_query'] ?? array();
		if ( is_string( $queries ) ) {
			$queries = array( $queries );
		}
		$queries = array_slice( array_filter( array_map( 'trim', (array) $queries ) ), 0, 3 );
		if ( ! $queries ) {
			update_post_meta( $post_id, '_tdn_photo_status', 'no-query' );
			return 0;
		}
		foreach ( $queries as $q ) {
			$hit = self::commons_search( $q );
			if ( ! $hit ) {
				continue;
			}
			$att = self::sideload_photo( $post_id, $hit );
			if ( $att ) {
				set_post_thumbnail( $post_id, $att );
				update_post_meta( $post_id, '_tdn_photo_status', 'ok' );
				self::log( 'Photo attached to #' . $post_id . ' (' . $q . ')' );
				return $att;
			}
		}
		update_post_meta( $post_id, '_tdn_photo_status', 'none' );
		self::log( 'No licensed photo found for #' . $post_id );
		return 0;
	}

	private static function commons_search( $query ) {
		$url = add_query_arg(
			array(
				'action'       => 'query',
				'format'       => 'json',
				'generator'    => 'search',
				'gsrsearch'    => rawurlencode( $query . ' filetype:bitmap' ),
				'gsrnamespace' => 6,
				'gsrlimit'     => 15,
				'prop'         => 'imageinfo',
				'iiprop'       => rawurlencode( 'url|size|mime|extmetadata' ),
				'iiurlwidth'   => 1600,
			),
			'https://commons.wikimedia.org/w/api.php'
		);
		$res = wp_remote_get( $url, array( 'timeout' => 20, 'headers' => array( 'User-Agent' => self::UA ) ) );
		if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
			self::log( 'Commons search failed for "' . $query . '"' );
			return null;
		}
		$data  = json_decode( wp_remote_retrieve_body( $res ), true );
		$pages = $data['query']['pages'] ?? array();
		uasort(
			$pages,
			function ( $a, $b ) {
				return ( $a['index'] ?? 99 ) <=> ( $b['index'] ?? 99 );
			}
		);
		foreach ( $pages as $pg ) {
			$ii = $pg['imageinfo'][0] ?? null;
			if ( ! $ii || ! in_array( $ii['mime'] ?? '', array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
				continue;
			}
			$w = (int) ( $ii['width'] ?? 0 );
			$h = (int) ( $ii['height'] ?? 0 );
			if ( $w < 1000 || $w < $h * 1.2 ) {
				continue; // landscape and reasonably large only
			}
			$meta    = $ii['extmetadata'] ?? array();
			$license = trim( wp_strip_all_tags( $meta['LicenseShortName']['value'] ?? '' ) );
			if ( ! preg_match( '/^(CC0|CC BY(-SA)?( \d\.\d)?|Public domain|PD\b)/i', $license ) ) {
				continue; // reusable licences only
			}
			$title = strtolower( $pg['title'] ?? '' );
			if ( preg_match( '/portrait|headshot|selfie|mugshot/', $title ) ) {
				continue; // no photos of individuals
			}
			$thumb = $ii['thumburl'] ?? $ii['url'];
			if ( ! in_array( wp_parse_url( $thumb, PHP_URL_HOST ), self::PHOTO_HOSTS, true ) ) {
				continue;
			}
			$artist = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $meta['Artist']['value'] ?? 'Unknown' ) ) );
			return array(
				'url'     => $thumb,
				'page'    => $ii['descriptionurl'] ?? '',
				'artist'  => mb_substr( $artist ?: 'Unknown', 0, 80 ),
				'license' => $license,
				'lic_url' => esc_url_raw( $meta['LicenseUrl']['value'] ?? '' ),
				'title'   => preg_replace( '/^File:|\.[a-z0-9]+$/i', '', $pg['title'] ?? '' ),
				'mime'    => $ii['thumbmime'] ?? $ii['mime'],
			);
		}
		return null;
	}

	private static function sideload_photo( $post_id, $hit ) {
		$res = wp_remote_get( $hit['url'], array( 'timeout' => 30, 'headers' => array( 'User-Agent' => self::UA ) ) );
		if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
			return 0;
		}
		$bin = wp_remote_retrieve_body( $res );
		if ( strlen( $bin ) < 5000 ) {
			return 0;
		}
		$ext = ( false !== strpos( $hit['mime'], 'png' ) ) ? 'png' : ( ( false !== strpos( $hit['mime'], 'webp' ) ) ? 'webp' : 'jpg' );
		$up  = wp_upload_bits( sanitize_file_name( 'tdn-' . $post_id . '-' . substr( md5( $hit['url'] ), 0, 8 ) . '.' . $ext ), null, $bin );
		if ( ! empty( $up['error'] ) ) {
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$credit = 'Photo: ' . $hit['artist'] . ' / Wikimedia Commons, ' . $hit['license'];
		$att    = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/' . ( 'jpg' === $ext ? 'jpeg' : $ext ),
				'post_title'     => $hit['title'],
				'post_excerpt'   => $credit,
				'post_status'    => 'inherit',
			),
			$up['file'],
			$post_id
		);
		if ( is_wp_error( $att ) || ! $att ) {
			return 0;
		}
		wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $up['file'] ) );
		update_post_meta( $att, '_wp_attachment_image_alt', $hit['title'] );
		update_post_meta( $att, '_tdn_source_page', esc_url_raw( $hit['page'] ) );
		update_post_meta( $att, '_tdn_license_url', $hit['lic_url'] );
		return $att;
	}

	/** Admin: pull the latest plugin + theme files from the repo's wordpress/ folder. */
	public static function self_update() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'tdn_self_update' ) ) {
			wp_die( 'Not allowed' );
		}
		$s    = self::settings();
		$body = self::gh( '/git/trees/' . rawurlencode( $s['branch'] ) . '?recursive=1' );
		$tree = is_wp_error( $body ) ? array() : json_decode( $body, true );
		$map  = array(
			'wordpress/tdn-github-importer/' => trailingslashit( WP_PLUGIN_DIR ) . 'tdn-github-importer/',
			'wordpress/tdn-news/'            => trailingslashit( get_theme_root() ) . 'tdn-news/',
		);
		$n    = 0;
		foreach ( (array) ( $tree['tree'] ?? array() ) as $item ) {
			if ( 'blob' !== ( $item['type'] ?? '' ) ) {
				continue;
			}
			foreach ( $map as $prefix => $dest ) {
				if ( 0 !== strpos( $item['path'], $prefix ) ) {
					continue;
				}
				$rel = substr( $item['path'], strlen( $prefix ) );
				if ( '' === $rel || false !== strpos( $rel, '..' ) || ! preg_match( '/\.(php|css|js|txt|md)$/', $rel ) ) {
					continue;
				}
				$raw = self::gh( '/contents/' . implode( '/', array_map( 'rawurlencode', explode( '/', $item['path'] ) ) ) . '?ref=' . rawurlencode( $s['branch'] ), true );
				if ( is_wp_error( $raw ) || '' === $raw ) {
					continue;
				}
				wp_mkdir_p( dirname( $dest . $rel ) );
				if ( false !== file_put_contents( $dest . $rel, $raw ) ) {
					$n++;
				}
			}
		}
		self::log( 'Self-update: ' . $n . ' file(s) refreshed from GitHub.' );
		wp_safe_redirect( admin_url( 'options-general.php?page=tdn-importer&updated=' . $n ) );
		exit;
	}

	/** Download a headline card PNG from the repo and set it as the post's featured image. */
	private static function attach_card( $post_id, $png_path ) {
		$s   = self::settings();
		$bin = self::gh( '/contents/' . implode( '/', array_map( 'rawurlencode', explode( '/', $png_path ) ) ) . '?ref=' . rawurlencode( $s['branch'] ), true );
		if ( is_wp_error( $bin ) || strlen( $bin ) < 100 ) {
			self::log( 'Card download failed: ' . $png_path );
			return 0;
		}
		$name   = sanitize_file_name( basename( $png_path ) );
		$upload = wp_upload_bits( $name, null, $bin );
		if ( ! empty( $upload['error'] ) ) {
			self::log( 'Card save failed: ' . $upload['error'] );
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$title  = get_the_title( $post_id );
		$att_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => $title,
				'post_status'    => 'inherit',
			),
			$upload['file'],
			$post_id
		);
		if ( is_wp_error( $att_id ) || ! $att_id ) {
			return 0;
		}
		wp_update_attachment_metadata( $att_id, wp_generate_attachment_metadata( $att_id, $upload['file'] ) );
		update_post_meta( $att_id, '_wp_attachment_image_alt', $title );
		set_post_thumbnail( $post_id, $att_id );
		self::log( 'Card attached to #' . $post_id );
		return $att_id;
	}

	private static function create_draft( $story, $path ) {
		$sid = sanitize_text_field( $story['id'] ?? $path );
		$dup = get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'meta_key' => '_tdn_story_id', 'meta_value' => $sid, 'fields' => 'ids', 'numberposts' => 1 ) );
		if ( $dup ) {
			return (int) $dup[0];
		}

		$content = wp_kses_post( $story['content_html'] );
		$sources = (array) ( $story['sources'] ?? array() );
		if ( $sources ) {
			$content .= "\n<h2>Sources</h2>\n<ul>";
			foreach ( $sources as $src ) {
				$url = esc_url( $src['url'] ?? '' );
				$nm  = esc_html( $src['name'] ?? $url );
				if ( $url ) {
					$content .= '<li><a href="' . $url . '" target="_blank" rel="noopener nofollow">' . $nm . '</a></li>';
				}
			}
			$content .= '</ul>';
		}

		$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
		$cat    = in_array( $story['category'] ?? '', self::CATS, true ) ? $story['category'] : 'Australia';
		$term   = term_exists( $cat, 'category' );
		if ( ! $term ) {
			$term = wp_insert_term( $cat, 'category' );
		}
		$cat_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;

		$post_id = wp_insert_post(
			array(
				'post_title'    => sanitize_text_field( $story['title'] ),
				'post_name'     => sanitize_title( $story['slug'] ?? $story['title'] ),
				'post_excerpt'  => sanitize_text_field( $story['excerpt'] ?? '' ),
				'post_content'  => $content,
				'post_status'   => 'draft',
				'post_author'   => $admins ? (int) $admins[0] : 1,
				'post_category' => array( $cat_id ),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			self::log( 'Insert failed for ' . $path . ': ' . $post_id->get_error_message() );
			return 0;
		}
		wp_set_post_tags( $post_id, array_map( 'sanitize_text_field', (array) ( $story['tags'] ?? array() ) ) );
		update_post_meta( $post_id, '_tdn_story_id', $sid );
		update_post_meta( $post_id, '_tdn_source_path', sanitize_text_field( $path ) );
		update_post_meta( $post_id, '_tdn_format', sanitize_text_field( $story['format'] ?? '' ) );
		update_post_meta( $post_id, '_tdn_editor_notes', sanitize_textarea_field( $story['editor_notes'] ?? '' ) );
		return $post_id;
	}

	/* ---------- Admin ---------- */

	public static function menu() {
		add_options_page( 'Daily News Importer', 'Daily News Importer', 'manage_options', 'tdn-importer', array( __CLASS__, 'page' ) );
	}

	public static function register_settings() {
		register_setting(
			'tdn_importer',
			self::OPT,
			array(
				'sanitize_callback' => function ( $in ) {
					$old = self::settings();
					return array(
						'owner'  => sanitize_text_field( $in['owner'] ?? '' ),
						'repo'   => sanitize_text_field( $in['repo'] ?? '' ),
						'branch' => sanitize_text_field( $in['branch'] ?? 'main' ),
						'token'  => ( isset( $in['token'] ) && '' !== trim( $in['token'] ) ) ? trim( $in['token'] ) : $old['token'],
					);
				},
			)
		);
	}

	public static function import_now() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'tdn_import_now' ) ) {
			wp_die( 'Not allowed' );
		}
		$n = self::run();
		wp_safe_redirect( admin_url( 'options-general.php?page=tdn-importer&imported=' . (int) $n ) );
		exit;
	}

	public static function page() {
		$s    = self::settings();
		$log  = array_reverse( get_option( self::LOG, array() ) );
		$next = wp_next_scheduled( self::HOOK );
		?>
		<div class="wrap">
			<h1>Daily News – GitHub Story Importer</h1>
			<?php if ( isset( $_GET['imported'] ) ) : ?>
				<div class="notice notice-success"><p><?php echo (int) $_GET['imported']; ?> new draft(s) imported.</p></div>
			<?php endif; ?>
			<p>Stories committed to <code>stories/**/*.json</code> are imported as <strong>drafts</strong>. Review them under <a href="<?php echo esc_url( admin_url( 'edit.php?post_status=draft' ) ); ?>">Posts → Drafts</a> and publish the ones you approve.</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'tdn_importer' ); ?>
				<table class="form-table">
					<tr><th>GitHub owner</th><td><input name="<?php echo esc_attr( self::OPT ); ?>[owner]" value="<?php echo esc_attr( $s['owner'] ); ?>" class="regular-text"></td></tr>
					<tr><th>Repository</th><td><input name="<?php echo esc_attr( self::OPT ); ?>[repo]" value="<?php echo esc_attr( $s['repo'] ); ?>" class="regular-text"></td></tr>
					<tr><th>Branch</th><td><input name="<?php echo esc_attr( self::OPT ); ?>[branch]" value="<?php echo esc_attr( $s['branch'] ); ?>" class="regular-text"></td></tr>
					<tr><th>GitHub token</th><td><input type="password" name="<?php echo esc_attr( self::OPT ); ?>[token]" value="" placeholder="<?php echo $s['token'] ? 'Saved — leave blank to keep' : 'Fine-grained token, Contents: Read-only'; ?>" class="regular-text" autocomplete="off">
						<p class="description">Create at GitHub → Settings → Developer settings → Fine-grained tokens. Repository access: only <code>thedailynews-stories</code>. Permissions: Contents → Read-only.</p></td></tr>
				</table>
				<?php submit_button( 'Save settings' ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tdn_import_now">
				<?php wp_nonce_field( 'tdn_import_now' ); ?>
				<?php submit_button( 'Import now', 'secondary' ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tdn_self_update">
				<?php wp_nonce_field( 'tdn_self_update' ); ?>
				<?php submit_button( 'Update plugin & theme from GitHub', 'secondary' ); ?>
			</form>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success"><p><?php echo (int) $_GET['updated']; ?> file(s) updated from GitHub.</p></div>
			<?php endif; ?>
			<p>Next automatic check: <?php echo $next ? esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next ), 'D j M, g:i a' ) ) : 'not scheduled'; ?></p>
			<h2>Recent activity</h2>
			<pre style="background:#fff;padding:12px;max-height:320px;overflow:auto"><?php echo esc_html( implode( "\n", $log ) ?: 'Nothing yet.' ); ?></pre>
		</div>
		<?php
	}
}

TDN_GitHub_Importer::init();
register_activation_hook( __FILE__, array( 'TDN_GitHub_Importer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TDN_GitHub_Importer', 'deactivate' ) );

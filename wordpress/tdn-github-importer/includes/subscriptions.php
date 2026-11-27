<?php
/**
 * Email subscriptions: readers pick sections and get each new story ("instant")
 * or a 7am daily digest. Double opt-in, one-click unsubscribe (Spam Act 2003).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TDN_Subs {
	const DBV     = 2;
	const BATCH   = 40;
	const SECTIONS = array(
		'australia'  => 'Australia',
		'business'   => 'Business',
		'property'   => 'Property',
		'sports'     => 'Sports',
		'technology' => 'Technology',
		'world'      => 'World',
	);

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ) );
		add_action( 'init', array( __CLASS__, 'ensure_daily' ) );
		add_action( 'admin_post_nopriv_tdn_subscribe', array( __CLASS__, 'handle_subscribe' ) );
		add_action( 'admin_post_tdn_subscribe', array( __CLASS__, 'handle_subscribe' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_links' ), 1 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_publish' ), 10, 3 );
		add_action( 'tdn_subs_instant', array( __CLASS__, 'send_instant' ) );
		add_action( 'tdn_subs_daily', array( __CLASS__, 'send_daily' ) );
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'tdn_subscribers';
	}

	public static function maybe_install() {
		if ( (int) get_option( 'tdn_subs_db' ) >= self::DBV ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t   = self::table();
		$cs  = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE $t (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(190) NOT NULL,
			cats varchar(255) NOT NULL DEFAULT '',
			freq varchar(10) NOT NULL DEFAULT 'daily',
			pending_cats varchar(255) NULL,
			pending_freq varchar(10) NULL,
			token char(32) NOT NULL,
			status varchar(12) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL,
			confirmed_at datetime NULL,
			last_digest datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY status (status)
		) $cs;";
		dbDelta( $sql );
		update_option( 'tdn_subs_db', self::DBV );
	}

	/** Daily digest at 7:00 site time. */
	public static function ensure_daily() {
		if ( wp_next_scheduled( 'tdn_subs_daily' ) ) {
			return;
		}
		$tz   = wp_timezone();
		$next = new DateTime( 'today 07:00', $tz );
		if ( $next->getTimestamp() <= time() ) {
			$next->modify( '+1 day' );
		}
		wp_schedule_event( $next->getTimestamp(), 'daily', 'tdn_subs_daily' );
	}

	/* ---------- Form ---------- */

	public static function form( $context = 'band' ) {
		$id = 'tdn-sub-' . esc_attr( $context );
		ob_start();
		?>
		<form class="tdn-subscribe-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tdn_subscribe">
			<input type="hidden" name="back" value="<?php echo esc_attr( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ); ?>">
			<div class="tdn-hp" aria-hidden="true"><label>Leave blank <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
			<fieldset class="tdn-sub-cats">
				<legend>Sections</legend>
				<?php foreach ( self::SECTIONS as $slug => $name ) : ?>
					<label><input type="checkbox" name="cats[]" value="<?php echo esc_attr( $slug ); ?>" checked> <?php echo esc_html( $name ); ?></label>
				<?php endforeach; ?>
			</fieldset>
			<fieldset class="tdn-sub-freq">
				<legend>How often</legend>
				<label><input type="radio" name="freq" value="daily" checked> Daily digest (7am)</label>
				<label><input type="radio" name="freq" value="instant"> Every new story</label>
			</fieldset>
			<div class="tdn-sub-row">
				<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>">Email address</label>
				<input id="<?php echo esc_attr( $id ); ?>" type="email" name="email" required placeholder="Your email address" autocomplete="email">
				<button type="submit">Subscribe</button>
			</div>
			<p class="tdn-sub-fine">We'll email you a link to confirm. Unsubscribe any time with one click. We never share your email.</p>
		</form>
		<?php
		return ob_get_clean();
	}

	/* ---------- Handlers ---------- */

	public static function handle_subscribe() {
		$back = wp_validate_redirect( home_url( wp_unslash( $_POST['back'] ?? '/' ) ), home_url( '/' ) );
		$go   = function ( $code ) use ( $back ) {
			wp_safe_redirect( add_query_arg( 'tdn_notice', $code, $back ) . '#subscribe' );
			exit;
		};
		if ( ! empty( $_POST['website'] ) ) {
			$go( 'check' ); // honeypot: pretend success
		}
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			$go( 'bademail' );
		}
		$ipkey = 'tdn_sub_rl_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' );
		$hits  = (int) get_transient( $ipkey );
		if ( $hits >= 5 ) {
			$go( 'slow' );
		}
		set_transient( $ipkey, $hits + 1, HOUR_IN_SECONDS );

		$cats = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $_POST['cats'] ?? array() ) ), array_keys( self::SECTIONS ) ) );
		if ( ! $cats ) {
			$go( 'nocats' );
		}
		$freq    = ( 'instant' === ( $_POST['freq'] ?? '' ) ) ? 'instant' : 'daily';
		$catsstr = ',' . implode( ',', $cats ) . ',';

		global $wpdb;
		$t   = self::table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE email = %s", strtolower( $email ) ) );
		$tok = wp_generate_password( 32, false, false );
		if ( $row ) {
			// Changes only take effect after the owner of the address confirms.
			$wpdb->update( $t, array( 'pending_cats' => $catsstr, 'pending_freq' => $freq, 'token' => $tok ), array( 'id' => $row->id ) );
		} else {
			$wpdb->insert(
				$t,
				array(
					'email'        => strtolower( $email ),
					'cats'         => '',
					'freq'         => $freq,
					'pending_cats' => $catsstr,
					'pending_freq' => $freq,
					'token'        => $tok,
					'status'       => 'pending',
					'created_at'   => current_time( 'mysql', true ),
				)
			);
		}
		$link = add_query_arg( array( 'tdn_sub' => 'confirm', 't' => $tok ), home_url( '/' ) );
		$names = implode( ', ', array_map( function ( $c ) { return self::SECTIONS[ $c ]; }, $cats ) );
		$html  = self::wrap(
			'<h1 style="font:700 24px Georgia,serif;margin:0 0 12px">Confirm your subscription</h1>' .
			'<p>Please confirm you\'d like ' . ( 'instant' === $freq ? 'an email for every new story' : 'a 7am daily digest' ) . ' from The Daily News covering: <strong>' . esc_html( $names ) . '</strong>.</p>' .
			'<p style="margin:24px 0"><a href="' . esc_url( $link ) . '" style="background:#c4161c;color:#fff;padding:12px 22px;text-decoration:none;font-weight:700;border-radius:3px">Confirm subscription</a></p>' .
			'<p style="color:#5b6270;font-size:13px">If you didn\'t ask for this, ignore this email and nothing will happen.</p>',
			''
		);
		self::send( $email, 'Confirm your subscription to The Daily News', $html, '' );
		$go( 'check' );
	}

	public static function handle_links() {
		$act = sanitize_key( $_GET['tdn_sub'] ?? '' );
		$tok = preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $_GET['t'] ?? '' ) );
		if ( ! $act || strlen( $tok ) !== 32 ) {
			return;
		}
		global $wpdb;
		$t   = self::table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE token = %s", $tok ) );
		$to  = home_url( '/' );
		if ( ! $row ) {
			wp_safe_redirect( add_query_arg( 'tdn_notice', 'badlink', $to ) );
			exit;
		}
		if ( 'confirm' === $act ) {
			$wpdb->update(
				$t,
				array(
					'status'       => 'active',
					'cats'         => $row->pending_cats ?: $row->cats,
					'freq'         => $row->pending_freq ?: $row->freq,
					'pending_cats' => null,
					'pending_freq' => null,
					'confirmed_at' => current_time( 'mysql', true ),
					'last_digest'  => current_time( 'mysql', true ),
				),
				array( 'id' => $row->id )
			);
			wp_safe_redirect( add_query_arg( 'tdn_notice', 'confirmed', $to ) );
			exit;
		}
		if ( 'unsub' === $act ) {
			$wpdb->update( $t, array( 'status' => 'unsub' ), array( 'id' => $row->id ) );
			if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
				status_header( 200 );
				exit; // RFC 8058 one-click
			}
			wp_safe_redirect( add_query_arg( 'tdn_notice', 'unsubscribed', $to ) );
			exit;
		}
	}

	/* ---------- Sending ---------- */

	public static function on_publish( $new, $old, $post ) {
		if ( 'publish' !== $new || 'publish' === $old || 'post' !== $post->post_type ) {
			return;
		}
		if ( get_post_meta( $post->ID, '_tdn_notified', true ) ) {
			return;
		}
		update_post_meta( $post->ID, '_tdn_notified', 'queued' );
		update_post_meta( $post->ID, '_tdn_notify_last', 0 );
		wp_schedule_single_event( time() + 300, 'tdn_subs_instant', array( (int) $post->ID ) );
	}

	private static function post_slugs( $post_id ) {
		return wp_list_pluck( get_the_category( $post_id ), 'slug' );
	}

	public static function send_instant( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}
		$slugs = array_intersect( self::post_slugs( $post_id ), array_keys( self::SECTIONS ) );
		if ( ! $slugs ) {
			update_post_meta( $post_id, '_tdn_notified', 'none' );
			return;
		}
		global $wpdb;
		$t    = self::table();
		$last = (int) get_post_meta( $post_id, '_tdn_notify_last', true );
		$like = array();
		foreach ( $slugs as $s ) {
			$like[] = $wpdb->prepare( 'cats LIKE %s', '%,' . $wpdb->esc_like( $s ) . ',%' );
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE status = 'active' AND freq = 'instant' AND id > %d AND (" . implode( ' OR ', $like ) . ') ORDER BY id ASC LIMIT %d', $last, self::BATCH ) );
		foreach ( $rows as $r ) {
			$html = self::wrap( self::story_block( $post ), $r->token, $r->cats );
			self::send( $r->email, get_the_title( $post ), $html, $r->token );
			$last = (int) $r->id;
		}
		update_post_meta( $post_id, '_tdn_notify_last', $last );
		if ( count( $rows ) === self::BATCH ) {
			wp_schedule_single_event( time() + 120, 'tdn_subs_instant', array( (int) $post_id ) );
		} else {
			update_post_meta( $post_id, '_tdn_notified', 'done' );
		}
	}

	public static function send_daily() {
		global $wpdb;
		$t     = self::table();
		$start = gmdate( 'Y-m-d H:i:s', time() - 20 * HOUR_IN_SECONDS );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE status = 'active' AND freq = 'daily' AND (last_digest IS NULL OR last_digest < %s) ORDER BY id ASC LIMIT %d", $start, self::BATCH ) );
		$since = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		foreach ( $rows as $r ) {
			$cats = array_filter( explode( ',', $r->cats ) );
			$ids  = array();
			foreach ( $cats as $c ) {
				$term = get_category_by_slug( $c );
				if ( $term ) {
					$ids[] = $term->term_id;
				}
			}
			$posts = $ids ? get_posts(
				array(
					'numberposts'   => 15,
					'post_status'   => 'publish',
					'category__in'  => $ids,
					'date_query'    => array( array( 'after' => $since, 'column' => 'post_date_gmt' ) ),
					'orderby'       => 'date',
					'order'         => 'DESC',
				)
			) : array();
			if ( $posts ) {
				$body = '<h1 style="font:700 24px Georgia,serif;margin:0 0 6px">Your daily briefing</h1><p style="color:#5b6270;margin:0 0 18px">' . esc_html( wp_date( 'l, j F Y' ) ) . '</p>';
				foreach ( $posts as $p ) {
					$body .= self::story_block( $p );
				}
				self::send( $r->email, 'The Daily News: ' . get_the_title( $posts[0] ) . ( count( $posts ) > 1 ? ' + ' . ( count( $posts ) - 1 ) . ' more' : '' ), self::wrap( $body, $r->token, $r->cats ), $r->token );
			}
			$wpdb->update( $t, array( 'last_digest' => current_time( 'mysql', true ) ), array( 'id' => $r->id ) );
		}
		if ( count( $rows ) === self::BATCH ) {
			wp_schedule_single_event( time() + 120, 'tdn_subs_daily' );
		}
	}

	private static function story_block( $p ) {
		$cat   = function_exists( 'tdn_primary_cat' ) ? tdn_primary_cat( $p->ID ) : null;
		$img   = get_the_post_thumbnail_url( $p, 'tdn-thumb' );
		$url   = get_permalink( $p );
		$h     = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-bottom:1px solid #e3e5e9;margin:0 0 14px"><tr>';
		if ( $img ) {
			$h .= '<td width="132" valign="top" style="padding:0 14px 14px 0"><a href="' . esc_url( $url ) . '"><img src="' . esc_url( $img ) . '" width="132" alt="" style="display:block;border-radius:3px"></a></td>';
		}
		$h .= '<td valign="top" style="padding:0 0 14px">';
		if ( $cat ) {
			$h .= '<div style="font:700 11px Arial,sans-serif;letter-spacing:.1em;text-transform:uppercase;color:#c4161c;margin-bottom:4px">' . esc_html( $cat->name ) . '</div>';
		}
		$h .= '<a href="' . esc_url( $url ) . '" style="font:700 18px/1.3 Arial,sans-serif;color:#14171c;text-decoration:none">' . esc_html( get_the_title( $p ) ) . '</a>';
		$h .= '<p style="font:14px/1.5 Arial,sans-serif;color:#3b414c;margin:6px 0 0">' . esc_html( wp_trim_words( get_the_excerpt( $p ), 30, '…' ) ) . '</p>';
		$h .= '</td></tr></table>';
		return $h;
	}

	private static function wrap( $inner, $token, $cats = '' ) {
		$foot = '';
		if ( $token ) {
			$unsub = add_query_arg( array( 'tdn_sub' => 'unsub', 't' => $token ), home_url( '/' ) );
			$names = implode( ', ', array_filter( array_map( function ( $c ) { return self::SECTIONS[ $c ] ?? ''; }, explode( ',', $cats ) ) ) );
			$foot  = '<p>You\'re receiving this because you subscribed at thedailynews.news' . ( $names ? ' to: ' . esc_html( $names ) : '' ) . '.<br><a href="' . esc_url( $unsub ) . '" style="color:#5b6270">Unsubscribe</a> · <a href="' . esc_url( home_url( '/#subscribe' ) ) . '" style="color:#5b6270">Change sections</a></p>';
		}
		return '<!doctype html><html><body style="margin:0;background:#f5f6f8"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff">'
			. '<tr><td style="background:#0d2a4d;padding:18px 24px;border-bottom:4px solid #c4161c"><a href="' . esc_url( home_url( '/' ) ) . '" style="font:700 26px Georgia,serif;color:#fff;text-decoration:none">The Daily News</a></td></tr>'
			. '<tr><td style="padding:24px;font:15px/1.55 Arial,sans-serif;color:#14171c">' . $inner . '</td></tr>'
			. '<tr><td style="padding:16px 24px;background:#f5f6f8;font:12px/1.5 Arial,sans-serif;color:#5b6270">' . $foot . '<p>The Daily News · <a href="' . esc_url( home_url( '/' ) ) . '" style="color:#5b6270">thedailynews.news</a></p></td></tr>'
			. '</table></td></tr></table></body></html>';
	}

	private static function send( $to, $subject, $html, $token ) {
		$host    = wp_parse_url( home_url(), PHP_URL_HOST );
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: The Daily News <news@' . $host . '>',
		);
		if ( $token ) {
			$unsub     = add_query_arg( array( 'tdn_sub' => 'unsub', 't' => $token ), home_url( '/' ) );
			$headers[] = 'List-Unsubscribe: <' . $unsub . '>';
			$headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
		}
		return wp_mail( $to, $subject, $html, $headers );
	}

	/** Counts for the admin screen. */
	public static function counts() {
		global $wpdb;
		$t = self::table();
		$r = $wpdb->get_results( "SELECT status, freq, COUNT(*) n FROM $t GROUP BY status, freq" );
		$o = array( 'active_daily' => 0, 'active_instant' => 0, 'pending' => 0, 'unsub' => 0 );
		foreach ( (array) $r as $x ) {
			if ( 'active' === $x->status ) {
				$o[ 'active_' . $x->freq ] = (int) $x->n;
			} elseif ( isset( $o[ $x->status ] ) ) {
				$o[ $x->status ] += (int) $x->n;
			}
		}
		return $o;
	}
}

TDN_Subs::init();

/** Theme helper. */
function tdn_subscribe_form( $context = 'band' ) {
	echo TDN_Subs::form( $context ); // phpcs:ignore -- escaped inside
}

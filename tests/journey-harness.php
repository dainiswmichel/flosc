<?php
/**
 * Does FLOSC do FLOSC? A WP-CLI harness that answers the mechanical half.
 *
 * Checking a release by hand means VGM × FLOSC × four packs × at least two
 * personalities — on the order of forty to sixty driven conversations, each
 * needing the right user state on the right flow at the right phase. Most of
 * what that would establish is mechanical: whether the declared number of free
 * items is what a visitor can reach, whether the offer surfaces at the right
 * phase and not before, whether each turn's log row names the personality that
 * produced it.
 *
 * None of that needs a human. What needs a human is whether it sells
 * gracefully, whether the character is alive, and whether you would buy the PDF
 * from this conversation. This harness exists so the person doing the judging
 * never spends an afternoon discovering that recipe gating was off by one.
 *
 * It reads what each pack declares about itself — free_content_item_count and
 * its pool category from flow_ivr.md, the conversion and attached personality
 * from pack.json — so the expectations are the pack's, not this file's.
 *
 * It drives the PUBLIC route. An admin-only run is how a candidate passed a
 * ten-question test with a dead visitor path.
 *
 * NOT SHIPPED. tests/ is excluded from the build. Load it explicitly:
 *
 *   wp --require=wp-content/plugins/flosc/tests/journey-harness.php \
 *      flosc-journey --pack=vegan-latvian-kitchen
 *
 *   --pack=<slug>          one pack, or every installed pack when omitted
 *   --personality=<id>     attach this personality first (use the one you built)
 *   --transcript=<path>    write the full conversations here
 *   --keep-users           leave the throwaway guest and member accounts behind
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * One run of the journey across a pack's user states.
 */
class FLOSC_Journey_Harness {

	/** @var array Rows for the results grid. */
	private $rows = array();

	/** @var array Full conversations, for the transcript file. */
	private $transcript = array();

	/** @var int Failures that are the harness's business to report. */
	private $failures = 0;

	/**
	 * Run the journey.
	 *
	 * @param array $args       Positional (unused).
	 * @param array $assoc_args --pack, --personality, --transcript, --keep-users.
	 */
	public function __invoke( $args, $assoc_args ) {
		$only        = isset( $assoc_args['pack'] ) ? sanitize_key( $assoc_args['pack'] ) : '';
		$personality = isset( $assoc_args['personality'] ) ? sanitize_key( $assoc_args['personality'] ) : '';
		$transcript  = isset( $assoc_args['transcript'] ) ? (string) $assoc_args['transcript'] : '';
		$keep_users  = isset( $assoc_args['keep-users'] );

		$packs = $this->packs( $only );
		if ( empty( $packs ) ) {
			WP_CLI::error( $only !== '' ? "No starter pack named '{$only}'." : 'No starter packs found.' );
		}

		foreach ( $packs as $slug => $pack ) {
			WP_CLI::log( '' );
			WP_CLI::log( WP_CLI::colorize( "%B{$slug}%n — " . ( $pack['name'] ?? $slug ) ) );

			$flow_id = $this->flow_id_for( $pack );
			if ( $flow_id === '' ) {
				$this->row( $slug, '—', '—', 'not installed', false, 'Install this pack first.' );
				continue;
			}

			if ( $personality !== '' ) {
				$this->attach( $flow_id, $personality );
			}

			$declared = $this->declared_model( $slug, $pack );
			WP_CLI::log( sprintf(
				'  flow %s · personality %s · %d free item(s) from %s',
				$flow_id,
				$this->attached( $flow_id ),
				$declared['free_count'],
				$declared['pool'] !== '' ? $declared['pool'] : '(no pool declared)'
			) );

			foreach ( array( 'visitor', 'guest', 'member' ) as $state ) {
				$this->run_state( $slug, $pack, $flow_id, $declared, $state, $keep_users );
			}
		}

		$this->render();

		if ( $transcript !== '' ) {
			file_put_contents( $transcript, $this->render_transcript() );
			WP_CLI::log( '' );
			WP_CLI::log( 'Transcript: ' . $transcript );
		}

		WP_CLI::log( '' );
		if ( $this->failures > 0 ) {
			WP_CLI::error( sprintf( '%d check(s) failed. The grid above says which.', $this->failures ) );
		}
		WP_CLI::success( 'Every mechanical check passed. The reading is yours.' );
	}

	/**
	 * Packs on disk, each with what it declares about itself.
	 */
	private function packs( $only ) {
		$found = array();
		foreach ( (array) glob( FLOSC_PLUGIN_DIR . 'starter-packs/*/pack.json' ) as $file ) {
			$slug = basename( dirname( $file ) );
			if ( $only !== '' && $slug !== $only ) {
				continue;
			}
			$pack = json_decode( (string) file_get_contents( $file ), true );
			if ( is_array( $pack ) ) {
				$found[ $slug ] = $pack;
			}
		}
		return $found;
	}

	/**
	 * The pack's own content model, read from the pack — never assumed here.
	 */
	private function declared_model( $slug, $pack ) {
		$model = array(
			'free_count' => 0,
			'pool'       => '',
			'conversion' => (string) ( $pack['conversion'] ?? '' ),
		);

		$flow_file = FLOSC_PLUGIN_DIR . 'starter-packs/' . $slug . '/' . ( $pack['flow']['file'] ?? 'flow_ivr.md' );
		if ( ! is_file( $flow_file ) ) {
			return $model;
		}

		$src = (string) file_get_contents( $flow_file );
		if ( preg_match( '/^free_content_item_count:\s*(\d+)/m', $src, $m ) ) {
			$model['free_count'] = (int) $m[1];
		}
		if ( preg_match( '/^free_content_item_pool_category:\s*(\S+)/m', $src, $m ) ) {
			$model['pool'] = trim( $m[1] );
		}
		return $model;
	}

	/**
	 * The installed flow id for a pack, or '' when it was never installed.
	 */
	private function flow_id_for( $pack ) {
		$install_as = (string) ( $pack['flow']['install_as'] ?? '' );
		if ( $install_as === '' ) {
			return '';
		}
		$flow_id = sanitize_key( pathinfo( $install_as, PATHINFO_FILENAME ) );
		$settings = get_option( 'flosc_flow_' . $flow_id, null );
		return is_array( $settings ) ? $flow_id : '';
	}

	private function attach( $flow_id, $personality ) {
		$key      = 'flosc_flow_' . $flow_id;
		$settings = get_option( $key, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$settings['personality_library_id'] = $personality;
		update_option( $key, $settings );
	}

	private function attached( $flow_id ) {
		if ( ! function_exists( 'flosc_personality_library_resolve_field' ) ) {
			return '(unknown)';
		}
		$name = trim( (string) flosc_personality_library_resolve_field( 'ai_personality_name', '', $flow_id ) );
		return $name !== '' ? $name : '(none attached)';
	}

	/**
	 * One user state, driven through the public chat route.
	 */
	private function run_state( $slug, $pack, $flow_id, $declared, $state, $keep_users ) {
		$user_id = 0;
		if ( $state !== 'visitor' ) {
			$user_id = $this->throwaway_user( $slug, $state );
			wp_set_current_user( $user_id );
		} else {
			wp_set_current_user( 0 );
		}

		// The questions a real person asks, in the order they ask them: who are
		// you, what have you got, can I have it. Anything shorter does not reach
		// the offer; anything longer is a conversation, not a check.
		$script = array(
			'hello, who am i talking to?',
			'what do you have here?',
			'can i see one of those?',
			'how do i get the rest?',
		);

		$turns = array();
		foreach ( $script as $line ) {
			$turns[] = $this->turn( $flow_id, $line, $state );
		}

		$this->transcript[] = array(
			'pack'  => $slug,
			'state' => $state,
			'turns' => $turns,
		);

		// Every turn answered by the model rather than by scripted copy. A
		// fallback here is the failure that reads to a visitor as a chatbot with
		// nothing to say.
		$ai_turns = 0;
		$named    = '';
		$hashes   = array();
		foreach ( $turns as $t ) {
			if ( ( $t['response_source'] ?? '' ) === 'ai' || ( $t['response_source'] ?? '' ) === 'rag' ) {
				$ai_turns++;
			}
			if ( $named === '' && ( $t['personality_name'] ?? '' ) !== '' ) {
				$named = $t['personality_name'];
			}
			if ( ( $t['profile_hash'] ?? '' ) !== '' ) {
				$hashes[ $t['profile_hash'] ] = true;
			}
		}

		$this->row( $slug, $state, $named !== '' ? $named : '—',
			sprintf( '%d/%d generated', $ai_turns, count( $turns ) ),
			$ai_turns === count( $turns ),
			$ai_turns === count( $turns ) ? '' : 'A turn fell through to scripted copy.' );

		// One personality across the run. Two hashes means the character
		// changed mid-conversation without anyone asking it to.
		$this->row( $slug, $state, $named !== '' ? $named : '—',
			sprintf( '%d profile hash', count( $hashes ) ),
			count( $hashes ) <= 1,
			count( $hashes ) > 1 ? 'The compiled profile changed mid-conversation.' : '' );

		// What the pack says a visitor may reach.
		if ( $state === 'visitor' && $declared['free_count'] > 0 && $declared['pool'] !== '' ) {
			$reachable = $this->free_items( $flow_id, $declared['pool'] );
			$this->row( $slug, $state, $named !== '' ? $named : '—',
				sprintf( '%d of %d free items', $reachable, $declared['free_count'] ),
				$reachable === $declared['free_count'],
				$reachable === $declared['free_count'] ? '' : 'The pack declares ' . $declared['free_count'] . '.' );
		}

		// The offer has to arrive, and the last turn is where it is asked for.
		if ( $declared['conversion'] !== '' ) {
			$last  = end( $turns );
			$reply = strtolower( (string) ( $last['reply'] ?? '' ) );
			$hit   = false;
			foreach ( array( 'member', 'buy', 'purchase', 'access', 'join', 'sign up', 'register', '$' ) as $word ) {
				if ( strpos( $reply, $word ) !== false ) {
					$hit = true;
					break;
				}
			}
			$this->row( $slug, $state, $named !== '' ? $named : '—',
				'names a next step', $hit,
				$hit ? '' : 'The closing turn did not point anywhere.' );
		}

		wp_set_current_user( 0 );
		if ( $user_id > 0 && ! $keep_users ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user_id );
		}
	}

	/**
	 * One turn through /flosc/v1/chat, then the row it wrote.
	 */
	private function turn( $flow_id, $message, $state ) {
		$request = new WP_REST_Request( 'POST', '/flosc/v1/chat' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( array(
			'message'    => $message,
			'flow_id'    => $flow_id,
			'journey_id' => 'harness-' . $state . '-' . substr( md5( $flow_id ), 0, 12 ),
			'context'    => array( 'browsing_surface' => 'full_page' ),
		) ) );

		$started  = microtime( true );
		$response = rest_do_request( $request );
		$data     = $response->get_data();
		$elapsed  = (int) round( ( microtime( true ) - $started ) * 1000 );

		$reply = '';
		if ( is_array( $data ) ) {
			$reply = (string) ( $data['message'] ?? $data['response'] ?? '' );
		}

		$row = $this->last_log_row( $flow_id );

		return array(
			'message'          => $message,
			'reply'            => $reply,
			'ms'               => $elapsed,
			'status'           => $response->get_status(),
			'response_source'  => (string) ( $row['response_source'] ?? '' ),
			'personality_name' => (string) ( $row['personality_name'] ?? '' ),
			'profile_hash'     => (string) ( $row['profile_hash'] ?? '' ),
			'surface'          => (string) ( $row['surface'] ?? '' ),
			'input_tokens'     => (int) ( $row['billing_input_tokens'] ?? 0 ),
		);
	}

	/**
	 * The row that turn just wrote. The log is the record; the response is not.
	 */
	private function last_log_row( $flow_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'flosc_chat_logs';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- harness only, never shipped.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE flow_id = %s ORDER BY id DESC LIMIT 1", $flow_id ), ARRAY_A );
		return is_array( $row ) ? $row : array();
	}

	/**
	 * How many items of the pack's pool a visitor can actually reach.
	 */
	private function free_items( $flow_id, $pool ) {
		$terms = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'numberposts'    => 100,
			'category_name'  => $pool,
			'fields'         => 'ids',
		) );
		if ( empty( $terms ) ) {
			return 0;
		}
		$reachable = 0;
		foreach ( $terms as $post_id ) {
			$level = get_post_meta( $post_id, 'flosc_access_level', true );
			if ( $level === '' || $level === 'visitor' || $level === 'public' ) {
				$reachable++;
			}
		}
		return $reachable;
	}

	private function throwaway_user( $slug, $state ) {
		$login = 'flosc_harness_' . substr( md5( $slug . $state ), 0, 10 );
		$user  = get_user_by( 'login', $login );
		if ( $user instanceof WP_User ) {
			return (int) $user->ID;
		}
		$user_id = wp_insert_user( array(
			'user_login' => $login,
			'user_pass'  => wp_generate_password( 24, true, true ),
			'user_email' => $login . '@example.invalid',
			'role'       => 'subscriber',
		) );
		if ( is_wp_error( $user_id ) ) {
			WP_CLI::warning( 'Could not create a ' . $state . ' account: ' . $user_id->get_error_message() );
			return 0;
		}
		if ( $state === 'member' ) {
			update_user_meta( $user_id, 'flosc_member_level', 'member' );
		}
		return (int) $user_id;
	}

	private function row( $pack, $state, $who, $check, $pass, $note ) {
		if ( ! $pass ) {
			$this->failures++;
		}
		$this->rows[] = compact( 'pack', 'state', 'who', 'check', 'pass', 'note' );
	}

	private function render() {
		WP_CLI::log( '' );
		WP_CLI::log( str_repeat( '─', 96 ) );
		printf( "%-2s %-26s %-8s %-16s %-24s %s\n", '', 'pack', 'state', 'personality', 'check', 'note' );
		WP_CLI::log( str_repeat( '─', 96 ) );
		foreach ( $this->rows as $r ) {
			printf( "%-2s %-26s %-8s %-16s %-24s %s\n",
				$r['pass'] ? '·' : '!',
				substr( $r['pack'], 0, 26 ),
				$r['state'],
				substr( $r['who'], 0, 16 ),
				$r['check'],
				$r['note']
			);
		}
		WP_CLI::log( str_repeat( '─', 96 ) );
	}

	private function render_transcript() {
		$out = array( 'FLOSC journey transcript', str_repeat( '=', 60 ), '' );
		foreach ( $this->transcript as $run ) {
			$out[] = strtoupper( $run['pack'] ) . ' · ' . $run['state'];
			$out[] = str_repeat( '-', 60 );
			foreach ( $run['turns'] as $t ) {
				$out[] = '> ' . $t['message'];
				$out[] = '  ' . $t['reply'];
				$out[] = sprintf( '  [%s · %s · %dms · %d in]',
					$t['personality_name'] !== '' ? $t['personality_name'] : 'no personality recorded',
					$t['response_source'] !== '' ? $t['response_source'] : 'no source recorded',
					$t['ms'],
					$t['input_tokens']
				);
				$out[] = '';
			}
			$out[] = '';
		}
		return implode( "\n", $out ) . "\n";
	}
}

WP_CLI::add_command( 'flosc-journey', 'FLOSC_Journey_Harness', array(
	'shortdesc' => 'Drive each starter pack through visitor, guest and member on the public chat route.',
) );

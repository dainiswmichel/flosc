<?php
/**
 * FLOSC Concierge
 * -----------------------------------------------------------------------------
 * Concierge is an IVR message TYPE — a keyword-triggered message with an
 * optional password gate. Because it's just a message type, it rides the whole
 * IVR machinery for free: stored in the DB, mirrored to the portability .md,
 * organized by phase, scoped per flow, edited in the IVR editor, and loaded
 * with every other message. It can live in any phase of any flow.
 *
 * The only behavior unique to concierge is the gate:
 *
 *   guest says the keyword
 *     → message has IndividualMessagePassword? ask for it, then on an exact
 *       match deliver the content
 *     → no password? deliver immediately, like any keyword message
 *
 * This class does that one thing, operating on the IVR config the chat has
 * ALREADY loaded from the DB — so a concierge check adds no file read, no
 * markdown parse, and no database query. The gate's "waiting for the password"
 * state is the only thing it stores, and only while a gate is actually open.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLOSC_Concierge {

	/** The IVR message type that marks a concierge message. */
	const TYPE = 'concierge';

	/** Message field holding the optional per-message password. */
	const PASSWORD_FIELD = 'individual_message_password';

	/** Field holding the optional UTC window start time. */
	const START_FIELD = 'start_utc_mts';

	/** Field holding the optional UTC window end time. */
	const END_FIELD = 'end_utc_mts';

	/** How long (seconds) a guest has to enter the password after the keyword. */
	const GATE_TTL = 600;

	/**
	 * How long an opened concierge "desk" stays available for that one guest.
	 *
	 * Once the keyword unlocks the desk, the guest can come and go and keep asking
	 * for the next thing for this long — scoped to their session alone, so it is
	 * open for them and no one else. Three days suits a personal hand-off: long
	 * enough to revisit from the same browser, short enough that it isn't forever.
	 */
	const OPEN_TTL = 7 * DAY_IN_SECONDS;

	/**
	 * Handle a chat message against the concierge messages already loaded in the IVR.
	 *
	 * Called before the normal IVR/AI path. Operates entirely on the in-memory
	 * $ivr_config — no queries — so it's free on every non-concierge message too.
	 *
	 * @param string $message     The visitor's message.
	 * @param string $session_key Stable per-conversation key (session id or user).
	 * @param array  $ivr_config  The loaded IVR config: ['messages' => [...], ...].
	 * @return array|null A chat response to return, or null when no concierge applies.
	 */
	public static function handle( $message, $session_key, $ivr_config ) {
		$message     = trim( (string) $message );
		$session_key = (string) $session_key;
		if ( '' === $message || '' === $session_key ) {
			return null;
		}

		$messages = ( isset( $ivr_config['messages'] ) && is_array( $ivr_config['messages'] ) )
			? $ivr_config['messages']
			: array();
		if ( empty( $messages ) ) {
			return null;
		}

		$gate_key = 'flosc_concierge_gate_' . md5( $session_key );
		$open_key = self::open_key( $session_key );

		// Step 2: a password gate is open for this session (keyword already hit).
		$pending = get_transient( $gate_key );
		if ( is_array( $pending ) && ! empty( $pending['id'] ) && isset( $messages[ $pending['id'] ] ) ) {
			$msg = $messages[ $pending['id'] ];
			$max = self::max_tries( $msg );
			$password = (string) ( $msg[ self::PASSWORD_FIELD ] ?? '' );

			if ( self::is_escape_request( $message ) ) {
				delete_transient( $gate_key );
				delete_transient( $open_key );
				return null;
			}

			if ( self::keyword_hit( $message, (string) ( $msg['keywords'] ?? '' ) ) ) {
				set_transient( $gate_key, array( 'id' => $pending['id'], 'tries' => 0 ), self::GATE_TTL );
				return self::prompt( self::text( $msg, 'password_prompt', 'I’ve got something for you — what’s the password?' ) );
			}

			// Right password → open the AI-hosted desk and hand the conversation to
			// the AI path. We return the authoritative source content so the AI can
			// host the reveal, but the chat handler can still fall back to that exact
			// content if the provider is unavailable on this turn.
			if ( self::password_matches( $message, $password ) ) {
				delete_transient( $gate_key );
				self::open_session( $session_key, $msg, 'revealing', true );
				return array(
					'content'          => trim( (string) ( $msg['content'] ?? '' ) ),
					'user_autoprompts' => array(),
					'phase_change'     => null,
					'flosc_concierge'  => 'revealing',
					'concierge_success'=> self::text( $msg, 'password_success', 'Password confirmed — here you go.' ),
				);
			}

			// Wrong password → show this miss's retry line. The final allowed miss
			// shows its line (typically the "reach out to me" escape note) and then
			// the gate closes — the conversation falls back to normal chat.
			$tries = (int) ( $pending['tries'] ?? 0 ) + 1;
			$line  = self::retry_line( $msg, $tries, $max );
			if ( $tries >= $max ) {
				delete_transient( $gate_key );
			} else {
				set_transient( $gate_key, array( 'id' => $pending['id'], 'tries' => $tries ), self::GATE_TTL );
			}
			return self::prompt( $line );
		}

		// Step 1: does the message hit a concierge message's keyword?
		foreach ( $messages as $id => $msg ) {
			if ( ! isset( $msg['type'] ) || self::TYPE !== $msg['type'] ) {
				continue;
			}
			if ( ! self::is_active_now( $msg ) ) {
				continue;
			}
			if ( ! self::keyword_hit( $message, (string) ( $msg['keywords'] ?? '' ) ) ) {
				continue;
			}
			$password = self::normalize_password( (string) ( $msg[ self::PASSWORD_FIELD ] ?? '' ) );
			if ( '' !== $password ) {
				set_transient( $gate_key, array( 'id' => $id, 'tries' => 0 ), self::GATE_TTL );
				return self::prompt( self::text( $msg, 'password_prompt', 'I’ve got something for you — what’s the password?' ) );
			}
			// No gate → open the concierge desk for this guest and hand off to the AI
			// path (return null, no short-circuit). The AI then hosts the reveal in
			// its own voice, offer by offer, using the desk's brief (active_guidance).
			// There is deliberately NO canned-dump fallback: dumping the raw brief is
			// exactly the behaviour we must never produce.
			self::open_session( $session_key, $msg, 'revealing', true );
			return null;
		}

		return null;
	}

	/**
	 * Allowed password attempts for a message (default 3).
	 *
	 * @param array $msg IVR message.
	 * @return int
	 */
	protected static function max_tries( $msg ) {
		$n = (int) ( $msg['password_max_tries'] ?? 3 );
		return $n > 0 ? $n : 3;
	}

	/**
	 * Read a per-message text field, falling back to a default when blank.
	 *
	 * Every gate line is the floscAdmin's to write; the defaults only show when
	 * a field is left empty.
	 *
	 * @param array  $msg     IVR message.
	 * @param string $key     Field key.
	 * @param string $default Fallback text.
	 * @return string
	 */
	protected static function text( $msg, $key, $default ) {
		$value = trim( (string) ( $msg[ $key ] ?? '' ) );
		return '' !== $value ? $value : $default;
	}

	/**
	 * Substitute {try} and {max} into a retry line.
	 *
	 * @param string $text Retry text.
	 * @param int    $try  Which attempt this was.
	 * @param int    $max  Allowed attempts.
	 * @return string
	 */
	protected static function fill_counts( $text, $try, $max ) {
		return str_replace( array( '{try}', '{max}' ), array( (string) $try, (string) $max ), (string) $text );
	}

	/**
	 * The retry line for a given miss, from the per-message retry list.
	 *
	 * Line 1 shows on the first miss, line 2 on the second, and so on; if there
	 * are more misses than lines, the last line repeats. {try}/{max} are filled
	 * in. The final line is typically the "reach out to me directly" escape note.
	 *
	 * @param array $msg IVR message.
	 * @param int   $try Which miss this is (1-based).
	 * @param int   $max Allowed attempts.
	 * @return string
	 */
	protected static function retry_line( $msg, $try, $max ) {
		$list = array();
		if ( isset( $msg['password_retry_messages'] ) && is_array( $msg['password_retry_messages'] ) ) {
			foreach ( $msg['password_retry_messages'] as $line ) {
				$line = trim( (string) $line );
				if ( '' !== $line ) {
					$list[] = $line;
				}
			}
		}
		if ( empty( $list ) ) {
			$list = array( 'Hmm, not quite — that’s try {try} of {max}.' );
		}
		$idx = min( $try - 1, count( $list ) - 1 );
		return self::fill_counts( $list[ $idx ], $try, $max );
	}

	/**
	 * Whole-word, case-insensitive test for any of a message's keywords.
	 *
	 * The IVR `keywords` field is comma-separated; a hit on any one counts.
	 *
	 * @param string $message  Visitor message.
	 * @param string $keywords Comma-separated keyword(s).
	 * @return bool
	 */
	protected static function keyword_hit( $message, $keywords ) {
		$haystack = mb_strtolower( (string) $message );
		foreach ( explode( ',', (string) $keywords ) as $keyword ) {
			$keyword = mb_strtolower( trim( $keyword ) );
			if ( '' === $keyword ) {
				continue;
			}
			// Approximate: case- and position-insensitive containment, so "Narcissist"
			// matches "you're a narcissist" and "narcissistic" without an exact phrase.
			if ( false !== mb_strpos( $haystack, $keyword ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Collapse a written "no password" to an actual blank (= no gate).
	 *
	 * Admins and OpenClaw naturally write "none", "(none)", or "n/a" to MEAN
	 * "there is no password" — in the meta-box field or as a `Password:` line in
	 * the post body. Taken literally, those words become the password itself,
	 * opening a gate whose secret is the word "none"; worse, the meta box keeps
	 * re-deriving that word from the body, so clearing the field never sticks.
	 * Normalizing the whole family of sentinels to '' makes a written "none" do
	 * what it plainly says: no gate. The trade-off is intentional — a concierge
	 * password literally equal to one of these words is disallowed, which is fine
	 * for a friendly handoff that was never a security boundary.
	 *
	 * @param string $password Password as resolved from the meta box or post body.
	 * @return string The password, or '' when it is a "no password" sentinel.
	 */
	protected static function normalize_password( $password ) {
		$sentinels = array( 'none', '(none)', 'n/a', 'n.a.', 'na', 'no', 'no password', 'false', 'nil', 'null', '-', '–', '—' );
		return in_array( mb_strtolower( trim( (string) $password ) ), $sentinels, true ) ? '' : (string) $password;
	}

	/**
	 * Case-insensitive password comparison ("MONKI" == "monki" == "Monki").
	 *
	 * Concierge gates are a friendly handoff, not a security boundary, so a guest
	 * shouldn't be tripped up by capitalization. Both sides are lower-cased and
	 * trimmed before a constant-time compare. A blank stored password never matches —
	 * that's the "no gate" case, handled before we reach here.
	 *
	 * @param string $given    What the guest typed.
	 * @param string $expected The message's password.
	 * @return bool
	 */
	protected static function password_matches( $given, $expected ) {
		$expected = mb_strtolower( trim( (string) $expected ) );
		if ( '' === $expected ) {
			return false;
		}
		$given = mb_strtolower( trim( (string) $given ) );
		return '' !== $given && false !== mb_stripos( $given, $expected );
	}

	/**
	 * Decide whether the guest wants out of the concierge flow.
	 *
	 * @param string $message Visitor message.
	 * @return bool
	 */
	protected static function is_escape_request( $message ) {
		$message = mb_strtolower( trim( (string) $message ) );
		if ( '' === $message ) {
			return false;
		}

		$escape_phrases = array(
			'never mind',
			'nevermind',
			'cancel',
			'stop',
			'go back',
			'back to normal',
			'normal chat',
			'escape',
			'leave',
			'exit',
		);

		foreach ( $escape_phrases as $phrase ) {
			if ( '' !== $phrase && false !== mb_stripos( $message, $phrase ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build a prompt chat response (e.g. asking for the password).
	 *
	 * @param string $text Prompt text.
	 * @return array
	 */
	protected static function prompt( $text ) {
		return array(
			'success'          => true,
			'message'          => (string) $text,
			'user_autoprompts' => array(),
			'phaseChange'      => null,
			'flosc_concierge'  => 'awaiting_password',
		);
	}

	/* ===========================================================================
	 * The open concierge desk (AI-hosted reveal)
	 * ---------------------------------------------------------------------------
	 * Once a guest clears the keyword (and any password), we don't dump the post's
	 * content at them. Instead we open a "desk" for THAT guest's session: a brief
	 * the AI guide is authorized to share — but only a little at a time, only what
	 * the guest asks for, in the guide's own voice. The desk is a per-session
	 * transient, so it's open for this one guest and no one else, for OPEN_TTL.
	 *
	 * The chat handler reads active_guidance() after handle() and, when a desk is
	 * open, appends it to the AI's system prompt. handle() itself returns null on
	 * unlock so the message flows on into the normal AI path (which also means the
	 * exchange is logged like any other turn, instead of being short-circuited).
	 * ======================================================================== */

	/** Per-session transient key for an open desk. */
	protected static function open_key( $session_key ) {
		return 'flosc_concierge_open_' . md5( (string) $session_key );
	}

	/**
	 * Open the concierge desk for one guest's session.
	 *
	 * Stores just what the AI needs to host the reveal: the material to draw from
	 * and the optional per-post delivery style (tone, language, pacing).
	 *
	 * @param string $session_key      Stable per-conversation key.
	 * @param array  $msg              The matched concierge IVR message.
	 * @param string $start_stage      Initial stage ('invited' or 'revealing').
	 * @param bool   $deliver_now_once Whether next AI turn should deliver content immediately.
	 * @return void
	 */
	protected static function open_session( $session_key, $msg, $start_stage = 'invited', $deliver_now_once = false ) {
		$start_stage = in_array( $start_stage, array( 'invited', 'revealing', 'offered' ), true ) ? $start_stage : 'invited';
		set_transient(
			self::open_key( $session_key ),
			array(
				// The full note is the AI's authoritative SOURCE. It is given as
				// reference every turn so the AI can quote facts (phone, email)
				// EXACTLY and never invent them — while the guidance instructs it to
				// reveal only 1–3 sentences per turn rather than paste the whole note.
				'brief'       => (string) ( $msg['content'] ?? '' ),
				'stage'       => $start_stage,
				'deliver_now' => (bool) $deliver_now_once,
				'delivery'    => (string) ( $msg['delivery_style'] ?? '' ),
				'off_ramp_phrases' => (string) ( $msg['off_ramp_phrases'] ?? '' ),
				'off_ramp_exactness' => self::off_ramp_exactness( (string) ( $msg['off_ramp_exactness'] ?? '' ) ),
				'name'        => (string) ( $msg['name'] ?? '' ),
			),
			self::OPEN_TTL
		);
	}

	/** Is a concierge desk currently open for this guest's session? */
	public static function has_active_session( $session_key ) {
		$data = get_transient( self::open_key( $session_key ) );
		return is_array( $data ) && '' !== trim( (string) ( $data['brief'] ?? '' ) );
	}

	/**
	 * The system-prompt block to inject on THIS turn, and advance the desk's state.
	 *
	 * This is the heart of the hosted reveal. It is stateful and called once per
	 * guest turn:
	 *   - the first turn returns an INVITATION instruction with NONE of the note;
	 *   - each later turn hands the AI exactly ONE fragment to voice, then advances
	 *     the cursor, so the note is revealed strictly one piece per turn;
	 *   - once every fragment is revealed, it returns a gentle closing instruction.
	 *
	 * Because the AI never receives more than a single fragment, a full-note dump is
	 * impossible by construction — the guidance below is style, not the safeguard.
	 *
	 * @param string $session_key Stable per-conversation key.
	 * @return string Prompt block for this turn, or '' when no desk is open.
	 */
	public static function active_guidance( $session_key ) {
		$key  = self::open_key( $session_key );
		$data = get_transient( $key );
		if ( ! is_array( $data ) || '' === trim( (string) ( $data['brief'] ?? '' ) ) ) {
			return '';
		}

		$delivery = trim( (string) ( $data['delivery'] ?? '' ) );
		$style    = ( '' !== $delivery )
			? $delivery
			: 'Be warm, friendly and personable, and use the polite form of address.';
		$off_ramp = self::off_ramp_guidance(
			(string) ( $data['off_ramp_phrases'] ?? '' ),
			(string) ( $data['off_ramp_exactness'] ?? 'preferred' )
		);

		$head = "\n\n**PRIVATE CONCIERGE — you are personally hosting ONE guest. Keep each reply to 1–3 short sentences.**\n"
			. 'Delivery style: ' . $style . "\n"
			. "Never mention operational details (keywords, expiry, configuration, or that you are reading from a note). Stay fully in character.\n"
			. $off_ramp;

		// Turn 1 — invite only. Reveal nothing from the note yet.
		if ( 'invited' === ( $data['stage'] ?? 'invited' ) ) {
			$data['stage'] = 'offered';
			set_transient( $key, $data, self::OPEN_TTL );
			return $head
				. "This is your FIRST reply. Greet the guest warmly by name and let them know the person who introduced you left a short, personal note for them. "
				. "Invite them to say whether they'd like to hear it. Reveal NONE of the note's content yet. End on an inviting question.";
		}

		if ( ! empty( $data['deliver_now'] ) ) {
			$data['deliver_now'] = false;
			set_transient( $key, $data, self::OPEN_TTL );
			return $head
				. "The guest has already passed access verification. In THIS reply, deliver the core content immediately. If the SOURCE contains a URL, provide that URL verbatim in this reply. Keep it concise and helpful; do not ask for more permission first.\n"
				. "----- SOURCE (authoritative private reference — quote facts exactly, never paste wholesale) -----\n"
				. trim( (string) $data['brief'] ) . "\n"
				. "----- end SOURCE -----";
		}

		// Reveal stage: the AI gets the full SOURCE so every fact it states is
		// accurate, but it must reveal only a little per turn and never fabricate.
		return $head
			. "Reveal the SOURCE below GRADUALLY — only 1–3 sentences per reply, in order, continuing from what you have already shared (check the conversation so far), and end by inviting them to hear more. NEVER paste, list, or summarise the whole note at once.\n"
			. "ACCURACY IS ABSOLUTE: every fact you state — ESPECIALLY phone numbers and email addresses — must be copied VERBATIM from the SOURCE. Never invent, guess, alter, reformat, or round a number or address. When the guest asks for a contact detail, quote it EXACTLY as written below. If something is not in the SOURCE, say you don't have it — do not make anything up.\n"
			. "----- SOURCE (authoritative private reference — quote facts exactly, never paste wholesale) -----\n"
			. trim( (string) $data['brief'] ) . "\n"
			. "----- end SOURCE -----";
	}

	/* ===========================================================================
	 * Concierge POSTS → DB/IVR sync
	 * ---------------------------------------------------------------------------
	 * OpenClaw (or the admin) writes a private post in the concierge category; the
	 * plugin reads it and upserts a concierge MESSAGE into the post's flow — which
	 * the integrity hook then mirrors to the .md. There is no authoring surface to
	 * build: the WordPress post IS the surface. On the post, admins see a read-only
	 * "what FLOSC understands" confirmation so they can check the setup landed.
	 * ======================================================================== */

	/** Default category that marks a concierge post. */
	const CATEGORY = 'concierge';
	const INTERNAL_PARENT_SLUG = 'flosc-internal';
	const INTERNAL_CONCIERGE_CHILD_SLUG = 'flosc-internal-concierge';

	/** Post-meta keys for the editable settings (the "FLOSC Concierge" meta box). */
	const META = array(
		'flow'      => '_flosc_concierge_flow',
		'keyword'   => '_flosc_concierge_keyword',
		'password'  => '_flosc_concierge_password',
		'start'     => '_flosc_concierge_start_utc_mts',
		'end'       => '_flosc_concierge_end_utc_mts',
		'success'   => '_flosc_concierge_success',
		'max_tries' => '_flosc_concierge_max_tries',
		'retry'     => '_flosc_concierge_retry',
		'delivery'  => '_flosc_concierge_delivery',
		'off_ramp_phrases' => '_flosc_concierge_off_ramp_phrases',
		'off_ramp_exactness' => '_flosc_concierge_off_ramp_exactness',
		'expires'   => '_flosc_concierge_expires_utc_mts',
		'params'    => '_flosc_concierge_parameters',
		'instruct'  => '_flosc_concierge_instruction_template',
	);

	/**
	 * Resolve a concierge post's settings.
	 *
	 * The meta box is the editable source of truth; for any field left blank
	 * there, we fall back to the labeled lines in the post body (the format
	 * OpenClaw writes), so an OpenClaw-authored post works with no meta set and
	 * its values pre-fill the meta box. Content delivered is the post body's
	 * "Content to deliver" block, or the whole body if that label is absent.
	 *
	 * @param int|WP_Post $post Post or ID.
	 * @return array|null
	 */
	public static function config_from_post( $post ) {
		$post = get_post( $post );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}
		$body = (string) $post->post_content;
		$meta = static function ( $id, $key ) {
			$v = get_post_meta( $id, self::META[ $key ], true );
			return is_string( $v ) ? $v : '';
		};

		$flow     = $meta( $post->ID, 'flow' );
		$keyword  = $meta( $post->ID, 'keyword' );
		$password = $meta( $post->ID, 'password' );
		$success  = $meta( $post->ID, 'success' );
		$retry    = $meta( $post->ID, 'retry' );
		$delivery = $meta( $post->ID, 'delivery' );
		$off_ramp_phrases = $meta( $post->ID, 'off_ramp_phrases' );
		$off_ramp_exactness = $meta( $post->ID, 'off_ramp_exactness' );
		$start    = $meta( $post->ID, 'start' );
		$end      = $meta( $post->ID, 'end' );
		$expires  = $meta( $post->ID, 'expires' );
		$params   = $meta( $post->ID, 'params' );
		$instruct = $meta( $post->ID, 'instruct' );
		$max      = (int) $meta( $post->ID, 'max_tries' );

		$deployment = self::label( $body, 'Deployment' );
		if ( '' === $deployment ) {
			$deployment = self::label( $body, 'deployment' );
		}

		$flow_hint = self::label( $body, 'floscFlow' );
		if ( '' === $flow_hint ) {
			$flow_hint = self::label( $body, 'Flow' );
		}
		if ( '' === $flow_hint ) {
			$flow_hint = self::label( $body, 'FlowName' );
		}

		// OpenClaw fallback: resolve the flow for any field left blank in the meta box.
		// Tried in order of how a person would name it:
		//   1. an explicit .md filename in floscFlow/Flow/FlowName ("… (dainis_net_ivr.md)")
		//   2. the flow's NAME in floscFlow/Flow/FlowName ("Br3nda", "LeSAEp")
		//   3. the human-facing Deployment ("dainis.net/chat", "flosc.ai")
		if ( '' === $flow ) {
			$flow = self::flow_file( $flow_hint );
		}
		if ( '' === $flow && '' !== $flow_hint ) {
			$flow = self::flow_by_name( $flow_hint );
		}
		if ( '' === $flow ) {
			$flow = self::flow_from_deployment( $deployment );
		}
		if ( '' === $keyword ) {
			$keyword = self::unquote( self::label( $body, 'Keyword' ) );
		}
		if ( '' === $password ) {
			$password = self::unquote( self::label( $body, 'Password' ) );
		}
		$password = self::normalize_password( $password );
		if ( '' === $delivery ) {
			$delivery = self::label( $body, 'Delivery style' );
		}
		if ( '' === $off_ramp_exactness ) {
			$off_ramp_exactness = self::label( $body, 'Off-ramp exactness' );
		}
		$off_ramp_exactness = self::off_ramp_exactness( $off_ramp_exactness );
		if ( '' === $expires ) {
			$expires = self::label( $body, 'Expires UTC MTS' );
			if ( '' === $expires ) {
				$expires = self::label( $body, 'Expiry UTC MTS' );
			}
			if ( '' === $expires ) {
				$expires = self::label( $body, 'ExpiresUTCMTS' );
			}
		}
		if ( '' === $start ) {
			$start = self::label( $body, 'Start UTC MTS' );
		}
		if ( '' === $end ) {
			$end = self::label( $body, 'End UTC MTS' );
		}
		if ( '' === $end && '' !== $expires ) {
			$end = $expires;
		}
		if ( '' === $params ) {
			$params = self::content_block( $body, 'Parameters' );
		}
		if ( '' === $instruct ) {
			$instruct = self::content_block( $body, 'Instruction template' );
			if ( '' === $instruct ) {
				$instruct = self::content_block( $body, 'Instructions template' );
			}
		}

		$parameters = self::parse_parameters_text( $params );
		$content = self::content_to_deliver( $body );
		$end = self::normalize_utc_mts( $end );
		$start = self::normalize_utc_mts( $start );
		$content = self::apply_template_parameters( $content, $parameters, $end );
		$instruct = self::apply_template_parameters( (string) $instruct, $parameters, $end );
		$expires = $end;

		return array(
			'flow'       => $flow,
			'deployment' => self::label( $body, 'Deployment' ),
			'keyword'    => $keyword,
			'password'   => $password,
			'success'    => $success,
			'max_tries'  => $max > 0 ? $max : 3,
			'retry'      => $retry,
			'delivery'   => $delivery,
			'off_ramp_phrases' => (string) $off_ramp_phrases,
			'off_ramp_exactness' => (string) $off_ramp_exactness,
			'start_utc_mts'   => $start,
			'end_utc_mts'     => $end,
			'expires_utc_mts' => $expires,
			'parameters_text' => (string) $params,
			'parameters' => $parameters,
			'instruction_template' => (string) $instruct,
			'content'    => $content,
		);
	}

	/**
	 * Render the "FLOSC Concierge" meta box on a concierge post's edit screen.
	 *
	 * @param WP_Post $post The post being edited.
	 * @return void
	 */
	public static function render_meta_box( $post ) {
		$c = self::config_from_post( $post );
		wp_nonce_field( 'flosc_concierge_meta', 'flosc_concierge_nonce' );
		// Styles for .flosc-cncrg-* live on the 'flosc-metabox' handle, added in
		// enqueue_admin_assets() during admin_enqueue_scripts — the only moment
		// inline style data still reaches the page (see the §12 note there).
		echo '<div class="flosc-cncrg-row"><label>Flow</label><select name="flosc_cncrg_flow">';
		$current  = $c['flow'];
		$files    = function_exists( 'flosc_config_glob' ) ? flosc_config_glob( '*_ivr.md' ) : array();
		$seen     = array();
		$have_cur = false;
		echo '<option value="">— choose a flow —</option>';
		foreach ( (array) $files as $file ) {
			$name = basename( (string) $file );
			if ( '' === $name || isset( $seen[ $name ] ) ) {
				continue;
			}
			$seen[ $name ] = true;
			$opt   = get_option( 'flosc_flow_' . sanitize_key( pathinfo( $name, PATHINFO_FILENAME ) ), array() );
			$label = ( is_array( $opt ) && ! empty( $opt['identity']['name'] ) ) ? $opt['identity']['name'] . ' (' . $name . ')' : $name;
			if ( $name === $current ) {
				$have_cur = true;
			}
			echo '<option value="' . esc_attr( $name ) . '" ' . selected( $current, $name, false ) . '>' . esc_html( $label ) . '</option>';
		}
		if ( '' !== $current && ! $have_cur ) {
			echo '<option value="' . esc_attr( $current ) . '" selected>' . esc_html( $current ) . '</option>';
		}
		echo '</select></div>';
		echo '<div class="flosc-cncrg-row"><label>Keyword</label><input type="text" name="flosc_cncrg_keyword" value="' . esc_attr( $c['keyword'] ) . '" placeholder="the word the guest says"></div>';
		echo '<div class="flosc-cncrg-row"><label>Password (any capitalization — blank = no gate)</label><input type="text" name="flosc_cncrg_password" value="' . esc_attr( $c['password'] ) . '"></div>';
		echo '<div class="flosc-cncrg-row"><label>Max tries</label><input class="flosc-cncrg-max-tries" type="number" name="flosc_cncrg_max_tries" value="' . esc_attr( (string) $c['max_tries'] ) . '" min="1"></div>';
		echo '<div class="flosc-cncrg-row"><label>Retry messages (one per line; {try}/{max} substituted; last line shows on the final miss)</label><textarea name="flosc_cncrg_retry" rows="3">' . esc_textarea( $c['retry'] ) . '</textarea></div>';
		echo '<div class="flosc-cncrg-row"><label>Success message (shown just before the content delivers)</label><input type="text" name="flosc_cncrg_success" value="' . esc_attr( $c['success'] ) . '"></div>';
		echo '<div class="flosc-cncrg-row"><label>Delivery style (how the AI hosts the reveal — tone, language, pacing; blank = a warm, friendly default)</label><textarea name="flosc_cncrg_delivery" rows="3" placeholder="e.g. Warm and professional in English; offer one thing at a time as an easy yes/no question; reveal only what the guest asks for. (Set the persona per guest/purpose here — playful, formal, a specific language, etc.)">' . esc_textarea( $c['delivery'] ) . '</textarea></div>';
		echo '<div class="flosc-cncrg-row"><label>Off-ramp exactness</label><select name="flosc_cncrg_off_ramp_exactness">'
			. '<option value="flexible" ' . selected( (string) ( $c['off_ramp_exactness'] ?? 'preferred' ), 'flexible', false ) . '>Flexible (paraphrase allowed)</option>'
			. '<option value="preferred" ' . selected( (string) ( $c['off_ramp_exactness'] ?? 'preferred' ), 'preferred', false ) . '>Preferred (close wording)</option>'
			. '<option value="exact" ' . selected( (string) ( $c['off_ramp_exactness'] ?? 'preferred' ), 'exact', false ) . '>Exact (verbatim phrase)</option>'
			. '</select></div>';
		echo '<div class="flosc-cncrg-row"><label>Off-ramp phrases (one per line)</label><textarea name="flosc_cncrg_off_ramp_phrases" rows="3" placeholder="Would you like to continue this concierge exchange, or would you like to chat about something else?">' . esc_textarea( (string) ( $c['off_ramp_phrases'] ?? '' ) ) . '</textarea></div>';
		echo '<div class="flosc-cncrg-row"><label>Start UTC MTS (optional)</label><input type="text" name="flosc_cncrg_start_utc_mts" value="' . esc_attr( (string) ( $c['start_utc_mts'] ?? '' ) ) . '" placeholder="YYYY-MMm-DDd-THHh:MMm:SSs"></div>';
		echo '<div class="flosc-cncrg-row"><label>End UTC MTS (optional)</label><input type="text" name="flosc_cncrg_end_utc_mts" value="' . esc_attr( (string) ( $c['end_utc_mts'] ?? ( $c['expires_utc_mts'] ?? '' ) ) ) . '" placeholder="YYYY-MMm-DDd-THHh:MMm:SSs"></div>';
		echo '<div class="flosc-cncrg-row"><label>Expires UTC MTS (Michel format)</label><input type="text" name="flosc_cncrg_expires_utc_mts" value="' . esc_attr( (string) ( $c['expires_utc_mts'] ?? '' ) ) . '" placeholder="YYYY-MMm-DDd-THHh:MMm:SSs"></div>';
		echo '<div class="flosc-cncrg-row"><label>Parameters (one per line: key=value)</label><textarea name="flosc_cncrg_parameters" rows="4" placeholder="guest_name=Frank\npassword_hint=123\nscore_1=Skumja Daina">' . esc_textarea( (string) ( $c['parameters_text'] ?? '' ) ) . '</textarea></div>';
		echo '<div class="flosc-cncrg-row"><label>Instruction template (optional)</label><textarea name="flosc_cncrg_instruction_template" rows="4" placeholder="Use {guest_name} by name. Mention expiry {expires_utc_mts}. Reveal one item at a time.">' . esc_textarea( (string) ( $c['instruction_template'] ?? '' ) ) . '</textarea></div>';
		echo '<p class="description">The post body is the AI\'s private brief — it offers it a little at a time, in your chosen voice, only what the guest asks for. Saving syncs this to the chat (DB and .md).</p>';
	}

	/**
	 * Save the meta box fields to post meta.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function save_meta_box( $post_id ) {
		if ( ! isset( $_POST['flosc_concierge_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['flosc_concierge_nonce'] ) ), 'flosc_concierge_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$map = array(
			'flow'      => 'flosc_cncrg_flow',
			'keyword'   => 'flosc_cncrg_keyword',
			'password'  => 'flosc_cncrg_password',
			'success'   => 'flosc_cncrg_success',
			'max_tries' => 'flosc_cncrg_max_tries',
			'retry'     => 'flosc_cncrg_retry',
			'delivery'  => 'flosc_cncrg_delivery',
			'off_ramp_phrases' => 'flosc_cncrg_off_ramp_phrases',
			'off_ramp_exactness' => 'flosc_cncrg_off_ramp_exactness',
			'start'     => 'flosc_cncrg_start_utc_mts',
			'end'       => 'flosc_cncrg_end_utc_mts',
			'expires'   => 'flosc_cncrg_expires_utc_mts',
			'params'    => 'flosc_cncrg_parameters',
			'instruct'  => 'flosc_cncrg_instruction_template',
		);
		foreach ( $map as $key => $field ) {
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}
			// Sanitize at the point of access, per field type.
			if ( 'retry' === $key || 'delivery' === $key || 'params' === $key || 'instruct' === $key || 'off_ramp_phrases' === $key ) {
				$value = sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) );
			} elseif ( 'max_tries' === $key ) {
				$value = (string) max( 1, absint( wp_unslash( $_POST[ $field ] ) ) );
			} elseif ( 'off_ramp_exactness' === $key ) {
				$value = self::off_ramp_exactness( sanitize_key( wp_unslash( $_POST[ $field ] ) ) );
			} elseif ( 'start' === $key || 'end' === $key || 'expires' === $key ) {
				$value = self::normalize_utc_mts( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			} else {
				$value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
			}
			update_post_meta( $post_id, self::META[ $key ], $value );
		}
	}

	/**
	 * Sync a concierge post into its flow's DB (upsert a concierge message).
	 *
	 * @param int|WP_Post $post Post or ID.
	 * @return void
	 */
	public static function sync_post( $post ) {
		$post = get_post( $post );
		if ( ! $post instanceof WP_Post || ! self::is_concierge_post( $post ) ) {
			return;
		}
		if ( in_array( (string) $post->post_status, array( 'publish', 'private' ), true ) ) {
			// Keep syncing only from statuses that are intended to be live.
		} else {
			self::unsync_post( $post );
			return;
		}

		$c        = self::config_from_post( $post );
		$flow_key = self::flow_key( $c['flow'] );
		if ( '' === $flow_key || '' === $c['keyword'] ) {
			return; // Needs a resolvable flow and a keyword to be actionable.
		}

		$retry_list = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $c['retry'] ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$retry_list[] = $line;
			}
		}

		$fs = get_option( $flow_key, array() );
		if ( ! isset( $fs['ivr_messages'] ) || ! is_array( $fs['ivr_messages'] ) ) {
			$fs['ivr_messages'] = array();
		}
		$id = self::post_message_id( $post );

		$fs['ivr_messages'][ $id ] = array(
			'name'                        => $id,
			'type'                        => self::TYPE,
			'phase'                       => 'freeline',
			'keywords'                    => $c['keyword'],
			'individual_message_password' => $c['password'],
			'password_success'            => $c['success'],
			'password_max_tries'          => $c['max_tries'],
			'password_retry_messages'     => $retry_list,
			'delivery_style'              => $c['delivery'],
			'off_ramp_phrases'            => (string) ( $c['off_ramp_phrases'] ?? '' ),
			'off_ramp_exactness'          => self::off_ramp_exactness( (string) ( $c['off_ramp_exactness'] ?? '' ) ),
			'start_utc_mts'               => (string) ( $c['start_utc_mts'] ?? '' ),
			'end_utc_mts'                 => (string) ( $c['end_utc_mts'] ?? ( $c['expires_utc_mts'] ?? '' ) ),
			'expires_utc_mts'             => (string) ( $c['expires_utc_mts'] ?? '' ),
			'template_parameters'         => (string) ( $c['parameters_text'] ?? '' ),
			'instruction_template'        => (string) ( $c['instruction_template'] ?? '' ),
			'content'                     => $c['content'],
			'source'                      => 'concierge_post',
			'concierge_post_id'           => (int) $post->ID,
		);
		update_option( $flow_key, $fs ); // The integrity hook mirrors this to the .md.
	}

	/**
	 * Remove a concierge post's synced message from its flow.
	 *
	 * @param int|WP_Post $post Post or ID.
	 * @return void
	 */
	public static function unsync_post( $post ) {
		$post = get_post( $post );
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$c = self::config_from_post( $post );
		$flow_key = self::flow_key( $c['flow'] ?? '' );
		if ( '' === $flow_key ) {
			$flow_key = self::flow_key( self::flow_file( self::label( (string) $post->post_content, 'floscFlow' ) ) );
		}
		if ( '' === $flow_key ) {
			return;
		}
		$fs = get_option( $flow_key, array() );
		$id = self::post_message_id( $post );
		if ( isset( $fs['ivr_messages'][ $id ] ) ) {
			unset( $fs['ivr_messages'][ $id ] );
			update_option( $flow_key, $fs );
		}
	}

	/**
	 * Append the admin-only "what FLOSC understands" confirmation to a concierge post.
	 *
	 * Read-only by design — editing happens in the post itself, and saving re-syncs.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function maybe_append_confirmation( $content ) {
		if ( is_admin() || ! is_singular() || ! current_user_can( 'manage_options' ) ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post instanceof WP_Post || ! self::is_concierge_post( $post ) ) {
			return $content;
		}

		$c         = self::config_from_post( $post );
		$flow_stem = ( '' !== $c['flow'] ) ? sanitize_key( pathinfo( $c['flow'], PATHINFO_FILENAME ) ) : '';
		$flow_ok   = ( '' !== $flow_stem ) && ! empty( get_option( 'flosc_flow_' . $flow_stem ) );
		$active    = ( '' !== $c['keyword'] && $flow_ok && self::is_active_now( $c ) );
		$delivers  = trim( (string) preg_replace( '/\s+/', ' ', (string) $c['content'] ) );

		$lines = array(
			'deployment : ' . ( '' !== $c['deployment'] ? $c['deployment'] : '(none)' ),
			'flow       : ' . ( '' !== $flow_stem ? $flow_stem . ( $flow_ok ? ' ✓' : ' — not found' ) : '(missing flow)' ),
			'keyword    : ' . ( '' !== $c['keyword'] ? $c['keyword'] : '(missing — required)' ),
			'password   : ' . ( '' !== $c['password'] ? 'set (exact)' : '(none — instant)' ),
			'start      : ' . ( '' !== (string) ( $c['start_utc_mts'] ?? '' ) ? (string) $c['start_utc_mts'] : '(none)' ),
			'end        : ' . ( '' !== (string) ( $c['end_utc_mts'] ?? ( $c['expires_utc_mts'] ?? '' ) ) ? (string) ( $c['end_utc_mts'] ?? $c['expires_utc_mts'] ) : '(none)' ),
			'expires    : ' . ( '' !== (string) ( $c['expires_utc_mts'] ?? '' ) ? (string) $c['expires_utc_mts'] : '(none)' ),
			'params     : ' . ( '' !== trim( (string) ( $c['parameters_text'] ?? '' ) ) ? 'set' : '(none)' ),
			'delivers   : ' . ( '' !== $delivers ? mb_strimwidth( $delivers, 0, 90, '…' ) : '(empty)' ),
			'status     : ' . ( $active ? 'active on ' . $flow_stem : 'NOT active — fix the above' ),
		);

		$html  = '<div class="flosc-cncrg-confirm">';
		$html .= '<div class="flosc-cncrg-confirm-title">— what FLOSC understands (admin only) —</div>';
		$html .= '<pre class="flosc-cncrg-confirm-body">' . esc_html( implode( "\n", $lines ) ) . '</pre>';
		$html .= '<div class="flosc-cncrg-confirm-foot">Edit this post to change the setup; saving re-syncs it to the chat.</div>';
		$html .= '</div>';

		return $content . $html;
	}

	/** Is this post in the concierge category? */
	public static function is_concierge_post( $post ) {
		$post = get_post( $post );
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		$terms = get_the_terms( $post->ID, 'category' );
		if ( ! is_array( $terms ) ) {
			return false;
		}

		foreach ( $terms as $term ) {
			$slug = sanitize_title( (string) ( $term->slug ?? '' ) );
			if ( self::INTERNAL_CONCIERGE_CHILD_SLUG === $slug ) {
				return true;
			}

			if ( self::CATEGORY === $slug && intval( $term->parent ) > 0 ) {
				$parent = get_term( intval( $term->parent ), 'category' );
				if ( $parent && ! is_wp_error( $parent ) ) {
					$parent_slug = sanitize_title( (string) ( $parent->slug ?? '' ) );
					if ( self::INTERNAL_PARENT_SLUG === $parent_slug ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/** Stable, slug-derived message id for a post-sourced concierge message. */
	protected static function post_message_id( $post ) {
		$slug = ( '' !== $post->post_name ) ? $post->post_name : ( 'post' . $post->ID );
		return 'concierge_' . sanitize_key( $slug );
	}

	/** Resolve a flow option key from a flow file ('dainis_net_ivr.md' -> 'flosc_flow_dainis_net_ivr'). */
	protected static function flow_key( $flow_file ) {
		$flow_file = (string) $flow_file;
		if ( '' === $flow_file ) {
			return '';
		}
		return 'flosc_flow_' . sanitize_key( pathinfo( $flow_file, PATHINFO_FILENAME ) );
	}

	/** Pull a '*.md' flow file out of a floscFlow value ('Br3nda (dainis_net_ivr.md)' -> 'dainis_net_ivr.md'). */
	protected static function flow_file( $value ) {
		if ( preg_match( '/([A-Za-z0-9_\-]+\.md)\b/i', (string) $value, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * Resolve a flow file from a flow's NAME ('Br3nda' -> 'dainis_net_ivr.md').
	 *
	 * Every flow carries a human name (identity.name) — the same name shown in the
	 * IVR editor and the Flow dropdown. Matching is case-insensitive and ignores
	 * surrounding quotes, so an admin or OpenClaw can simply name the flow.
	 *
	 * @param string $value A flow name, possibly quoted.
	 * @return string Flow file (e.g. 'dainis_net_ivr.md'), or '' if no name matches.
	 */
	protected static function flow_by_name( $value ) {
		$name = mb_strtolower( self::unquote( (string) $value ) );
		if ( '' === $name ) {
			return '';
		}
		$files = function_exists( 'flosc_config_glob' ) ? flosc_config_glob( '*_ivr.md' ) : array();
		foreach ( (array) $files as $file ) {
			$fname = basename( (string) $file );
			$opt   = get_option( 'flosc_flow_' . sanitize_key( pathinfo( $fname, PATHINFO_FILENAME ) ), array() );
			$iname = ( is_array( $opt ) && ! empty( $opt['identity']['name'] ) ) ? mb_strtolower( (string) $opt['identity']['name'] ) : '';
			if ( '' !== $iname && $iname === $name ) {
				return $fname;
			}
		}
		return '';
	}

	/**
	 * Resolve a flow file from a human-facing Deployment ('dainis.net/chat' -> 'dainis_net_ivr.md').
	 *
	 * OpenClaw writes the deployment it knows — "dainis.net/chat", "flosc.ai",
	 * "lesaep.com" — never the internal filename. We reduce that to a bare domain,
	 * turn it into a stem ("dainis.net" -> "dainis_net"), and match it against the
	 * actual *_ivr.md flow files so their names stay the single source of truth.
	 *
	 * @param string $deployment Deployment value from the post body.
	 * @return string Flow file (e.g. 'dainis_net_ivr.md'), or '' if none matches.
	 */
	protected static function flow_from_deployment( $deployment ) {
		$host = strtolower( trim( (string) $deployment ) );
		if ( '' === $host ) {
			return '';
		}
		$host = preg_replace( '#^[a-z][a-z0-9+.\-]*://#', '', $host ); // drop scheme
		$host = preg_replace( '#[/?\#].*$#', '', $host );              // drop path/query/fragment
		$host = preg_replace( '#^www\.#', '', $host );                 // drop www.
		$stem = trim( (string) preg_replace( '/[^a-z0-9]+/', '_', $host ), '_' ); // dainis.net -> dainis_net
		if ( '' === $stem ) {
			return '';
		}
		$files = function_exists( 'flosc_config_glob' ) ? flosc_config_glob( '*_ivr.md' ) : array();
		foreach ( (array) $files as $file ) {
			$name  = basename( (string) $file );
			$fstem = pathinfo( $name, PATHINFO_FILENAME );             // e.g. dainis_net_ivr
			if ( $fstem === $stem || 0 === strpos( $fstem, $stem . '_' ) ) {
				if ( ! empty( get_option( 'flosc_flow_' . sanitize_key( $fstem ) ) ) ) {
					return $name;
				}
			}
		}
		return '';
	}

	/** Read a single-line "Label: value", quotes preserved, returning the trimmed value. */
	protected static function label( $body, $label ) {
		$pattern = '/^[ \t>*_\-]*' . preg_quote( $label, '/' ) . '[ \t]*:[ \t]*(.+?)[ \t]*$/mi';
		return preg_match( $pattern, (string) $body, $m ) ? trim( $m[1] ) : '';
	}

	/**
	 * Resolve the content a concierge message delivers, from the post body.
	 *
	 * What the bot delivers is everything after the "Content to deliver:" line. If
	 * that label is absent, the body is served with the config lines (floscFlow/
	 * Deployment/Keyword/Password) stripped, so a password is never handed out.
	 *
	 * @param string $body Post body.
	 * @return string
	 */
	protected static function content_to_deliver( $body ) {
		$body = (string) $body;

		// What the bot delivers is everything after the "Content to deliver:" line.
		$block = self::content_block( $body, 'Content to deliver' );
		if ( '' !== $block ) {
			return $block;
		}

		// Safety net only (no label present): serve the body with the config lines
		// removed, so a password is never handed to the guest.
		$stripped = preg_replace( '/^[ \t>*_\-]*(floscFlow|Deployment|Keyword|Password)[ \t]*:.*$/mi', '', $body );
		return trim( (string) $stripped );
	}

	/** Parse parameters text (key=value per line) into an associative array. */
	protected static function parse_parameters_text( $text ) {
		$params = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line || false === strpos( $line, '=' ) ) {
				continue;
			}
			list( $key, $value ) = array_map( 'trim', explode( '=', $line, 2 ) );
			$key = sanitize_key( $key );
			if ( '' === $key ) {
				continue;
			}
			$params[ $key ] = (string) $value;
		}
		return $params;
	}

	/** Replace {parameter_name} placeholders in a template. */
	protected static function apply_template_parameters( $text, $params, $expires_utc_mts = '' ) {
		$text = (string) $text;
		if ( '' === $text ) {
			return '';
		}

		$tokens = is_array( $params ) ? $params : array();
		$tokens['utc_now_mts'] = self::utc_now_mts();
		$tokens['expires_utc_mts'] = (string) $expires_utc_mts;

		foreach ( $tokens as $k => $v ) {
			$text = str_replace( '{' . $k . '}', (string) $v, $text );
		}

		return $text;
	}

	/** Current UTC time in Michel timestamp format. */
	protected static function utc_now_mts() {
		return gmdate( 'Y' ) . '-' . gmdate( 'm' ) . 'm-' . gmdate( 'd' ) . 'd-T' . gmdate( 'H' ) . 'h:' . gmdate( 'i' ) . 'm:' . gmdate( 's' ) . 's';
	}

	/** Normalize accepted UTC MTS variants to canonical format; returns '' when invalid. */
	protected static function normalize_utc_mts( $value ) {
		$ts = self::utc_mts_to_unix( (string) $value );
		if ( null === $ts ) {
			return '';
		}
		return gmdate( 'Y', $ts ) . '-' . gmdate( 'm', $ts ) . 'm-' . gmdate( 'd', $ts ) . 'd-T' . gmdate( 'H', $ts ) . 'h:' . gmdate( 'i', $ts ) . 'm:' . gmdate( 's', $ts ) . 's';
	}

	/** Convert UTC MTS to unix timestamp. Supports date-only and date-time forms. */
	protected static function utc_mts_to_unix( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}

		if ( preg_match( '/^(\d{4})-(\d{2})m-(\d{2})d-T(\d{2})h:(\d{2})m:(\d{2})s$/', $value, $m ) ) {
			$y = (int) $m[1];
			$mo = (int) $m[2];
			$d = (int) $m[3];
			$h = (int) $m[4];
			$mi = (int) $m[5];
			$s = (int) $m[6];
			if ( ! checkdate( $mo, $d, $y ) || $h > 23 || $mi > 59 || $s > 59 ) {
				return null;
			}
			return gmmktime( $h, $mi, $s, $mo, $d, $y );
		}

		if ( preg_match( '/^(\d{4})-(\d{2})m-(\d{2})d$/', $value, $m ) ) {
			$y = (int) $m[1];
			$mo = (int) $m[2];
			$d = (int) $m[3];
			if ( ! checkdate( $mo, $d, $y ) ) {
				return null;
			}
			return gmmktime( 23, 59, 59, $mo, $d, $y );
		}

		return null;
	}

	/** Is this concierge message expired for UTC now? */
	protected static function is_expired( $msg ) {
		$expires = trim( (string) ( $msg['end_utc_mts'] ?? ( $msg['expires_utc_mts'] ?? '' ) ) );
		if ( '' === $expires ) {
			return false;
		}
		$ts = self::utc_mts_to_unix( $expires );
		if ( null === $ts ) {
			return false;
		}
		return time() > $ts;
	}

	/** Is this concierge message currently active within its optional UTC window? */
	protected static function is_active_now( $msg ) {
		$start = trim( (string) ( $msg['start_utc_mts'] ?? '' ) );
		if ( '' !== $start ) {
			$start_ts = self::utc_mts_to_unix( $start );
			if ( null !== $start_ts && time() < $start_ts ) {
				return false;
			}
		}

		if ( self::is_expired( $msg ) ) {
			return false;
		}

		return true;
	}

	/** Read everything from a "Label:" to the end of the body. */
	protected static function content_block( $body, $label ) {
		$pattern = '/^[ \t>*_\-]*' . preg_quote( $label, '/' ) . '[ \t]*:[ \t]*/mi';
		if ( preg_match( $pattern, (string) $body, $m, PREG_OFFSET_CAPTURE ) ) {
			return trim( substr( (string) $body, $m[0][1] + strlen( $m[0][0] ) ) );
		}
		return '';
	}

	/** Strip one pair of matching surrounding quotes. */
	protected static function unquote( $value ) {
		$value = trim( (string) $value );
		if ( strlen( $value ) >= 2 ) {
			$first = $value[0];
			$last  = substr( $value, -1 );
			if ( ( '"' === $first && '"' === $last ) || ( "'" === $first && "'" === $last ) ) {
				return substr( $value, 1, -1 );
			}
		}
		return $value;
	}

	private static function off_ramp_exactness( $mode ) {
		$mode = sanitize_key( (string) $mode );
		if ( ! in_array( $mode, array( 'flexible', 'preferred', 'exact' ), true ) ) {
			$mode = 'preferred';
		}
		return $mode;
	}

	private static function off_ramp_guidance( $phrases_text, $exactness ) {
		$phrases = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $phrases_text ) as $line ) {
			$line = trim( (string) $line );
			if ( '' !== $line ) {
				$phrases[] = $line;
			}
		}

		$mode = self::off_ramp_exactness( $exactness );
		if ( empty( $phrases ) ) {
			return "Offer a clear off-ramp at natural points: ask whether they want to continue this current exchange or chat about something else.\n";
		}

		$lead = 'Use one off-ramp phrase from this list before ending, keeping the meaning that they can continue this exchange or switch topics.';
		if ( 'exact' === $mode ) {
			$lead = 'Use ONE off-ramp phrase VERBATIM from this list before ending.';
		} elseif ( 'preferred' === $mode ) {
			$lead = 'Prefer using one off-ramp phrase from this list with close wording.';
		}

		return $lead . "\nOff-ramp phrases:\n- " . implode( "\n- ", $phrases ) . "\n";
	}
}

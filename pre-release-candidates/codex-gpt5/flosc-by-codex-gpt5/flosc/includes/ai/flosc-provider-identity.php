<?php
/**
 * What FLOSC tells an AI provider about itself.
 *
 * Until now, nothing. A provider receiving FLOSC traffic saw an API key, a
 * model name and a prompt, with no way to tell that the request came from a
 * WordPress plugin at all — let alone which one, which version, or which of
 * many installs. Assuming FLOSC is adopted widely, that is a provider's
 * problem as much as ours: they cannot recognise a client they cannot see.
 *
 * So every outbound call to an AI host now carries a User-Agent and one
 * structured trace header. Both are machine-to-machine; no visitor ever sees
 * them, and nothing here changes a single token of the prompt.
 *
 * WHAT IS SENT, exactly:
 *
 *   User-Agent: FLOSC/8.0.0 (+https://flosc.ai) DA1-Personality-Builder/3.1.2
 *               (FLOSC edition) WordPress/7.0.4 PHP/8.2.0
 *
 *   X-DA1-Trace: v=1;app=flosc/8.0.0;bld=da1pb/3.1.2;ed=flosc;
 *                inst=<12 hex>;site=<domain>;flow=<8 hex>;prof=<8 hex>;
 *                kb=<8 hex>;tier=<v|g|m>;pair=<n>
 *
 * WHAT IS NOT SENT: the visitor's id, name, email, IP, or the page they are
 * on. Nothing that narrows a turn to one person. `tier` says what KIND of
 * turn it was — visitor, guest or member — and stops there.
 *
 * The install id is random and generated once. It is NOT derived from the
 * domain, so it survives a site moving house and cannot be reversed into one.
 * The domain, when sent, is sent plainly; a hashed domain is not anonymous,
 * because there are only so many domains and anyone can hash all of them.
 *
 * flow, prof and kb are salted hashes — HMACs under a secret this install
 * generated and keeps. Every correlation the floscAdmin wants survives: the
 * same flow, personality or corpus matches itself forever, on this install.
 * What does not survive is a stranger turning `4d81ac09` back into a name,
 * and two installs running the same shipped personality looking like one
 * install.
 *
 * FLOSC PHONES NOTHING HOME. None of this reaches Anthropic's authors, da1.fm
 * or flosc.ai. It goes to the AI host the floscAdmin configured with their own
 * key, on a request already carrying the entire conversation, and nowhere
 * else.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'flosc_provider_identity_hosts' ) ) {
	/**
	 * The hosts FLOSC identifies itself to.
	 *
	 * Deliberately a list, not a wildcard. This filter sees every outbound
	 * HTTP request WordPress makes, from every plugin on the site; adding a
	 * header to a request that is not ours would be someone else's bug report.
	 *
	 * @return string[]
	 */
	function flosc_provider_identity_hosts() {
		$hosts = array(
			'api.anthropic.com',
			'api.openai.com',
			'api.x.ai',
			'generativelanguage.googleapis.com',
			'api.assemblyai.com',
		);

		/**
		 * Filter the AI hosts FLOSC identifies itself to.
		 *
		 * @param string[] $hosts Hostnames.
		 */
		return (array) apply_filters( 'flosc_provider_identity_hosts', $hosts );
	}
}

if ( ! function_exists( 'flosc_provider_identity_salt' ) ) {
	/**
	 * Per-install salt for the derived ids.
	 *
	 * Random and stored once. Without it the flow and profile hashes would be
	 * plain hashes of short, guessable strings — a provider could tell that a
	 * flow was called "chat" by hashing the word "chat". With it, the same
	 * flow hashes differently on every install, which is what makes these
	 * correlation ids rather than disclosures.
	 *
	 * @return string
	 */
	function flosc_provider_identity_salt() {
		$salt = (string) get_option( 'flosc_provider_identity_salt', '' );
		if ( $salt !== '' ) {
			return $salt;
		}

		$salt = function_exists( 'wp_generate_password' )
			? wp_generate_password( 64, false, false )
			: bin2hex( random_bytes( 32 ) );
		add_option( 'flosc_provider_identity_salt', $salt, '', 'no' );

		// add_option() is a no-op if a concurrent request won the race, so
		// read back rather than trusting the value we generated.
		return (string) get_option( 'flosc_provider_identity_salt', $salt );
	}
}

if ( ! function_exists( 'flosc_provider_install_id' ) ) {
	/**
	 * Opaque, stable id for this FLOSC install.
	 *
	 * Random — NOT derived from the domain, the admin email, or anything else
	 * a provider could reverse. It says "the same install as last time" and
	 * nothing more.
	 *
	 * @return string 12 hex characters.
	 */
	function flosc_provider_install_id() {
		$id = (string) get_option( 'flosc_install_id', '' );
		if ( $id !== '' ) {
			return $id;
		}

		$id = substr( bin2hex( random_bytes( 8 ) ), 0, 12 );
		add_option( 'flosc_install_id', $id, '', 'yes' );

		return (string) get_option( 'flosc_install_id', $id );
	}
}

if ( ! function_exists( 'flosc_provider_identity_digest' ) ) {
	/**
	 * Salted short hash of one value, or '' when there is nothing to hash.
	 *
	 * @param string $value  Value to digest.
	 * @param int    $length Hex characters to keep.
	 * @return string
	 */
	function flosc_provider_identity_digest( $value, $length = 8 ) {
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return '';
		}

		return substr( hash_hmac( 'sha256', $value, flosc_provider_identity_salt() ), 0, max( 4, (int) $length ) );
	}
}

if ( ! function_exists( 'flosc_provider_identity_context' ) ) {
	/**
	 * Read or set the per-turn identity context.
	 *
	 * The HTTP filter runs deep inside the AI Client and knows nothing about
	 * flows or personalities, so the chat turn leaves the few facts worth
	 * carrying here on its way past. Static rather than a global: one request
	 * serves one turn, and a leftover value would mislabel the next one.
	 *
	 * @param array|null $set Values to store, or null to read.
	 * @return array{flow:string,profile:string,pair:int,surface:string,kb:string,tier:string}
	 */
	function flosc_provider_identity_context( $set = null ) {
		static $context = array(
			'flow'    => '',
			'profile' => '',
			'pair'    => 0,
			'surface' => '',
			'kb'      => '',
			'tier'    => '',
		);

		if ( is_array( $set ) ) {
			$context = array(
				'flow'    => isset( $set['flow'] ) ? (string) $set['flow'] : '',
				'profile' => isset( $set['profile'] ) ? (string) $set['profile'] : '',
				'pair'    => isset( $set['pair'] ) ? (int) $set['pair'] : 0,
				'surface' => isset( $set['surface'] ) ? (string) $set['surface'] : '',
				'kb'      => isset( $set['kb'] ) ? (string) $set['kb'] : '',
				'tier'    => isset( $set['tier'] ) ? (string) $set['tier'] : '',
			);
		}

		return $context;
	}
}

if ( ! function_exists( 'flosc_provider_user_agent' ) ) {
	/**
	 * The User-Agent FLOSC sends to AI hosts.
	 *
	 * @return string
	 */
	function flosc_provider_user_agent() {
		$plugin  = defined( 'FLOSC_VERSION' ) ? FLOSC_VERSION : '8.0.0';
		$builder = defined( 'FLOSC_DA1_BUILDER_VERSION' ) ? FLOSC_DA1_BUILDER_VERSION : '3.1.2';

		$parts = array(
			'FLOSC/' . $plugin . ' (+https://flosc.ai)',
			'DA1-Personality-Builder/' . $builder . ' (FLOSC edition; +https://da1.fm)',
		);

		if ( function_exists( 'get_bloginfo' ) ) {
			$wp = (string) get_bloginfo( 'version' );
			if ( $wp !== '' ) {
				$parts[] = 'WordPress/' . $wp;
			}
		}
		$parts[] = 'PHP/' . PHP_VERSION;

		return implode( ' ', $parts );
	}
}

if ( ! function_exists( 'flosc_provider_trace_header' ) ) {
	/**
	 * The structured trace header: one line, key=value pairs, ';' separated.
	 *
	 * Condensed on purpose. Nothing here is for a human to read, and a header
	 * that rides on every turn should cost bytes, not kilobytes.
	 *
	 * @return string
	 */
	function flosc_provider_trace_header() {
		$plugin  = defined( 'FLOSC_VERSION' ) ? FLOSC_VERSION : '8.0.0';
		$builder = defined( 'FLOSC_DA1_BUILDER_VERSION' ) ? FLOSC_DA1_BUILDER_VERSION : '3.1.2';
		$context = flosc_provider_identity_context();

		$pairs = array(
			'v'    => '1',
			'app'  => 'flosc/' . $plugin,
			'bld'  => 'da1pb/' . $builder,
			'ed'   => 'flosc',
			'inst' => flosc_provider_install_id(),
		);

		$flow = flosc_provider_identity_digest( $context['flow'] );
		if ( $flow !== '' ) {
			$pairs['flow'] = $flow;
		}

		// The personality's profile_hash is already a sha256 of the compiled
		// profile. Digesting it again with the install salt keeps two installs
		// running the same shipped personality from looking like one install.
		$profile = flosc_provider_identity_digest( $context['profile'] );
		if ( $profile !== '' ) {
			$pairs['prof'] = $profile;
		}

		// Which knowledge base was in play. Salted like the rest: a provider
		// can see that two turns drew on the same corpus without learning
		// what the corpus is called.
		$kb = flosc_provider_identity_digest( $context['kb'] );
		if ( $kb !== '' ) {
			$pairs['kb'] = $kb;
		}

		// v | g | m. One letter, and the only field here about the person on
		// the other end — no id, no name, no address, nothing that narrows it
		// to one of them. It says what KIND of turn this was, which is what
		// makes "guests are burning tokens and never buying" visible as a
		// shape in a provider's traffic as well as in the floscAdmin's log.
		$tier = strtolower( trim( $context['tier'] ) );
		if ( in_array( $tier, array( 'visitor', 'guest', 'member' ), true ) ) {
			$pairs['tier'] = substr( $tier, 0, 1 );
		}

		if ( $context['pair'] > 0 ) {
			$pairs['pair'] = (string) $context['pair'];
		}

		// The one field that names the site, and it names it plainly rather
		// than as a hash pretending not to be one — a hashed domain is not
		// anonymous, there are only so many domains and anyone can hash all
		// of them.
		//
		// On by default. The request it rides on already carries the entire
		// conversation to a provider the floscAdmin configured with their own
		// key and pays for; the domain adds nothing material beside that, and
		// a provider that cannot tell which site it is serving cannot help
		// when something goes wrong. Off in one click for anyone who
		// disagrees.
		if ( flosc_provider_identity_site_enabled() ) {
			$host = function_exists( 'wp_parse_url' ) ? wp_parse_url( get_bloginfo( 'url' ), PHP_URL_HOST ) : '';
			if ( is_string( $host ) && $host !== '' ) {
				$pairs['site'] = $host;
			}
		}

		$out = array();
		foreach ( $pairs as $key => $value ) {
			// A header value cannot contain CR, LF or ';' without changing
			// what it means. Everything here is generated, but a filtered
			// host or a strange blogname is not.
			$value = preg_replace( '/[^\x21-\x3A\x3C-\x7E]/', '', (string) $value );
			if ( $value !== '' ) {
				$out[] = $key . '=' . $value;
			}
		}

		return implode( ';', $out );
	}
}

if ( ! function_exists( 'flosc_provider_identity_enabled' ) ) {
	/**
	 * Whether FLOSC identifies itself at all. On by default.
	 *
	 * @return bool
	 */
	function flosc_provider_identity_enabled() {
		$settings = get_option( 'flosc_provider_identity', array() );
		$enabled  = is_array( $settings ) && array_key_exists( 'enabled', $settings )
			? (bool) $settings['enabled']
			: true;

		/**
		 * Filter whether FLOSC sends identity headers to AI providers.
		 *
		 * @param bool $enabled Whether to send.
		 */
		return (bool) apply_filters( 'flosc_provider_identity_enabled', $enabled );
	}
}

if ( ! function_exists( 'flosc_provider_identity_site_enabled' ) ) {
	/**
	 * Whether the site's own domain rides along. On by default.
	 *
	 * @return bool
	 */
	function flosc_provider_identity_site_enabled() {
		$settings = get_option( 'flosc_provider_identity', array() );
		$enabled  = is_array( $settings ) && array_key_exists( 'send_site', $settings )
			? (bool) $settings['send_site']
			: true;

		/**
		 * Filter whether the site domain is disclosed to AI providers.
		 *
		 * @param bool $enabled Whether to disclose.
		 */
		return (bool) apply_filters( 'flosc_provider_identity_site_enabled', $enabled );
	}
}

if ( ! function_exists( 'flosc_provider_identity_http_args' ) ) {
	/**
	 * Attach the identity headers to outbound AI requests.
	 *
	 * Hooked on http_request_args rather than added at each call site, because
	 * three of the four chat providers go through the WordPress AI Client and
	 * its official provider plugins, whose HTTP calls FLOSC does not make and
	 * cannot reach. This filter is the one place that sees all of them.
	 *
	 * @param array  $args Request arguments.
	 * @param string $url  Request URL.
	 * @return array
	 */
	function flosc_provider_identity_http_args( $args, $url ) {
		if ( ! flosc_provider_identity_enabled() ) {
			return $args;
		}

		$host = function_exists( 'wp_parse_url' ) ? wp_parse_url( (string) $url, PHP_URL_HOST ) : '';
		if ( ! is_string( $host ) || $host === '' ) {
			return $args;
		}
		if ( ! in_array( strtolower( $host ), array_map( 'strtolower', flosc_provider_identity_hosts() ), true ) ) {
			return $args;
		}

		if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = array();
		}

		// Do not overwrite a header the caller set deliberately.
		$has = function ( $name ) use ( $args ) {
			foreach ( array_keys( $args['headers'] ) as $key ) {
				if ( strcasecmp( (string) $key, $name ) === 0 ) {
					return true;
				}
			}
			return false;
		};

		if ( ! $has( 'X-DA1-Trace' ) ) {
			$trace = flosc_provider_trace_header();
			if ( $trace !== '' ) {
				$args['headers']['X-DA1-Trace'] = $trace;
			}
		}

		// user-agent is a top-level WP_Http argument, not a header, and WP
		// writes it into the headers itself. Setting the header alone would be
		// overwritten; setting both would send it twice.
		$args['user-agent'] = flosc_provider_user_agent();

		return $args;
	}
}

if ( ! function_exists( 'flosc_provider_last_request_id' ) ) {
	/**
	 * The provider's own id for the most recent AI request, or ''.
	 *
	 * This is the one identifier that exists on BOTH sides of the wire.
	 * Everything FLOSC sends outward lands in a provider's logs and can never
	 * be read back — there is no API to ask Anthropic "show me requests
	 * tagged inst=9f3c". The id they return is the reverse direction: a
	 * floscAdmin holding it can ask the provider to look up that exact call.
	 * Without it, our ledger and theirs can never be joined.
	 *
	 * Captured rather than passed through, because three of the four chat
	 * providers answer inside the WordPress AI Client, whose response object
	 * FLOSC never sees. The http_response filter does see it.
	 *
	 * @param array|null $set Internal: the value to store.
	 * @return string
	 */
	function flosc_provider_last_request_id( $set = null ) {
		static $request_id = '';

		if ( is_array( $set ) ) {
			$request_id = (string) $set[0];
		}

		return $request_id;
	}
}

if ( ! function_exists( 'flosc_provider_capture_request_id' ) ) {
	/**
	 * Remember the provider's request id from an AI response.
	 *
	 * Providers do not agree on the header name, so all the known spellings
	 * are checked in order. An unrecognised provider simply leaves the id
	 * empty, which is the honest outcome — a blank column says "not recorded",
	 * and inventing one would put a value in the ledger that no provider can
	 * look up.
	 *
	 * @param array  $response HTTP response.
	 * @param array  $args     Request arguments.
	 * @param string $url      Request URL.
	 * @return array The response, unchanged.
	 */
	function flosc_provider_capture_request_id( $response, $args, $url ) {
		$host = function_exists( 'wp_parse_url' ) ? wp_parse_url( (string) $url, PHP_URL_HOST ) : '';
		if ( ! is_string( $host ) || $host === '' ) {
			return $response;
		}
		if ( ! in_array( strtolower( $host ), array_map( 'strtolower', flosc_provider_identity_hosts() ), true ) ) {
			return $response;
		}
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// request-id: Anthropic. x-request-id: OpenAI and xAI.
		// x-guploader-uploadid: the closest Google returns on generativelanguage.
		foreach ( array( 'request-id', 'x-request-id', 'x-guploader-uploadid' ) as $header ) {
			$value = wp_remote_retrieve_header( $response, $header );
			if ( is_array( $value ) ) {
				$value = reset( $value );
			}
			$value = trim( (string) $value );
			if ( $value !== '' ) {
				// Stored in a VARCHAR(128) column; providers stay well under
				// that, but a header is whatever the far end chose to send.
				flosc_provider_last_request_id( array( substr( sanitize_text_field( $value ), 0, 128 ) ) );
				return $response;
			}
		}

		return $response;
	}
}

add_filter( 'http_request_args', 'flosc_provider_identity_http_args', 10, 2 );
add_filter( 'http_response', 'flosc_provider_capture_request_id', 10, 3 );

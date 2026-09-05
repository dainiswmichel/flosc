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
 *                inst=<12 hex>;flow=<8 hex>;prof=<8 hex>;pair=<n>
 *
 * WHAT IS NOT SENT: the site's domain, the visitor, their tier, their IP,
 * their name, the page they are on, or anything derived from them. The
 * install id is random and generated once — it is not derived from the domain,
 * so it cannot be reversed into one. A floscAdmin who *wants* the provider to
 * know the domain can turn that on; it is off by default, because a plugin
 * that quietly tells four companies where every copy of it lives is not a
 * plugin anyone should install.
 *
 * The flow and profile ids are salted hashes of the flow stem and the
 * personality's profile_hash. They let a provider see that two requests came
 * from the same flow or the same personality without learning what either is
 * called.
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
	 * @return array{flow:string,profile:string,pair:int,surface:string}
	 */
	function flosc_provider_identity_context( $set = null ) {
		static $context = array(
			'flow'    => '',
			'profile' => '',
			'pair'    => 0,
			'surface' => '',
		);

		if ( is_array( $set ) ) {
			$context = array(
				'flow'    => isset( $set['flow'] ) ? (string) $set['flow'] : '',
				'profile' => isset( $set['profile'] ) ? (string) $set['profile'] : '',
				'pair'    => isset( $set['pair'] ) ? (int) $set['pair'] : 0,
				'surface' => isset( $set['surface'] ) ? (string) $set['surface'] : '',
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

		if ( $context['pair'] > 0 ) {
			$pairs['pair'] = (string) $context['pair'];
		}

		// Off by default. On, this is the one field that names the site — so it
		// is the site's own URL, plainly, rather than a hash pretending not to
		// be one. A hashed domain is not anonymous: there are only so many
		// domains, and anyone can hash all of them.
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
	 * Whether the site's own domain rides along. Off by default.
	 *
	 * @return bool
	 */
	function flosc_provider_identity_site_enabled() {
		$settings = get_option( 'flosc_provider_identity', array() );
		$enabled  = is_array( $settings ) && ! empty( $settings['send_site'] );

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

add_filter( 'http_request_args', 'flosc_provider_identity_http_args', 10, 2 );

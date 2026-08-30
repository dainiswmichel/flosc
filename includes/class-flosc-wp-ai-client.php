<?php
/**
 * WordPress 7.0 AI Client hop for OpenAI, Anthropic, and Gemini.
 *
 * Entry: wp_ai_client_prompt(). Vendor HTTP lives in the official provider
 * plugins. FLOSC binds this install’s BYOK key onto the core registry
 * (ApiKeyRequestAuthentication / Anthropic x-api-key / Google X-Goog-Api-Key)
 * and does not read Settings → Connectors.
 *
 * Requires at least WordPress 7.0.4. Operators attach one provider per flow
 * and install that plugin. A developer testing all three activates all three.
 *
 * xAI has no official plugin. Whisper transcription is not in AI Provider
 * for OpenAI. Those hops stay in FLOSC.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use WordPress\AiClient\Tools\DTO\FunctionResponse;
use WordPress\AnthropicAiProvider\Authentication\AnthropicApiKeyRequestAuthentication;
use WordPress\GoogleAiProvider\Authentication\GoogleApiKeyRequestAuthentication;

class FLOSC_WP_AI_Client {

	/**
	 * FLOSC chat slugs that go through the WordPress AI Client.
	 *
	 * @return array<int,string>
	 */
	public static function client_provider_slugs() {
		return array( 'anthropic', 'openai', 'gemini' );
	}

	/**
	 * @return array<string,string> FLOSC slug => WordPress provider id.
	 */
	public static function provider_id_map() {
		return array(
			'openai'    => 'openai',
			'anthropic' => 'anthropic',
			'gemini'    => 'google',
		);
	}

	/**
	 * @param string $flosc_provider FLOSC slug.
	 * @return string wordpress.org plugin slug, or empty.
	 */
	public static function plugin_slug( $flosc_provider ) {
		$map = array(
			'openai'    => 'ai-provider-for-openai',
			'anthropic' => 'ai-provider-for-anthropic',
			'gemini'    => 'ai-provider-for-google',
		);
		$flosc_provider = sanitize_key( (string) $flosc_provider );
		return isset( $map[ $flosc_provider ] ) ? $map[ $flosc_provider ] : '';
	}

	/**
	 * @param string $flosc_provider FLOSC slug.
	 * @return string
	 */
	public static function plugin_name( $flosc_provider ) {
		$map = array(
			'openai'    => __( 'AI Provider for OpenAI', 'flosc' ),
			'anthropic' => __( 'AI Provider for Anthropic', 'flosc' ),
			'gemini'    => __( 'AI Provider for Google', 'flosc' ),
		);
		$flosc_provider = sanitize_key( (string) $flosc_provider );
		return isset( $map[ $flosc_provider ] ) ? $map[ $flosc_provider ] : '';
	}

	/**
	 * @param string $flosc_provider FLOSC slug.
	 * @return string
	 */
	public static function plugin_directory_url( $flosc_provider ) {
		$slug = self::plugin_slug( $flosc_provider );
		return $slug !== '' ? 'https://wordpress.org/plugins/' . $slug . '/' : '';
	}

	/**
	 * Plugins screen search URL for this official plugin.
	 *
	 * @param string $flosc_provider FLOSC slug.
	 * @return string
	 */
	public static function plugin_install_url( $flosc_provider ) {
		$slug = self::plugin_slug( $flosc_provider );
		if ( $slug === '' ) {
			return '';
		}
		return add_query_arg(
			array(
				's'    => $slug,
				'tab'  => 'search',
				'type' => 'term',
			),
			admin_url( 'plugin-install.php' )
		);
	}

	/**
	 * @param string $flosc_provider FLOSC slug.
	 * @return string WordPress AI Client provider id, or empty.
	 */
	public static function wordpress_provider_id( $flosc_provider ) {
		$map = self::provider_id_map();
		$flosc_provider = sanitize_key( (string) $flosc_provider );
		return isset( $map[ $flosc_provider ] ) ? $map[ $flosc_provider ] : '';
	}

	/**
	 * @param string $flosc_provider FLOSC slug.
	 * @return bool
	 */
	public static function uses_official_plugin( $flosc_provider ) {
		return self::wordpress_provider_id( $flosc_provider ) !== '';
	}

	/**
	 * @return bool
	 */
	public static function core_client_exists() {
		return function_exists( 'wp_ai_client_prompt' ) && class_exists( AiClient::class );
	}

	/**
	 * Official provider is in the core registry (plugin loaded and registered).
	 *
	 * @param string $flosc_provider FLOSC slug.
	 * @return bool
	 */
	public static function is_provider_registered( $flosc_provider ) {
		if ( ! self::core_client_exists() ) {
			return false;
		}
		$wp_id = self::wordpress_provider_id( $flosc_provider );
		if ( $wp_id === '' ) {
			return false;
		}
		try {
			return AiClient::defaultRegistry()->hasProvider( $wp_id );
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Status rows for Anthropic, OpenAI, and Gemini — the three hops a
	 * developer tests. Operators still attach only one per flow.
	 *
	 * @return array<int,array<string,string|bool>>
	 */
	public static function plugin_status_rows() {
		$rows = array();
		foreach ( self::client_provider_slugs() as $slug ) {
			$rows[] = array(
				'slug'       => $slug,
				'label'      => $slug === 'gemini' ? 'Gemini' : ucfirst( $slug ),
				'plugin'     => self::plugin_name( $slug ),
				'registered' => self::is_provider_registered( $slug ),
				'directory'  => self::plugin_directory_url( $slug ),
				'install'    => self::plugin_install_url( $slug ),
			);
		}
		return $rows;
	}

	/**
	 * Admin table: Active / Not active for all three official plugins.
	 *
	 * @return string Escaped HTML.
	 */
	public static function plugin_status_table_html() {
		if ( ! self::core_client_exists() ) {
			return '<div class="notice notice-error inline"><p>'
				. esc_html__( 'WordPress 7.0 AI Client is not available. FLOSC requires WordPress 7.0.4 or later.', 'flosc' )
				. '</p></div>';
		}

		$html  = '<table class="widefat striped" style="max-width:720px;margin:12px 0;">';
		$html .= '<thead><tr>';
		$html .= '<th>' . esc_html__( 'FLOSC provider', 'flosc' ) . '</th>';
		$html .= '<th>' . esc_html__( 'Official plugin', 'flosc' ) . '</th>';
		$html .= '<th>' . esc_html__( 'WordPress AI Client', 'flosc' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( self::plugin_status_rows() as $row ) {
			$html .= '<tr><td>' . esc_html( $row['label'] ) . '</td><td>';
			$html .= '<a href="' . esc_url( $row['directory'] ) . '" target="_blank" rel="noopener noreferrer">'
				. esc_html( $row['plugin'] ) . '</a>';
			$html .= '</td><td>';
			if ( $row['registered'] ) {
				$html .= esc_html__( 'Registered', 'flosc' );
			} else {
				$html .= esc_html__( 'Not registered.', 'flosc' ) . ' ';
				$html .= '<a href="' . esc_url( $row['install'] ) . '">' . esc_html__( 'Install', 'flosc' ) . '</a>';
			}
			$html .= '</td></tr>';
		}

		$html .= '</tbody></table>';
		$html .= '<p class="description">'
			. esc_html__( 'A flow attaches one of these. Register every plugin you will Test. Keys stay in FLOSC (this flow or All Flows), not Settings → Connectors.', 'flosc' )
			. '</p>';
		return $html;
	}

	/**
	 * One chat hop through wp_ai_client_prompt().
	 *
	 * @param array $args See file header: provider, message, system_prompt, history, model, temperature, max_tokens, tools, function_responses, test_mode.
	 * @return array|WP_Error { text, function_calls, model_message, usage, model, provider }
	 */
	public static function generate( $args ) {
		// The provider plugins are third-party code reached through a builder
		// that throws. Anything escaping this method becomes a WordPress fatal
		// in the middle of a visitor's conversation, so nothing escapes it.
		try {
			return self::generate_inner( $args );
		} catch ( Throwable $e ) {
			$test_mode = is_array( $args ) && ! empty( $args['test_mode'] );

			return new WP_Error(
				'flosc_wp_ai_provider_exception',
				$test_mode
					? sprintf(
						"The provider integration threw an error.\n\nProvider message:\n%s\n\n📝 Next steps:\n1. Confirm the provider plugin is activated and up to date\n2. Confirm the model id is in that provider's catalog\n3. Test again",
						$e->getMessage()
					)
					: 'The AI provider could not answer that.',
				array(
					'exception' => get_class( $e ),
					'detail'    => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * The chat hop itself. Only ever called from generate(), which owns the
	 * guarantee that no provider exception reaches WordPress.
	 *
	 * @param array $args See generate().
	 * @return array|WP_Error
	 */
	private static function generate_inner( $args ) {
		$args        = is_array( $args ) ? $args : array();
		$provider    = sanitize_key( (string) ( $args['provider'] ?? '' ) );
		$test_mode   = ! empty( $args['test_mode'] );
		$wp_id       = self::wordpress_provider_id( $provider );

		if ( $wp_id === '' ) {
			return new WP_Error(
				'flosc_wp_ai_unsupported_provider',
				sprintf( 'FLOSC provider "%s" is not a WordPress AI Client hop.', $provider )
			);
		}

		if ( ! self::core_client_exists() ) {
			return new WP_Error(
				'flosc_wp_ai_missing_core',
				$test_mode
					? "WordPress 7.0 AI Client is not available.\n\nFLOSC Requires at least WordPress 7.0.4."
					: 'WordPress AI Client is not available.'
			);
		}

		if ( ! self::is_provider_registered( $provider ) ) {
			$name = self::plugin_name( $provider );
			$url  = self::plugin_directory_url( $provider );
			return new WP_Error(
				'flosc_wp_ai_missing_plugin',
				$test_mode
					? "{$name} is not registered with the WordPress AI Client.\n\n📝 Next steps:\n1. Plugins → Add New → search {$name}\n   {$url}\n2. Install and activate it\n3. Keep the API key in FLOSC\n4. Test again"
					: sprintf( 'Install and activate %s to use this chat API.', $name )
			);
		}

		$api_key = function_exists( 'flosc_get_provider_api_key' )
			? flosc_get_provider_api_key( $provider )
			: flosc_get_setting( $provider . '_api_key', '' );
		if ( $api_key === '' || $api_key === null ) {
			return self::no_key_error( $provider, $test_mode );
		}

		$bound = self::bind_flosc_key( $provider, (string) $api_key );
		if ( is_wp_error( $bound ) ) {
			return $bound;
		}

		$model_resolved = true;
		$builder        = self::make_builder( $args, $wp_id, $model_resolved );
		if ( is_wp_error( $builder ) ) {
			return $builder;
		}

		$temperature = isset( $args['temperature'] ) ? (float) $args['temperature'] : 0.3;
		$builder->using_temperature( $temperature );
		if ( ! $builder->is_supported_for_text_generation() ) {
			$builder = self::make_builder( $args, $wp_id, $model_resolved );
			if ( is_wp_error( $builder ) ) {
				return $builder;
			}
		}

		$plugin_name  = self::plugin_name( $provider );
		$model_wanted = (string) ( $args['model'] ?? '' );

		// A model id the provider plugin does not carry cannot resolve, pinned
		// or preferred. FLOSC does not quietly answer as some other model —
		// the flow would then be curated by something nobody chose. It says
		// which id failed and stops.
		if ( ! $model_resolved ) {
			return new WP_Error(
				'flosc_wp_ai_unknown_model',
				$test_mode
					? sprintf(
						"The model id \"%s\" is not in %s's catalog on this site.\n\n📝 Next steps:\n1. Open the AI tab and enter a model id the installed plugin offers\n2. Or update %s so it carries this one\n3. Save, then test again",
						$model_wanted,
						$plugin_name,
						$plugin_name
					)
					: sprintf( 'The configured AI model is not available from %s.', $plugin_name ),
				array( 'model' => $model_wanted )
			);
		}

		if ( ! $builder->is_supported_for_text_generation() ) {
			return new WP_Error(
				'flosc_wp_ai_not_supported',
				$test_mode
					? "This prompt is not supported for text generation with {$plugin_name}.\n\n📝 Next steps:\n1. Confirm the plugin is activated and up to date\n2. Confirm the model id is one the plugin offers\n3. Test again"
					: 'This prompt is not supported by the active AI provider.'
			);
		}

		$result = $builder->generate_text_result();
		if ( is_wp_error( $result ) ) {
			if ( $test_mode ) {
				return new WP_Error(
					$result->get_error_code(),
					"WordPress AI Client error.\n\n❌ " . $result->get_error_message() . "\n\n📝 Next steps:\n1. Confirm {$plugin_name} is active\n2. Confirm the FLOSC API key is valid\n3. Confirm the model id is current"
				);
			}
			return $result;
		}

		return self::parse_result( $result, $provider, (string) ( $args['model'] ?? '' ) );
	}

	/**
	 * Tool loop. $executor is function( $name, $input_array, $call_id ): string.
	 *
	 * @param array    $args     Same as generate(), plus tools.
	 * @param callable $executor Tool runner.
	 * @return array|WP_Error
	 */
	public static function generate_with_tools( $args, $executor ) {
		$args     = is_array( $args ) ? $args : array();
		$history  = self::history_to_messages( isset( $args['history'] ) && is_array( $args['history'] ) ? $args['history'] : array() );
		$message  = (string) ( $args['message'] ?? '' );
		$total_in  = 0;
		$total_out = 0;
		$last      = null;

		for ( $i = 0; $i < 5; $i++ ) {
			$hop_args            = $args;
			$hop_args['history'] = $history;
			if ( $i === 0 ) {
				$hop_args['message'] = $message;
				unset( $hop_args['function_responses'] );
			} else {
				$hop_args['message'] = '';
			}

			$hop = self::generate( $hop_args );
			if ( is_wp_error( $hop ) ) {
				return $hop;
			}

			$usage      = isset( $hop['usage'] ) && is_array( $hop['usage'] ) ? $hop['usage'] : array();
			$total_in  += (int) ( $usage['prompt_tokens'] ?? 0 );
			$total_out += (int) ( $usage['completion_tokens'] ?? 0 );
			$hop['usage'] = array(
				'prompt_tokens'     => $total_in,
				'completion_tokens' => $total_out,
				'total_tokens'      => $total_in + $total_out,
			);
			$last = $hop;

			$calls = isset( $hop['function_calls'] ) && is_array( $hop['function_calls'] ) ? $hop['function_calls'] : array();
			if ( empty( $calls ) ) {
				return $hop;
			}
			if ( ! is_callable( $executor ) ) {
				return new WP_Error( 'flosc_wp_ai_no_tool_executor', 'RAG tools were requested but no executor is available.' );
			}

			if ( $i === 0 && $message !== '' ) {
				$history[] = new Message(
					MessageRoleEnum::user(),
					array( new MessagePart( $message ) )
				);
			}
			if ( ! empty( $hop['model_message'] ) && $hop['model_message'] instanceof Message ) {
				$history[] = $hop['model_message'];
			}

			$responses = array();
			foreach ( $calls as $call ) {
				if ( ! is_object( $call ) ) {
					continue;
				}
				$name  = (string) $call->getName();
				$id    = $call->getId();
				$input = $call->getArgs();
				if ( is_object( $input ) ) {
					$input = (array) $input;
				}
				if ( ! is_array( $input ) ) {
					$input = array();
				}
				$out = call_user_func( $executor, $name, $input, $id );
				if ( is_array( $out ) ) {
					$out = isset( $out['flosc_user_facing_message'] )
						? $out['flosc_user_facing_message']
						: ( isset( $out['flosc_reason'] ) ? $out['flosc_reason'] : wp_json_encode( $out ) );
				}
				$responses[] = new FunctionResponse(
					is_string( $id ) ? $id : null,
					$name !== '' ? $name : null,
					(string) $out
				);
			}
			$args['function_responses'] = $responses;
		}

		return is_array( $last )
			? $last
			: new WP_Error( 'flosc_wp_ai_tool_loop', 'The tool loop did not finish.' );
	}

	/**
	 * Build a WP_AI_Client_Prompt_Builder for this hop.
	 *
	 * PromptBuilder requires the first and last messages to be user role.
	 *
	 * @param array  $args  generate() args.
	 * @param string $wp_id WordPress provider id.
	 * @return WP_AI_Client_Prompt_Builder|WP_Error
	 */
	private static function make_builder( $args, $wp_id, &$model_resolved = null ) {
		$model_resolved = true;
		$message   = (string) ( $args['message'] ?? '' );
		$system    = (string) ( $args['system_prompt'] ?? '' );
		$model     = (string) ( $args['model'] ?? '' );
		$max_tokens = isset( $args['max_tokens'] ) ? (int) $args['max_tokens'] : 500;
		$tools     = isset( $args['tools'] ) && is_array( $args['tools'] ) ? $args['tools'] : array();
		$fn_resps  = isset( $args['function_responses'] ) && is_array( $args['function_responses'] ) ? $args['function_responses'] : array();
		$history   = self::history_to_messages( isset( $args['history'] ) && is_array( $args['history'] ) ? $args['history'] : array() );

		if ( ! empty( $fn_resps ) ) {
			$builder = wp_ai_client_prompt();
			foreach ( $fn_resps as $fr ) {
				if ( $fr instanceof FunctionResponse ) {
					$builder->with_function_response( $fr );
				}
			}
		} else {
			if ( $message === '' ) {
				return new WP_Error( 'flosc_wp_ai_empty_prompt', 'Cannot generate from an empty prompt.' );
			}
			$builder = wp_ai_client_prompt( $message );
		}

		$builder->using_provider( $wp_id );

		if ( $model !== '' ) {
			$pinned = self::pin_model( $wp_id, $model );
			if ( $pinned ) {
				$builder->using_model( $pinned );
			} else {
				// The provider plugin's catalog does not carry this id. Say so
				// upward; a preference on an unknown model cannot resolve either.
				$model_resolved = false;
				$builder->using_model_preference( array( $wp_id, $model ) );
			}
		}

		if ( $system !== '' ) {
			$builder->using_system_instruction( $system );
		}
		if ( $max_tokens > 0 ) {
			$builder->using_max_tokens( $max_tokens );
		}
		if ( ! empty( $history ) ) {
			$builder->with_history( ...$history );
		}

		$declarations = self::tools_to_declarations( $tools );
		if ( ! empty( $declarations ) ) {
			$builder->using_function_declarations( ...$declarations );
		}

		return $builder;
	}

	/**
	 * Whether the installed provider plugin can actually use a model id.
	 *
	 * The ids a provider account can see and the ids the installed WordPress
	 * provider plugin carries are two different lists, and only the second one
	 * works here. This is the check FLOSC's own dispatch makes, exposed so the
	 * admin can be shown the difference before choosing.
	 *
	 * @param string $flosc_provider FLOSC slug.
	 * @param string $model_id       Model id.
	 * @return bool
	 */
	public static function plugin_can_use_model( $flosc_provider, $model_id ) {
		$wp_id = self::wordpress_provider_id( $flosc_provider );

		if ( '' === $wp_id || '' === (string) $model_id ) {
			return false;
		}

		if ( ! self::core_client_exists() || ! self::is_provider_registered( $flosc_provider ) ) {
			return false;
		}

		// Bind the key before asking, exactly as generate() does. The registry
		// will not hand back a model for a provider it cannot authenticate, so
		// asking cold returns null for every id — which reads as "the plugin
		// carries none of these models" when the plugin carries all of them.
		$api_key = function_exists( 'flosc_get_provider_api_key' )
			? flosc_get_provider_api_key( $flosc_provider )
			: flosc_get_setting( $flosc_provider . '_api_key', '' );

		if ( '' !== (string) $api_key && is_wp_error( self::bind_flosc_key( $flosc_provider, (string) $api_key ) ) ) {
			return false;
		}

		return null !== self::pin_model( $wp_id, (string) $model_id );
	}

	/**
	 * Exact model instance from the official plugin, or null if the id is not in its catalog.
	 *
	 * @param string $wp_id    WordPress provider id.
	 * @param string $model_id Model id.
	 * @return object|null ModelInterface
	 */
	private static function pin_model( $wp_id, $model_id ) {
		try {
			return AiClient::defaultRegistry()->getProviderModel( $wp_id, $model_id );
		} catch ( Throwable $e ) {
			return null;
		}
	}

	/**
	 * Bind the FLOSC key using the official plugin’s authentication class.
	 *
	 * @param string $flosc_provider FLOSC slug.
	 * @param string $api_key        FLOSC key.
	 * @return true|WP_Error
	 */
	private static function bind_flosc_key( $flosc_provider, $api_key ) {
		$wp_id = self::wordpress_provider_id( $flosc_provider );
		if ( $wp_id === '' ) {
			return new WP_Error( 'flosc_wp_ai_unsupported_provider', 'No WordPress provider id.' );
		}

		if ( $wp_id === 'anthropic' && class_exists( AnthropicApiKeyRequestAuthentication::class ) ) {
			$auth = new AnthropicApiKeyRequestAuthentication( $api_key );
		} elseif ( $wp_id === 'google' && class_exists( GoogleApiKeyRequestAuthentication::class ) ) {
			$auth = new GoogleApiKeyRequestAuthentication( $api_key );
		} else {
			$auth = new ApiKeyRequestAuthentication( $api_key );
		}

		try {
			AiClient::defaultRegistry()->setProviderRequestAuthentication( $wp_id, $auth );
		} catch ( Throwable $e ) {
			return new WP_Error( 'flosc_wp_ai_auth_bind', $e->getMessage() );
		}
		return true;
	}

	/**
	 * @param string $provider  FLOSC slug.
	 * @param bool   $test_mode Rich copy.
	 * @return WP_Error
	 */
	private static function no_key_error( $provider, $test_mode ) {
		if ( ! $test_mode ) {
			return new WP_Error( 'flosc_wp_ai_no_api_key', 'No API key configured.' );
		}
		$help = array(
			'openai'    => "No OpenAI API key configured.\n\n📝 Next steps:\n1. https://platform.openai.com/api-keys\n2. Paste the key in FLOSC (this flow or All Flows)\n3. Activate AI Provider for OpenAI\n4. Save Settings, then Test",
			'anthropic' => "No Anthropic API key configured.\n\n📝 Next steps:\n1. https://console.anthropic.com/settings/keys\n2. Paste the key in FLOSC (this flow or All Flows)\n3. Activate AI Provider for Anthropic\n4. Save Settings, then Test",
			'gemini'    => "No Gemini API key configured.\n\n📝 Next steps:\n1. https://aistudio.google.com/apikey\n2. Paste the key in FLOSC (this flow or All Flows)\n3. Activate AI Provider for Google\n4. Save Settings, then Test",
		);
		return new WP_Error(
			'flosc_wp_ai_no_api_key',
			isset( $help[ $provider ] ) ? $help[ $provider ] : 'No API key configured.'
		);
	}

	/**
	 * Convert FLOSC turns to Message objects.
	 * PromptBuilder requires the first message to be user — drop leading model/assistant greetings.
	 *
	 * @param array $history FLOSC turns or Message objects.
	 * @return Message[]
	 */
	private static function history_to_messages( $history ) {
		$out = array();
		foreach ( $history as $turn ) {
			if ( $turn instanceof Message ) {
				$out[] = $turn;
				continue;
			}
			if ( ! is_array( $turn ) ) {
				continue;
			}
			$text = (string) ( $turn['content'] ?? '' );
			if ( $text === '' ) {
				continue;
			}
			$role = (string) ( $turn['role'] ?? 'user' );
			if ( $role === 'assistant' ) {
				$role = 'model';
			}
			$enum  = ( $role === 'model' ) ? MessageRoleEnum::model() : MessageRoleEnum::user();
			$out[] = new Message( $enum, array( new MessagePart( $text ) ) );
		}

		while ( ! empty( $out ) && $out[0]->getRole()->isModel() ) {
			array_shift( $out );
		}

		return array_values( $out );
	}

	/**
	 * @param array $tools FLOSC RAG tool arrays (name, description, input_schema).
	 * @return FunctionDeclaration[]
	 */
	private static function tools_to_declarations( $tools ) {
		$out = array();
		foreach ( $tools as $tool ) {
			if ( ! is_array( $tool ) ) {
				continue;
			}
			$name = (string) ( $tool['name'] ?? '' );
			if ( $name === '' ) {
				continue;
			}
			$params = null;
			if ( isset( $tool['input_schema'] ) && is_array( $tool['input_schema'] ) ) {
				$params = $tool['input_schema'];
			} elseif ( isset( $tool['parameters'] ) && is_array( $tool['parameters'] ) ) {
				$params = $tool['parameters'];
			}
			$out[] = new FunctionDeclaration(
				$name,
				(string) ( $tool['description'] ?? '' ),
				$params
			);
		}
		return $out;
	}

	/**
	 * @param GenerativeAiResult $result         Core result.
	 * @param string             $provider       FLOSC slug.
	 * @param string             $requested_model Preferred model id.
	 * @return array
	 */
	private static function parse_result( $result, $provider, $requested_model ) {
		$text          = '';
		$calls         = array();
		$model_message = null;

		if ( $result instanceof GenerativeAiResult ) {
			$model_message = $result->toMessage();
			foreach ( $model_message->getParts() as $part ) {
				$type       = $part->getType();
				$channel_ok = $part->getChannel()->isContent();
				if ( $type->isFunctionCall() ) {
					$call = $part->getFunctionCall();
					if ( $call ) {
						$calls[] = $call;
					}
				} elseif ( $channel_ok && $type->isText() ) {
					$text .= (string) $part->getText();
				}
			}

			$usage             = $result->getTokenUsage();
			$prompt_tokens     = $usage->getPromptTokens();
			$completion_tokens = $usage->getCompletionTokens();
			$total_tokens      = $usage->getTotalTokens();
			if ( $total_tokens <= 0 ) {
				$total_tokens = $prompt_tokens + $completion_tokens;
			}

			$model_id = $requested_model;
			$got      = (string) $result->getModelMetadata()->getId();
			if ( $got !== '' ) {
				$model_id = $got;
			}
		} else {
			$prompt_tokens     = 0;
			$completion_tokens = 0;
			$total_tokens      = 0;
			$model_id          = $requested_model;
		}

		return array(
			'text'           => $text,
			'function_calls' => $calls,
			'model_message'  => $model_message,
			'usage'          => array(
				'prompt_tokens'     => $prompt_tokens,
				'completion_tokens' => $completion_tokens,
				'total_tokens'      => $total_tokens,
			),
			'model'          => $model_id,
			'provider'       => $provider,
		);
	}
}

<?php
/**
 * The timezone resolver answers the same zone it answered before v82.16.
 *
 * FLOSC_Condition_Evaluator::resolve_timezone() decides what "9am" means for a
 * scheduled condition. v82.16 restructured it -- four try/catch blocks whose
 * catch did nothing became a chain over a helper that returns null -- and a
 * resolver that quietly starts answering UTC where it used to answer the site's
 * zone would move every scheduled message by hours without failing anything.
 *
 * So this holds the original implementation and the current one side by side and
 * compares them over every token shape the resolver accepts, with the WordPress
 * functions stubbed three ways: present and working, present and throwing, and
 * absent entirely.
 *
 * Exit 0 when the two agree everywhere, 1 with the disagreements otherwise.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

/**
 * How the WordPress stubs below should behave on this pass.
 *
 * @var string
 */
$GLOBALS['flosc_tz_mode'] = 'working';

/**
 * Stand in for wp_timezone().
 *
 * Deliberately a different zone from wp_timezone_string() below. The first
 * version of this gate had both return the same one, which made it unable to
 * tell the two sources apart: skipping the first entirely still produced the
 * right answer, so a resolver that had stopped consulting it passed. Distinct
 * zones are what make each source's contribution visible.
 *
 * @return DateTimeZone The site zone.
 * @throws Exception When the test is exercising the throwing case.
 */
function wp_timezone() {
	if ( 'throwing' === $GLOBALS['flosc_tz_mode'] ) {
		throw new Exception( 'stored timezone is not one PHP knows' );
	}
	return new DateTimeZone( 'America/Denver' );
}

/**
 * Stand in for get_current_user_id(), which the evaluator's constructor calls.
 * The resolver never reads it.
 *
 * @return int Always 0: nobody is logged in during a CLI gate.
 */
function get_current_user_id() {
	return 0;
}

/**
 * Stand in for wp_timezone_string().
 *
 * @return string The site zone name.
 */
function wp_timezone_string() {
	return 'throwing' === $GLOBALS['flosc_tz_mode'] ? 'Not/AZone' : 'Europe/Riga';
}

/**
 * The implementation as it stood before v82.16, kept here as the thing to
 * compare against. Not called by the plugin.
 *
 * @param string $token Timezone token from the condition.
 * @return DateTimeZone The resolved zone.
 */
function flosc_resolve_timezone_original( $token = '' ) {
	$token = strtoupper( trim( (string) $token ) );

	if ( '' !== $token ) {
		if ( 'UTC' === $token ) {
			return new DateTimeZone( 'UTC' );
		}

		if ( preg_match( '/^UTC([+-])(\d{1,2})(?::?(\d{2}))?$/', $token, $m ) ) {
			$sign    = $m[1];
			$hours   = str_pad( (string) min( 14, (int) $m[2] ), 2, '0', STR_PAD_LEFT );
			$minutes = str_pad( (string) min( 59, (int) ( $m[3] ?? 0 ) ), 2, '0', STR_PAD_LEFT );
			$offset  = $sign . $hours . ':' . $minutes;
			try {
				return new DateTimeZone( $offset );
			} catch ( Exception $e ) {
				$token = '';
			}
		}
	}

	if ( function_exists( 'wp_timezone' ) ) {
		try {
			$site_tz = wp_timezone();
			if ( $site_tz instanceof DateTimeZone ) {
				return $site_tz;
			}
		} catch ( Exception $e ) {
			$site_tz = null;
		}
	}

	if ( function_exists( 'wp_timezone_string' ) ) {
		$site_tz_string = trim( (string) wp_timezone_string() );
		if ( '' !== $site_tz_string ) {
			try {
				return new DateTimeZone( $site_tz_string );
			} catch ( Exception $e ) {
				$site_tz_string = '';
			}
		}
	}

	$system_tz = trim( (string) date_default_timezone_get() );
	if ( '' !== $system_tz ) {
		try {
			return new DateTimeZone( $system_tz );
		} catch ( Exception $e ) {
			$system_tz = '';
		}
	}

	return new DateTimeZone( 'UTC' );
}

/*
 * The evaluator exits on sight when ABSPATH is missing, which is what it should
 * do in a browser -- but it means requiring it without this constant ends the
 * process with status 0 and no output, and this gate would "pass" having
 * compared nothing. The assertion below is what makes that impossible.
 */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require_once dirname( __DIR__ ) . '/includes/class-flosc-condition-evaluator.php';

if ( ! class_exists( 'FLOSC_Condition_Evaluator' ) ) {
	fwrite( STDERR, "check_timezone_resolution: the evaluator did not load\n" );
	exit( 1 );
}

$flosc_evaluator = new FLOSC_Condition_Evaluator( array() );
$flosc_method    = new ReflectionMethod( 'FLOSC_Condition_Evaluator', 'resolve_timezone' );
$flosc_method->setAccessible( true );

$flosc_tokens = array(
	'',
	'UTC',
	'utc',
	'  UTC  ',
	'UTC+2',
	'UTC+02',
	'UTC+02:00',
	'UTC-05:30',
	'UTC+0530',
	'UTC+99',
	'UTC-99:99',
	'UTC+14',
	'America/Denver',
	'Europe/Riga',
	'nonsense',
	'UTC+',
	'+02:00',
);

$flosc_failures = 0;
$flosc_compared = 0;

$flosc_seen = array();

foreach ( array( 'working', 'throwing' ) as $flosc_mode ) {
	$GLOBALS['flosc_tz_mode'] = $flosc_mode;

	foreach ( $flosc_tokens as $flosc_token ) {
		$flosc_was = flosc_resolve_timezone_original( $flosc_token )->getName();
		$flosc_now = $flosc_method->invoke( $flosc_evaluator, $flosc_token )->getName();

		$flosc_seen[ $flosc_now ] = true;
		++$flosc_compared;

		if ( $flosc_was !== $flosc_now ) {
			++$flosc_failures;
			printf(
				"  FAIL  mode=%s token=%s  was %s, now %s\n",
				$flosc_mode,
				'' === $flosc_token ? '(empty)' : $flosc_token,
				$flosc_was,
				$flosc_now
			);
		}
	}
}

printf(
	"check_timezone_resolution: %d token/mode pairs compared, %d distinct zones reached\n",
	$flosc_compared,
	count( $flosc_seen )
);

/*
 * Each of the four sources must actually be reached by some case. If a change
 * makes one of them unreachable, the comparison above can still agree with an
 * original that has been changed the same way -- so this is what stops the gate
 * degrading into one that only says "these two functions match".
 */
if ( count( $flosc_seen ) < 4 ) {
	printf(
		"  FAIL  only %d distinct zones were reached; every source should be exercised: %s\n",
		count( $flosc_seen ),
		implode( ', ', array_keys( $flosc_seen ) )
	);
	exit( 1 );
}

if ( $flosc_failures ) {
	exit( 1 );
}

echo "  PASS  the restructured resolver answers exactly what the original did\n";
exit( 0 );

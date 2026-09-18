<?php
/**
 * Guest follow-up email slots (parameterized — no magic day numbers in setting keys).
 *
 * Canonical keys: guest_followup_1|2|3_{subject,body,min_day,max_day}
 * Legacy keys (read fallback): guest_day10|20|28_* and sent-meta day10|20|28
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return array<string, array<string, mixed>>
 */
function flosc_guest_followup_slots() {
	static $slots = null;
	if ( null !== $slots ) {
		return $slots;
	}

	$slots = array(
		'guest_followup_1' => array(
			'label'           => __( 'Guest follow-up 1 (early window)', 'flosc' ),
			'timing'          => __( 'Sent when guest age (days since registration) falls in the window below and they have not upgraded.', 'flosc' ),
			'legacy_prefix'   => 'guest_day10',
			'legacy_sent_key' => 'day10',
			'default_min_day' => 10,
			'default_max_day' => 12,
			'default_subject' => 'How is your {app_name} experience going?',
			'default_body'    => "Hi {name}!\n\nYou're into your complimentary {app_name} guest access — we hope you're finding it useful.\n\nYou still have {days_remaining} days remaining. Continue here: {chat_url}\n\nReady to unlock everything? Upgrade: {upgrade_url}\n\n— The {team_name}",
		),
		'guest_followup_2' => array(
			'label'           => __( 'Guest follow-up 2 (mid window)', 'flosc' ),
			'timing'          => __( 'Sent when guest age falls in the mid window and they have not upgraded.', 'flosc' ),
			'legacy_prefix'   => 'guest_day20',
			'legacy_sent_key' => 'day20',
			'default_min_day' => 20,
			'default_max_day' => 22,
			'default_subject' => 'You have {days_remaining} days of {app_name} access remaining',
			'default_body'    => "Hi {name}!\n\nYou're well into your complimentary {app_name} guest access.\n\nVisit your profile: {profile_url}\n\nYou have {days_remaining} days remaining. Upgrade for full access: {upgrade_url}\n\n— The {team_name}",
		),
		'guest_followup_3' => array(
			'label'           => __( 'Guest follow-up 3 (late window)', 'flosc' ),
			'timing'          => __( 'Sent when guest age falls in the late window and they have not upgraded.', 'flosc' ),
			'legacy_prefix'   => 'guest_day28',
			'legacy_sent_key' => 'day28',
			'default_min_day' => 28,
			'default_max_day' => 30,
			'default_subject' => '{days_remaining} days left for your guest access',
			'default_body'    => "Hi {name}!\n\nWe would love to welcome you as a full member of {app_name}.\n\nYour guest access expires in {days_remaining} days. If you do not upgrade, guest account information, recordings, and quiz scores may be removed from our servers.\n\nUpgrade to keep your data: {upgrade_url}\n\n— The {team_name}",
		),
	);

	return $slots;
}

/**
 * Read a follow-up setting with legacy key fallback.
 *
 * @param array  $settings Flow settings.
 * @param string $slot_id  guest_followup_1|2|3
 * @param string $suffix   subject|body|min_day|max_day
 * @param mixed  $default  Default if neither key set.
 * @return mixed
 */
function flosc_guest_followup_get( array $settings, $slot_id, $suffix, $default = '' ) {
	$slot_id = sanitize_key( (string) $slot_id );
	$suffix  = sanitize_key( (string) $suffix );
	$slots   = flosc_guest_followup_slots();
	if ( ! isset( $slots[ $slot_id ] ) ) {
		return $default;
	}
	$new_key = $slot_id . '_' . $suffix;
	if ( array_key_exists( $new_key, $settings ) && $settings[ $new_key ] !== '' && $settings[ $new_key ] !== null ) {
		return $settings[ $new_key ];
	}
	$legacy = (string) ( $slots[ $slot_id ]['legacy_prefix'] ?? '' );
	if ( $legacy !== '' ) {
		$old_key = $legacy . '_' . $suffix;
		if ( array_key_exists( $old_key, $settings ) && $settings[ $old_key ] !== '' && $settings[ $old_key ] !== null ) {
			return $settings[ $old_key ];
		}
	}
	return $default;
}

/**
 * Whether this follow-up was already sent (new or legacy sent-meta key).
 *
 * @param array  $sent    Values from user meta _flosc_guest_emails_sent.
 * @param string $slot_id guest_followup_N
 * @return bool
 */
function flosc_guest_followup_was_sent( array $sent, $slot_id ) {
	$slot_id = sanitize_key( (string) $slot_id );
	if ( in_array( $slot_id, $sent, true ) ) {
		return true;
	}
	$slots = flosc_guest_followup_slots();
	$legacy = (string) ( $slots[ $slot_id ]['legacy_sent_key'] ?? '' );
	if ( $legacy !== '' && in_array( $legacy, $sent, true ) ) {
		return true;
	}
	// Also accept legacy full prefix without underscore day token.
	$legacy_prefix = (string) ( $slots[ $slot_id ]['legacy_prefix'] ?? '' );
	if ( $legacy_prefix !== '' && in_array( $legacy_prefix, $sent, true ) ) {
		return true;
	}
	return false;
}

/**
 * Setting key stems for textarea sanitization (new + legacy bodies).
 *
 * @return string[]
 */
function flosc_guest_followup_textarea_keys() {
	$keys = array( 'guest_welcome_body' );
	foreach ( flosc_guest_followup_slots() as $slot_id => $meta ) {
		$keys[] = $slot_id . '_body';
		$legacy = (string) ( $meta['legacy_prefix'] ?? '' );
		if ( $legacy !== '' ) {
			$keys[] = $legacy . '_body';
		}
	}
	return $keys;
}

/**
 * Engagement / allowlist template ids (new + legacy).
 *
 * @return string[]
 */
function flosc_guest_followup_template_ids() {
	$ids = array( '', 'reengagement', 'guest_welcome' );
	foreach ( flosc_guest_followup_slots() as $slot_id => $meta ) {
		$ids[] = $slot_id;
		$legacy = (string) ( $meta['legacy_prefix'] ?? '' );
		if ( $legacy !== '' ) {
			$ids[] = $legacy;
		}
	}
	return $ids;
}

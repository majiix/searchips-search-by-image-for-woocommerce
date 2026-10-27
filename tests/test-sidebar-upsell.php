<?php
/**
 * Test for Pro Upgrade Sidebar Card rendering.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../../../../' );
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL );
	}
}

require_once __DIR__ . '/../includes/class-tsbifw-admin.php';

$reflector = new ReflectionClass( 'TSBIFW_Admin' );
$admin     = $reflector->newInstanceWithoutConstructor();

ob_start();
$admin->render_sidebar_pro_upsell_card();
$output = ob_get_clean();

// 1. Verify card wrapper and header
assert( false !== strpos( $output, 'tsbifw-pro-upsell-card' ), 'Card wrapper class missing' );
assert( false !== strpos( $output, 'Upgrade to Pro' ), 'Header title missing' );
assert( false !== strpos( $output, 'PRO ADDON' ), 'PRO ADDON badge pill missing' );

// 2. Verify buy link
assert( false !== strpos( $output, 'https://violo.ir/?p=707' ), 'Buy link URL missing or incorrect' );
assert( false !== strpos( $output, 'target="_blank"' ), 'target="_blank" missing' );
assert( false !== strpos( $output, 'rel="noopener noreferrer"' ), 'rel="noopener noreferrer" missing' );

// 3. Verify genuine Pro-only features
assert( false !== strpos( $output, 'Visual Search Analytics:' ), 'Feature 1 missing' );
assert( false !== strpos( $output, 'Variation Images Indexing:' ), 'Feature 2 missing' );
assert( false !== strpos( $output, 'Smart Image Hashing (MD5):' ), 'Feature 3 missing' );
assert( false !== strpos( $output, 'Direct OpenAI &amp; Gemini:' ) || false !== strpos( $output, 'Direct OpenAI & Gemini:' ), 'Feature 4 missing' );
assert( false !== strpos( $output, 'Mobile Camera Instant Capture:' ), 'Feature 5 missing' );
assert( false !== strpos( $output, 'Similarity Score Boost:' ), 'Feature 6 missing' );

// 4. Verify that features existing in Free are excluded from the Pro card
assert( false === strpos( $output, '5 Futuristic Scanner FX:' ), 'Feature 5 Futuristic Scanner FX exists in Free and must NOT be in the Pro card' );

echo "ALL TESTS PASSED: render_sidebar_pro_upsell_card renders verified Pro-only features with no Free duplicates.\n";

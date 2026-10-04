// German posts are written in German but published at the root as en-US, and GTranslate serves a copy at /de/.
// Point the root copy's canonical at the /de/ copy so Google indexes one German URL per post.
function ma_german_post_ids() {
	return array( 6881, 6887, 6893, 6899, 6905, 6909, 6913, 7331, 7412, 7413, 7414, 7415, 7416, 7439, 7440, 7574, 7575, 7588, 7589, 7601, 7602, 7603, 7604, 7675, 7676, 7677, 7678, 7679, 7680, 7681, 7682, 7683, 7684, 7685, 7686, 7729, 7730 ); // German-language posts (37), checked 2026-10-04.
}

function ma_is_german_post() {
	return is_singular( 'post' ) && in_array( get_queried_object_id(), ma_german_post_ids(), true );
}

// The canonical differs between a visitor's request and GTranslate's fetch for the /de/ copy,
// so these pages must not be served from the page cache.
add_action( 'wp', function () {
	if ( ma_is_german_post() && ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
} );

add_filter( 'wpseo_canonical', function ( $canonical ) {
	if ( ! ma_is_german_post() ) {
		return $canonical;
	}
	// When GTranslate fetches the page to build the /de/ copy, keep the default canonical:
	// GTranslate adds the /de/ prefix to it itself.
	if ( ! empty( $_SERVER['HTTP_X_GT_LANG'] ) ) {
		return $canonical;
	}
	return home_url( '/de/' . get_post_field( 'post_name', get_queried_object_id() ) . '/' );
} );

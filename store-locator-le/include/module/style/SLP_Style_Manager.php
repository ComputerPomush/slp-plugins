<?php
defined( 'SLPLUS_VERSION' ) || exit;

/**
 * The "Locator Style" manager, handles implementing and changing SLP UI "themes".
 *
 * Cached styles are cached by the post ID.
 *
 */
class SLP_Style_Manager extends SLPlus_BaseClass_Object {
	const TRANSIENT_BASE = 'slp_style_';
	const REST_ENDPOINT = 'wp-json/wp/v2/slp_style_gallery';

	/* @var   array $current_style the meta for the current style being applied/in use */
	private $current_style;

	/* @var int $page_size the size of the admin UI "page" when rendering the style list. */
	public $page_size = 9;

	/**
	 * Apply the new style.
	 *
	 * @param $style_id
	 * @param $only_for_property    when passed, only set this property from the entire style setup.
	 */
	public function apply_style( $style_id, $only_for_property = null ) {
		$this->set_active_style( $style_id );

		if ( empty( $this->current_style ) ) {
			return;
		}
		foreach ( $this->current_style->custom_fields as $option => $data_array ) {
			if ( ! is_null( $only_for_property ) && ( $option !== $only_for_property ) ) {
				continue;
			}
			if ( $this->slplus->SmartOptions->exists( $option ) ) {
				if ( $data_array[0] === '&nbsp;' ) {
					$data_array[0] = '';
				}
				$this->slplus->SmartOptions->set( $option, $data_array[0] );
			}
		}

		if ( property_exists( $this->current_style, 'css' ) ) {
			if ( is_array( $this->current_style->css ) ) {
				$this->slplus->SmartOptions->set( 'active_style_css', $this->current_style->css[0] );
			} elseif ( property_exists( $this->current_style->css, 'content' ) ) {
				$this->slplus->SmartOptions->set( 'active_style_css', $this->current_style->css->content );
			} else {
				$this->slplus->SmartOptions->set( 'active_style_css', $this->slplus->SmartOptions->active_style_css->default );
			}
		}

		$this->slplus->WPOption_Manager->update_wp_option( 'js' );
		$this->slplus->WPOption_Manager->update_wp_option( 'nojs' );
	}

	/**
	 * Write the locator style to the cache (WP transient)
	 *
	 * @param $post
	 */
	public function cache_style( $post ) {
		set_transient( SLP_Style_Manager::TRANSIENT_BASE . $post->id, $post, WEEK_IN_SECONDS );
	}

	/**
	 * Change the active style.
	 *
	 * @param int $old_id
	 * @param int $new_id
	 */
	public function change_style( $old_id, $new_id ) {
		// TODO: add a 'save style' to allow users to revert
		$this->apply_style( $new_id );
	}

	/**
	 * Fetch the style (or styles) from the style server.
	 *
	 * @param int|null $style_id
	 *
	 * @return WP_Error|string
	 */
	public function fetch_style( $style_id = null ) {
		$style_selector = is_null( $style_id ) ? '' : sprintf( '/%d', $style_id );
		$request_params = '?orderby=title&order=asc&per_page=' . $this->page_size;

		return SLP_Service::get_instance()->get_styles( $style_selector, $request_params );
	}

	/**
	 * Set the active style including its meta as $this->current_style
	 *
	 * @param int $style_id
	 */
	private function set_active_style( $style_id ) {
		if ( ! empty( $this->current_style ) && ( $this->current_style->id === (int) $style_id ) ) {
			return;
		}

		// first check transient
		$this->current_style = get_transient( SLP_Style_Manager::TRANSIENT_BASE . $style_id );

		// if not valid get from style server
		if ( empty( $this->current_style ) || ! $this->style_matches_slug( $this->current_style->slug ) ) {
			$post = $this->fetch_style( $style_id );
			if ( is_wp_error( $post ) ) {
				$this->current_style = null;
			} else {
				$this->current_style = $post;
			}
		}
	}

	/**
	 * Does the style slug match our active setting?
	 *
	 * @param $slug
	 *
	 * @return bool
	 */
	private function style_matches_slug( $slug ) {
		return ( $slug === $this->slplus->SmartOptions->style->value );
	}
}

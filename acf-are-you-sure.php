<?php
/**
 * ACFAreYouSure
 * An plugin that shows available syncs on acf groups
 * @package             acf-are-you-sure
 * @author              Joeri Abbo <joeriabbo@hotmail.com>
 * @link                https://www.linkedin.com/in/joeri-abbo-43a457144/
 * @copyright           2021 Joeri Abbo
 * @wordpress-plugin
 * Plugin Name:        ACF Are You Sure
 * Plugin URI:            https://github.com/Joeri-Abbo/ACF-Are-You-Sure
 * Description:        An plugin that shows available syncs on acf groups
 * Version:            1.0.0
 * Author URI:            https://www.linkedin.com/in/joeri-abbo-43a457144/
 * Author:                Joeri Abbo
 **/
new ACFAreYouSure();

class ACFAreYouSure {
	const TEXT_DOMAIN = 'acf-are-you-sure';
	private ?array $sync = null;

	public function __construct() {
		add_action( 'admin_notices', [ $this, 'showNotice' ], 100, 1 );
		add_action( 'admin_footer', [ $this, 'addUpdateConfirmation' ] );
	}

	public function addUpdateConfirmation() {
		$group = $this->getGroup();
		if ( is_null( $group ) ) {
			return;
		}
		$this->setup_sync();


		if ( ! $this->shouldNotice( $group ) ) {
			return;
		}
		$confirmation_message = __( "Are you sure you want to update this ACF group? A sync is open!", self::TEXT_DOMAIN );
		?>

		<script type="text/javascript">
			let publish = document.getElementById("publish");
			console.log(publish);
			if (publish !== null) {
				publish.onclick = function () {
					return confirm("<?= $confirmation_message;?>");
				};
			}
		</script>
		<?php
	}

	/**
	 * Show the notice
	 */
	public function showNotice() {

		$group = $this->getGroup();
		if ( is_null( $group ) ) {
			return;
		}

		$this->setup_sync();

		$notice = $this->renderNotice(
			$group
		);
		if ( ! empty( $notice ) ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p>
					<?= $notice; ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Get the group
	 * @return array|null
	 */
	public function getGroup(): ?array {
		global $post;

		if ( ! current_user_can( 'administrator' ) ) {
			return null;
		}

		if ( ! $post instanceof \WP_Post ) {
			return null;
		}
		if ( $post->post_type !== 'acf-field-group' ) {
			return null;
		}

		return $this->getGroupByKey( $post->post_name );
	}

	/**
	 * Get group by key
	 *
	 * @param string $key
	 *
	 * @return array|null
	 */
	public function getGroupByKey( string $key ): ?array {

		$groups = acf_get_field_groups();
		if ( ! is_array( $groups ) || empty( $groups ) ) {
			return null;
		}

		foreach ( $groups as $group ) {
			if ( $key === $group['key'] ) {
				return $group;
			}
		}

		return null;
	}

	/**
	 * Render the notice
	 *
	 * @param $field_group array|null
	 *
	 * @return string|null
	 */
	public function renderNotice( ?array $field_group ): ?string {

		if ( $this->shouldNotice( $field_group ) ) {
			$url = $this->get_admin_url( '&acfsync=' . $field_group['key'] . '&_wpnonce=' . wp_create_nonce( 'bulk-posts' ) );

			return sprintf( '<strong><a href="%s">%s</a></strong>', $url, __( 'Sync available for this ACF Group', self::TEXT_DOMAIN ) );

		}

		return null;
	}

	/**
	 * Should we interfere in this group?
	 *
	 * @param array|null $field_group
	 *
	 * @return bool
	 */
	public function shouldNotice( ?array $field_group ): bool {
		if ( ! empty( $field_group['key'] ) ) {
			if ( ! empty( $this->sync[ $field_group['key'] ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sets up the field groups ready for sync.
	 *
	 * @param void
	 *
	 * @return    void
	 * @since    1.0.0
	 */
	private function setup_sync() {
		if ( is_null( $this->sync ) ) {
			$files = acf_get_local_json_files();
			// Review local json field groups.
			if ( ! empty( $files ) ) {

				// Get all groups in a single cached query to check if sync is available.
				$all_field_groups = acf_get_field_groups();
				foreach ( $all_field_groups as $field_group ) {
					$modified = null;
					if ( key_exists( $field_group["key"], $files ) ) {
						$file = $files[ $field_group["key"] ];
						if ( file_exists( $file ) ) {
							$modified = json_decode( file_get_contents( $file ) )->modified;
						}
					}

					if ( $modified && $modified > get_post_modified_time( 'U', true, $field_group['ID'] ) ) {
						$this->sync[ $field_group['key'] ] = $field_group;
					}
				}
			}
		}
	}

	/**
	 * Returns the Field Groups admin URL.
	 *
	 * @param string $params Extra URL params.
	 *
	 * @return    string
	 */
	private function get_admin_url(
		string $params = ''
	): string {
		return admin_url( "edit.php?post_type=acf-field-group{$params}" );
	}
}

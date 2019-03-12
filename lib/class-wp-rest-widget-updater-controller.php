<?php
/**
 * Start: Include for phase 2
 * Widget Updater REST API: WP_REST_Widget_Updater_Controller class
 *
 * @package gutenberg
 * @since 5.2.0
 */

/**
 * Controller which provides REST endpoint for updating a widget.
 *
 * @since 5.2.0
 *
 * @see WP_REST_Controller
 */
class WP_REST_Widget_Updater_Controller extends WP_REST_Controller {

	/**
	 * Constructs the controller.
	 *
	 * @access public
	 */
	public function __construct() {
		$this->namespace = 'wp/v2';
		$this->rest_base = 'widgets';
	}

	/**
	 * Registers the necessary REST API route.
	 *
	 * @access public
	 */
	public function register_routes() {
			register_rest_route(
				$this->namespace,
				'/' . $this->rest_base . '/(?P<identifier>[\w-_]+)/',
				array(
					'args' => array(
						'identifier' => array(
							'description' => __( 'Class name of the widget.', 'gutenberg' ),
							'type'        => 'string',
						),
					),
					array(
						'methods'             => WP_REST_Server::EDITABLE,
						'permission_callback' => array( $this, 'compute_new_widget_permissions_check' ),
						'callback'            => array( $this, 'compute_new_widget' ),
					),
				)
			);
	}

	/**
	 * Checks if the user has permissions to make the request.
	 *
	 * @since 5.2.0
	 * @access public
	 *
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function compute_new_widget_permissions_check() {
		// Verify if the current user has edit_theme_options capability.
		// This capability is required to access the widgets screen.
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error(
				'widgets_cannot_access',
				__( 'Sorry, you are not allowed to access widgets on this site.', 'gutenberg' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}
		return true;
	}

	/**
	 * Checks if the widget being referenced is valid.
	 *
	 * @since 5.2.0
	 * @param string  $identifier         Widget id for callback widgets or widget class name for class widgets.
	 * @param boolean $is_callback_widget If true the widget is a back widget if false the widget is a class widget.
	 *
	 * @return boolean True if widget being referenced exists and false otherwise.
	 */
	private function isValidWidget( $identifier, $is_callback_widget ) {
		if ( null === $identifier ) {
			return false;
		}
		if ( $is_callback_widget ) {
			global $wp_registered_widget_controls;
			return isset( $wp_registered_widget_controls[ $identifier ]['callback'] ) &&
			is_callable( $wp_registered_widget_controls[ $identifier ]['callback'] );
		}
		global $wp_widget_factory;
		return isset( $wp_widget_factory->widgets[ $identifier ] ) &&
			( $wp_widget_factory->widgets[ $identifier ] instanceof WP_Widget );
	}

	/**
	 * Computes an array with instance changes cleaned of widget specific prefixes and sufixes.
	 *
	 * @since 5.2.0
	 * @param string $id_base          Widget ID Base.
	 * @param string $id               Widget instance identifier.
	 * @param array  $instance_changes Array with the form values being being changed.
	 *
	 * @return array An array based on $instance_changes but whose keys have the widget specific sufixes and prefixes removed.
	 */
	private function parseInstanceChanges( $id_base, $id, $instance_changes ) {
		$instance_changes_parsed = array();
		$start_position          = strlen( 'widget-' . $id_base . '[' . $id . '][' );
		foreach ( $instance_changes as $key => $value ) {
			$key_parsed                             = substr( $key, $start_position, -1 );
			$instance_changes_parsed[ $key_parsed ] = $value;
		}
		return $instance_changes_parsed;
	}

	/**
	 * Returns the bew callback widget form.
	 *
	 * @since 5.2.0
	 * @param string $identifier       Widget id for callback widgets or widget class name for class widgets.
	 * @param array  $instance_changes Array with the form values being being changed.
	 *
	 * @return WP_REST_Response Response object.
	 */
	private function compute_new_widget_handle_callback_widgets( $identifier, $instance_changes ) {
		global $wp_registered_widget_controls;
		$control = $wp_registered_widget_controls[ $identifier ];
		$_POST   = array_merge( $_POST, $instance_changes );
		ob_start();
		call_user_func_array( $control['callback'], $control['params'] );
		$form = ob_get_clean();
		return rest_ensure_response(
			array(
				'instance' => array(),
				'form'     => $form,
				'id_base'  => $identifier,
				'id'       => $identifier,
			)
		);
	}

	/**
	 * Returns the new class widget instance and the form that represents it.
	 *
	 * @since 5.2.0
	 * @access public
	 *
	 * @param string $identifier       Widget id for callback widgets or widget class name for class widgets.
	 * @param array  $instance         Previous widget instance.
	 * @param array  $instance_changes Array with the form values being being changed.
	 * @param string $id_to_use        Identifier of the specific widget instance.
	 * @return WP_REST_Response Response object on success, or WP_Error object on failure.
	 */
	private function compute_new_widget_handle_class_widgets( $identifier, $instance, $instance_changes, $id_to_use ) {
		if ( null === $instance ) {
			$instance = array();
		}
		if ( null === $id_to_use ) {
			$id_to_use = -1;
		}

		global $wp_widget_factory;
		$widget_obj = $wp_widget_factory->widgets[ $identifier ];

		$widget_obj->_set( $id_to_use );
		$id_base = $widget_obj->id_base;
		$id      = $widget_obj->id;
		ob_start();

		if ( null !== $instance_changes ) {
			$instance_changes = $this->parseInstanceChanges( $id_base, $id_to_use, $instance_changes );
			$old_instance     = $instance;
			$instance         = $widget_obj->update( $instance_changes, $old_instance );

			/**
			 * Filters a widget's settings before saving.
			 *
			 * Returning false will effectively short-circuit the widget's ability
			 * to update settings. The old setting will be returned.
			 *
			 * @since 5.2.0
			 *
			 * @param array     $instance         The current widget instance's settings.
			 * @param array     $instance_changes Array of new widget settings.
			 * @param array     $old_instance     Array of old widget settings.
			 * @param WP_Widget $widget_ob        The widget instance.
			 */
			$instance = apply_filters( 'widget_update_callback', $instance, $instance_changes, $old_instance, $widget_obj );
			if ( false === $instance ) {
				$instance = $old_instance;
			}
		}

		$instance = apply_filters( 'widget_form_callback', $instance, $widget_obj );

		$return = null;
		if ( false !== $instance ) {
			$return = $widget_obj->form( $instance );

			/**
			 * Fires at the end of the widget control form.
			 *
			 * Use this hook to add extra fields to the widget form. The hook
			 * is only fired if the value passed to the 'widget_form_callback'
			 * hook is not false.
			 *
			 * Note: If the widget has no form, the text echoed from the default
			 * form method can be hidden using CSS.
			 *
			 * @since 5.2.0
			 *
			 * @param WP_Widget $widget_obj     The widget instance (passed by reference).
			 * @param null      $return   Return null if new fields are added.
			 * @param array     $instance An array of the widget's settings.
			 */
			do_action_ref_array( 'in_widget_form', array( &$widget_obj, &$return, $instance ) );
		}
		$form = ob_get_clean();

		return rest_ensure_response(
			array(
				'instance' => $instance,
				'form'     => $form,
				'id_base'  => $id_base,
				'id'       => $id,
			)
		);
	}

	/**
	 * Returns the new widget instance and the form that represents it.
	 *
	 * @since 5.2.0
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function compute_new_widget( $request ) {
		$identifier         = $request->get_param( 'identifier' );
		$is_callback_widget = $request->get_param( 'is_callback_widget' );
		if ( null === $is_callback_widget ) {
			$is_callback_widget = false;
		}

		if ( ! $this->isValidWidget( $identifier, $is_callback_widget ) ) {
			return new WP_Error(
				'widget_invalid',
				__( 'Invalid widget.', 'gutenberg' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( $is_callback_widget ) {
			return $this->compute_new_widget_handle_callback_widgets(
				$identifier,
				$request->get_param( 'instance_changes' )
			);
		}
		return $this->compute_new_widget_handle_class_widgets(
			$identifier,
			$request->get_param( 'instance' ),
			$request->get_param( 'instance_changes' ),
			$request->get_param( 'id_to_use' )
		);
	}
}
/**
 * End: Include for phase 2
 */
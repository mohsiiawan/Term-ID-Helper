<?php
/**
 * Plugin Name: Term ID Helper (Parent + Children Lists)
 * Description: Utility page in admin to get "parent + all children IDs" for a taxonomy, e.g. 28,33,40 for JetEngine visibility conditions.
 * Author: Beyondweb
 * Version: 1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Term_ID_Helper_Plugin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
    }

    public function add_menu_page() {
        add_management_page(
            __( 'Term ID Helper', 'term-id-helper' ),
            __( 'Term ID Helper', 'term-id-helper' ),
            'manage_options',
            'term-id-helper',
            array( $this, 'render_admin_page' )
        );
    }

    /**
     * Recursively collect all descendant IDs of a term.
     */
    private function get_descendant_ids( $parent_id, $children_map ) {
        $ids = array();

        if ( isset( $children_map[ $parent_id ] ) ) {
            foreach ( $children_map[ $parent_id ] as $child_id ) {
                $ids[] = $child_id;
                $ids   = array_merge( $ids, $this->get_descendant_ids( $child_id, $children_map ) );
            }
        }

        return $ids;
    }

    public function render_admin_page() {

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $selected_post_type = isset( $_POST['term_id_helper_post_type'] ) ? sanitize_text_field( $_POST['term_id_helper_post_type'] ) : '';
        $selected_taxonomy  = isset( $_POST['term_id_helper_taxonomy'] ) ? sanitize_text_field( $_POST['term_id_helper_taxonomy'] ) : '';

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Term ID Helper', 'term-id-helper' ); ?></h1>
            <p>Choose a post type and one of its taxonomies to generate a list of parent term IDs with all their child IDs.</p>

            <form method="post">
                <?php wp_nonce_field( 'term_id_helper_action', 'term_id_helper_nonce' ); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="term_id_helper_post_type">Post Type</label></th>
                        <td>
                            <select name="term_id_helper_post_type" id="term_id_helper_post_type">
                                <option value=""><?php esc_html_e( 'Select post type', 'term-id-helper' ); ?></option>
                                <?php
                                $post_types = get_post_types( array( 'public' => true ), 'objects' );
                                foreach ( $post_types as $pt ) :
                                    ?>
                                    <option value="<?php echo esc_attr( $pt->name ); ?>" <?php selected( $selected_post_type, $pt->name ); ?>>
                                        <?php echo esc_html( $pt->labels->singular_name . ' (' . $pt->name . ')' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="term_id_helper_taxonomy">Taxonomy</label></th>
                        <td>
                            <select name="term_id_helper_taxonomy" id="term_id_helper_taxonomy">
                                <option value=""><?php esc_html_e( 'Select taxonomy', 'term-id-helper' ); ?></option>
                                <?php
                                if ( $selected_post_type ) {
                                    $taxonomies = get_object_taxonomies( $selected_post_type, 'objects' );
                                } else {
                                    $taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
                                }

                                foreach ( $taxonomies as $tax ) :
                                    ?>
                                    <option value="<?php echo esc_attr( $tax->name ); ?>" <?php selected( $selected_taxonomy, $tax->name ); ?>>
                                        <?php echo esc_html( $tax->labels->singular_name . ' (' . $tax->name . ')' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Generate Term ID Lists', 'term-id-helper' ) ); ?>
            </form>

            <?php
            // If form submitted and taxonomy chosen, output the data.
            if (
                isset( $_POST['term_id_helper_nonce'] ) &&
                wp_verify_nonce( $_POST['term_id_helper_nonce'], 'term_id_helper_action' ) &&
                ! empty( $selected_taxonomy )
            ) {

                $terms = get_terms( array(
                    'taxonomy'   => $selected_taxonomy,
                    'hide_empty' => false,
                ) );

                if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {

                    // Build map of parent => children.
                    $children_map = array();
                    foreach ( $terms as $term ) {
                        $parent_id = (int) $term->parent;
                        if ( ! isset( $children_map[ $parent_id ] ) ) {
                            $children_map[ $parent_id ] = array();
                        }
                        $children_map[ $parent_id ][] = (int) $term->term_id;
                    }

                    echo '<h2>' . sprintf(
                        esc_html__( 'Results for taxonomy: %s', 'term-id-helper' ),
                        esc_html( $selected_taxonomy )
                    ) . '</h2>';

                    echo '<p>Copy the "IDs string" and paste it into your JetEngine visibility condition (In the list → numeric).</p>';

                    echo '<table class="widefat striped" style="max-width:900px;margin-top:10px;">';
                    echo '<thead><tr>';
                    echo '<th>' . esc_html__( 'Parent Term', 'term-id-helper' ) . '</th>';
                    echo '<th>' . esc_html__( 'Parent ID', 'term-id-helper' ) . '</th>';
                    echo '<th>' . esc_html__( 'Child IDs', 'term-id-helper' ) . '</th>';
                    echo '<th>' . esc_html__( 'IDs string (parent + children)', 'term-id-helper' ) . '</th>';
                    echo '</tr></thead><tbody>';

                    foreach ( $terms as $term ) {

                        // Only treat top-level terms as "parents".
                        if ( 0 !== (int) $term->parent ) {
                            continue;
                        }

                        $parent_id   = (int) $term->term_id;
                        $descendants = $this->get_descendant_ids( $parent_id, $children_map );

                        $all_ids     = array_merge( array( $parent_id ), $descendants );
                        $all_ids_str = implode( ',', $all_ids );

                        $child_ids_str = empty( $descendants ) ? '-' : implode( ', ', $descendants );

                        echo '<tr>';
                        echo '<td>' . esc_html( $term->name ) . '</td>';
                        echo '<td>' . esc_html( $parent_id ) . '</td>';
                        echo '<td>' . esc_html( $child_ids_str ) . '</td>';
                        echo '<td><code>' . esc_html( $all_ids_str ) . '</code></td>';
                        echo '</tr>';
                    }

                    echo '</tbody></table>';
                } else {
                    echo '<p>' . esc_html__( 'No terms found for this taxonomy.', 'term-id-helper' ) . '</p>';
                }
            }
            ?>
        </div>
        <?php
    }
}

new Term_ID_Helper_Plugin();

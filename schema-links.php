<?php
/**
 * Plugin Name: Schema Links Plugin
 * Plugin URI:  https://bloggerpi.com
 * Description: Allows marking links as relatedLink or significantLink and outputs JSON-LD on the front-end (1.0.0). Add editor in category via ACF and pages support (1.0.2). Add setting page (1.0.3).
 * Version:     1.0.3
 * Author:      Bloggerpi Digital (Rijal Fahmi Mohamadi)
 * Author URI:  https://bloggerpi.com
 * License:     GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Prevent direct file access
}

/* ---------------------------------------------------------------------------
 * 1. PLUGIN SETTINGS
 *    - Allows user to set an ACF field name & toggle ACF/Yoast integration
 * --------------------------------------------------------------------------- */

/**
 * Register settings.
 */
function slp_register_settings() {
    register_setting( 'slp_settings_group', 'slp_settings', [
        'type'              => 'array',
        'sanitize_callback' => 'slp_sanitize_settings',
        'default'           => [
            'acf_integration'   => 0,
            'acf_field_name'    => '',
            'yoast_integration' => 0
        ],
    ] );

    add_settings_section(
        'slp_main_section',
        'Schema Links Plugin Settings',
        'slp_settings_section_text',
        'slp-settings-page'
    );

    // ACF Integration Toggle
    add_settings_field(
        'slp_acf_integration',
        'Enable ACF Integration',
        'slp_acf_integration_field_cb',
        'slp-settings-page',
        'slp_main_section'
    );

    // ACF Field Name
    add_settings_field(
        'slp_acf_field_name',
        'ACF Field Name',
        'slp_acf_field_name_field_cb',
        'slp-settings-page',
        'slp_main_section'
    );

    // Yoast Integration Toggle
    add_settings_field(
        'slp_yoast_integration',
        'Enable Yoast Integration',
        'slp_yoast_integration_field_cb',
        'slp-settings-page',
        'slp_main_section'
    );
}
add_action( 'admin_init', 'slp_register_settings' );

function slp_settings_section_text() {
    echo '<p>Configure how the Schema Links Plugin behaves.</p>';
}

/** Field Callbacks **/

function slp_acf_integration_field_cb() {
    $options = get_option( 'slp_settings' );
    $checked = ! empty( $options['acf_integration'] ) ? 'checked' : '';
    echo '<input type="checkbox" name="slp_settings[acf_integration]" value="1" ' . $checked . ' />';
    echo '<label for="slp_acf_integration"> Check to enable scanning category ACF WYSIWYG fields</label>';
}

function slp_acf_field_name_field_cb() {
    $options = get_option( 'slp_settings' );
    $value   = isset( $options['acf_field_name'] ) ? esc_attr( $options['acf_field_name'] ) : '';
    echo '<input type="text" name="slp_settings[acf_field_name]" value="' . $value . '" placeholder="e.g. category_editor_field" />';
    echo '<p class="description">Enter the ACF field name used for category WYSIWYG. Only used if ACF Integration is enabled.</p>';
}

function slp_yoast_integration_field_cb() {
    $options = get_option( 'slp_settings' );
    $checked = ! empty( $options['yoast_integration'] ) ? 'checked' : '';
    echo '<input type="checkbox" name="slp_settings[yoast_integration]" value="1" ' . $checked . ' />';
    echo '<label for="slp_yoast_integration"> Check to merge into Yoast WebPage/CollectionPage instead of outputting a separate schema</label>';
}

/**
 * Sanitize settings - ensures values are booleans/strings.
 */
function slp_sanitize_settings( $input ) {
    $output = [
        'acf_integration'   => ! empty( $input['acf_integration'] ) ? 1 : 0,
        'acf_field_name'    => isset( $input['acf_field_name'] ) ? sanitize_text_field( $input['acf_field_name'] ) : '',
        'yoast_integration' => ! empty( $input['yoast_integration'] ) ? 1 : 0
    ];
    return $output;
}

/**
 * Add the settings page to WP Admin menu.
 */
function slp_add_options_page() {
    add_options_page(
        'Schema Links Plugin Settings',
        'Schema Links Plugin',
        'manage_options',
        'slp-settings-page',
        'slp_render_options_page'
    );
}
add_action( 'admin_menu', 'slp_add_options_page' );

function slp_render_options_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1>Schema Links Plugin Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'slp_settings_group' );
            do_settings_sections( 'slp-settings-page' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/* ---------------------------------------------------------------------------
 * 2. POST/PAGE META BOX FOR CLASSIFYING LINKS
 * --------------------------------------------------------------------------- */
function slp_add_schema_meta_box() {
    foreach ( ['post','page'] as $post_type ) {
        add_meta_box(
            'slp_schema_links',
            'Schema Links',
            'slp_render_schema_meta_box',
            $post_type,
            'side',
            'default'
        );
    }
}
add_action( 'add_meta_boxes', 'slp_add_schema_meta_box' );

function slp_render_schema_meta_box( $post ) {
    $saved_links = get_post_meta( $post->ID, '_slp_schema_links', true );
    if ( ! is_array( $saved_links ) ) {
        $saved_links = [];
    }

    $content = get_post_field( 'post_content', $post->ID );
    preg_match_all( '/<a\s+(?:[^>]*?\s+)?href=([\'"])(.*?)\1/i', $content, $matches );
    $all_links = isset( $matches[2] ) ? array_unique( $matches[2] ) : [];

    if ( empty( $all_links ) ) {
        echo '<p>No links found in this content.</p>';
        return;
    }

    echo '<p>Select whether each link is relatedLink or significantLink:</p>';
    foreach ( $all_links as $link_url ) {
        $link_type = isset( $saved_links[ $link_url ] ) ? $saved_links[ $link_url ] : '';
        ?>
        <div style="margin-bottom: 8px;">
            <strong><?php echo esc_html( $link_url ); ?></strong><br/>

            <label>
                <input type="radio" name="slp_schema_links[<?php echo esc_attr( $link_url ); ?>]" value="relatedLink"
                       <?php checked( $link_type, 'relatedLink' ); ?> />
                relatedLink
            </label>
            &nbsp;&nbsp;

            <label>
                <input type="radio" name="slp_schema_links[<?php echo esc_attr( $link_url ); ?>]" value="significantLink"
                       <?php checked( $link_type, 'significantLink' ); ?> />
                significantLink
            </label>
            &nbsp;&nbsp;

            <label>
                <input type="radio" name="slp_schema_links[<?php echo esc_attr( $link_url ); ?>]" value=""
                       <?php checked( $link_type, '' ); ?> />
                None
            </label>
        </div>
        <?php
    }

    wp_nonce_field( 'slp_save_schema_links', 'slp_schema_links_nonce' );
}

function slp_save_post( $post_id ) {
    if ( ! isset( $_POST['slp_schema_links_nonce'] ) ||
         ! wp_verify_nonce( $_POST['slp_schema_links_nonce'], 'slp_save_schema_links' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( isset( $_POST['slp_schema_links'] ) && is_array( $_POST['slp_schema_links'] ) ) {
        $links_data = array_map( 'sanitize_text_field', $_POST['slp_schema_links'] );
        update_post_meta( $post_id, '_slp_schema_links', $links_data );
    } else {
        delete_post_meta( $post_id, '_slp_schema_links' );
    }
}
add_action( 'save_post', 'slp_save_post' );

/* ---------------------------------------------------------------------------
 * 3. CATEGORY + ACF INTEGRATION (OPTIONAL)
 * --------------------------------------------------------------------------- */
function slp_maybe_init_category_acf_hooks() {
    $opts = get_option( 'slp_settings' );
    // Only proceed if user enabled acf_integration & ACF is actually installed.
    if ( empty( $opts['acf_integration'] ) ) {
        return;
    }
    if ( ! function_exists( 'get_field' ) ) {
        return;
    }

    add_action( 'edit_category_form_fields', 'slp_edit_category_form_fields' );
    add_action( 'edited_category', 'slp_save_category_links' );
}
add_action( 'admin_init', 'slp_maybe_init_category_acf_hooks' );

function slp_edit_category_form_fields( $term ) {
    $opts       = get_option( 'slp_settings' );
    $field_name = ! empty( $opts['acf_field_name'] ) ? $opts['acf_field_name'] : '';
    if ( ! $field_name ) {
        echo '<tr class="form-field"><th scope="row">Schema Links</th><td>Please set an ACF field name in the plugin settings.</td></tr>';
        return;
    }

    // Attempt to get the content from the configured ACF field
    $content = get_field( $field_name, 'category_' . $term->term_id );
    if ( empty( $content ) ) {
        echo '<tr class="form-field"><th scope="row"><label>Schema Links</label></th><td>No content found in ACF field: <strong>' . esc_html( $field_name ) . '</strong></td></tr>';
        return;
    }

    preg_match_all( '/<a\s+(?:[^>]*?\s+)?href=([\'"])(.*?)\1/i', $content, $matches );
    $all_links = isset( $matches[2] ) ? array_unique( $matches[2] ) : [];

    $saved_links = get_term_meta( $term->term_id, '_slp_schema_links', true );
    if ( ! is_array( $saved_links ) ) {
        $saved_links = [];
    }

    echo '<tr class="form-field">';
    echo '  <th scope="row"><label>Schema Links</label></th>';
    echo '  <td>';

    if ( empty( $all_links ) ) {
        echo '<p>No links found in this ACF field content.</p>';
    } else {
        echo '<p>Select whether each link is relatedLink or significantLink:</p>';
        foreach ( $all_links as $link_url ) {
            $link_type = isset( $saved_links[ $link_url ] ) ? $saved_links[ $link_url ] : '';
            ?>
            <div style="margin-bottom: 8px;">
                <strong><?php echo esc_html( $link_url ); ?></strong><br/>

                <label>
                    <input type="radio" name="slp_category_links[<?php echo esc_attr( $link_url ); ?>]" value="relatedLink"
                           <?php checked( $link_type, 'relatedLink' ); ?> />
                    relatedLink
                </label>
                &nbsp;&nbsp;

                <label>
                    <input type="radio" name="slp_category_links[<?php echo esc_attr( $link_url ); ?>]" value="significantLink"
                           <?php checked( $link_type, 'significantLink' ); ?> />
                    significantLink
                </label>
                &nbsp;&nbsp;

                <label>
                    <input type="radio" name="slp_category_links[<?php echo esc_attr( $link_url ); ?>]" value=""
                           <?php checked( $link_type, '' ); ?> />
                    None
                </label>
            </div>
            <?php
        }
    }

    echo '  </td>';
    echo '</tr>';
}

function slp_save_category_links( $term_id ) {
    // For production, you might add a nonce check here.
    if ( isset( $_POST['slp_category_links'] ) && is_array( $_POST['slp_category_links'] ) ) {
        $links_data = array_map( 'sanitize_text_field', $_POST['slp_category_links'] );
        update_term_meta( $term_id, '_slp_schema_links', $links_data );
    } else {
        delete_term_meta( $term_id, '_slp_schema_links' );
    }
}

/* ---------------------------------------------------------------------------
 * 4. HELPER FUNCTIONS FOR RETRIEVAL
 * --------------------------------------------------------------------------- */
function slp_get_post_classifications( $post_id ) {
    $saved_links = get_post_meta( $post_id, '_slp_schema_links', true );
    if ( ! is_array( $saved_links ) ) {
        return [ 'significantLink' => [], 'relatedLink' => [] ];
    }

    $sig = [];
    $rel = [];
    foreach ( $saved_links as $url => $type ) {
        if ( $type === 'significantLink' ) {
            $sig[] = $url;
        } elseif ( $type === 'relatedLink' ) {
            $rel[] = $url;
        }
    }
    return [
        'significantLink' => array_values( array_unique( $sig ) ),
        'relatedLink'     => array_values( array_unique( $rel ) ),
    ];
}

function slp_get_term_classifications( $term_id ) {
    $saved_links = get_term_meta( $term_id, '_slp_schema_links', true );
    if ( ! is_array( $saved_links ) ) {
        return [ 'significantLink' => [], 'relatedLink' => [] ];
    }

    $sig = [];
    $rel = [];
    foreach ( $saved_links as $url => $type ) {
        if ( $type === 'significantLink' ) {
            $sig[] = $url;
        } elseif ( $type === 'relatedLink' ) {
            $rel[] = $url;
        }
    }
    return [
        'significantLink' => array_values( array_unique( $sig ) ),
        'relatedLink'     => array_values( array_unique( $rel ) ),
    ];
}

/* ---------------------------------------------------------------------------
 * 5. JSON-LD OUTPUT / MERGE WITH YOAST
 * --------------------------------------------------------------------------- */
function slp_merge_post_into_yoast( $data ) {
    if ( ! is_singular() ) {
        return $data;
    }
    $post_id = get_the_ID();
    if ( ! $post_id ) {
        return $data;
    }
    $links = slp_get_post_classifications( $post_id );
    if ( empty( $links['significantLink'] ) && empty( $links['relatedLink'] ) ) {
        return $data;
    }

    foreach ( $data as &$graph_piece ) {
        if ( isset( $graph_piece['@type'] ) && $graph_piece['@type'] === 'WebPage' ) {
            if ( $links['significantLink'] ) {
                $graph_piece['significantLink'] = $links['significantLink'];
            }
            if ( $links['relatedLink'] ) {
                $graph_piece['relatedLink'] = $links['relatedLink'];
            }
            break;
        }
    }
    return $data;
}

function slp_merge_category_into_yoast( $data ) {
    if ( ! is_category() ) {
        return $data;
    }
    $term = get_queried_object();
    if ( ! $term || ! isset( $term->term_id ) ) {
        return $data;
    }
    $links = slp_get_term_classifications( $term->term_id );
    if ( empty( $links['significantLink'] ) && empty( $links['relatedLink'] ) ) {
        return $data;
    }

    foreach ( $data as &$graph_piece ) {
        if ( isset( $graph_piece['@type'] ) && in_array( $graph_piece['@type'], ['WebPage','CollectionPage'], true ) ) {
            if ( $links['significantLink'] ) {
                $graph_piece['significantLink'] = $links['significantLink'];
            }
            if ( $links['relatedLink'] ) {
                $graph_piece['relatedLink'] = $links['relatedLink'];
            }
            break;
        }
    }
    return $data;
}

/**
 * Output JSON-LD (no Yoast) for single post/page.
 */
function slp_output_jsonld_single() {
    if ( ! is_singular() ) {
        return;
    }
    $post_id = get_the_ID();
    $links   = slp_get_post_classifications( $post_id );
    if ( empty( $links['significantLink'] ) && empty( $links['relatedLink'] ) ) {
        return;
    }

    $json = [
        '@context' => 'https://schema.org',
        '@type'    => 'WebPage',
        'name'     => get_the_title( $post_id ),
        'url'      => get_permalink( $post_id ),
    ];
    if ( $links['significantLink'] ) {
        $json['significantLink'] = $links['significantLink'];
    }
    if ( $links['relatedLink'] ) {
        $json['relatedLink'] = $links['relatedLink'];
    }

    echo '<script type="application/ld+json">' .
         wp_json_encode( $json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) .
         '</script>';
}

/**
 * Output JSON-LD (no Yoast) for category archives, if classifications exist.
 */
function slp_output_jsonld_category() {
    if ( ! is_category() ) {
        return;
    }
    $term = get_queried_object();
    if ( ! $term || ! isset( $term->term_id ) ) {
        return;
    }
    $links = slp_get_term_classifications( $term->term_id );
    if ( empty( $links['significantLink'] ) && empty( $links['relatedLink'] ) ) {
        return;
    }

    $json = [
        '@context' => 'https://schema.org',
        '@type'    => 'WebPage',
        'name'     => single_cat_title( '', false ),
        'url'      => get_term_link( $term->term_id ),
    ];
    // or '@type' => 'CollectionPage' if you prefer

    if ( $links['significantLink'] ) {
        $json['significantLink'] = $links['significantLink'];
    }
    if ( $links['relatedLink'] ) {
        $json['relatedLink'] = $links['relatedLink'];
    }

    echo '<script type="application/ld+json">' .
         wp_json_encode( $json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) .
         '</script>';
}

/* ---------------------------------------------------------------------------
 * 6. INIT LOGIC: Check Yoast Setting & Decide (Merge or Output)
 * --------------------------------------------------------------------------- */
function slp_init_schema_logic() {
    $opts = get_option( 'slp_settings' );
    $yoast_enabled = ! empty( $opts['yoast_integration'] ) && defined( 'WPSEO_VERSION' );

    if ( $yoast_enabled ) {
        // Merge with Yoast
        add_filter( 'wpseo_schema_graph', 'slp_merge_post_into_yoast' );
        add_filter( 'wpseo_schema_graph', 'slp_merge_category_into_yoast' );
    } else {
        // Output our own JSON-LD
        add_action( 'wp_head', 'slp_output_jsonld_single' );
        add_action( 'wp_head', 'slp_output_jsonld_category' );
    }
}
add_action( 'plugins_loaded', 'slp_init_schema_logic' );


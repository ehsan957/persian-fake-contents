<?php
/**
 * Plugin Name:       Persian Fake contents Generator
 * Plugin URI:        https://wordpress.org/plugins/persian-fake-contents/
 * Description:       A tool for persian wordpress developers to create Fake posts in Persian/Farsi language
 * Version:           1.3.1
 * Requires at least: 5.9
 * Requires PHP:      8.0
 * Author:            Ehsan Shariaty
 * Author URI:        https://grizzly.ir
 * Text Domain:       persian-fake-contents
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'GFPC_MAX_POSTS', 10 );

add_action( 'admin_menu', 'GFPC_setup_menu' );
function GFPC_setup_menu() {
    add_menu_page(
        __( 'Grizzly Fake Contents', 'persian-fake-contents' ),
        __( 'Persian Content', 'persian-fake-contents' ),
        'manage_options',
        'grizzly-plugin',
        'GFPC_page_init'
    );
}

function GFPC_allowed_statuses() {
    return array(
        'draft'   => __( 'Draft', 'persian-fake-contents' ),
        'pending' => __( 'Pending Review', 'persian-fake-contents' ),
        'publish' => __( 'Publish', 'persian-fake-contents' ),
    );
}

function GFPC_page_init() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'persian-fake-contents' ) );
    }
    ?>
    <div class="wrap gfpc-wrap">
        <style type="text/css">
        .gfpc-wrap .button.button-primary{
            border: none;
            border-radius: 10px;
            color:white;
            background-color: green;
        }
        .gfpc-wrap .button.button-primary:hover{
            color:green;
            background-color: transparent;
            font-weight: bold;
            border: solid;
        }
        .gfpc-wrap .button.delete{
            border: none;
            border-radius: 10px;
            color:white;
            background-color:red;
        }
        .gfpc-wrap .button.delete:hover{
            color:red;
            font-weight: bold;
            border: solid;
        }
        .gfpc-wrap strong{
            font-size: large;
            color: red;
        }
        .gfpc-wrap .gfpc-form td{
            padding: 8px 10px 8px 0;
        }
        </style>

        <h1><?php esc_html_e( 'Fake Persian Contents Generator', 'persian-fake-contents' ); ?></h1>
        <?php
        if ( ! class_exists( 'SQLite3' ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'This plugin needs the PHP SQLite3 extension, which is not enabled on this server. Please ask your host to enable it.', 'persian-fake-contents' ) . '</p></div>';
        } else {
            GFPC_set_numberOf_posts();
        }
        GFPC_delete_fakes();
        ?>
        <h2><?php esc_html_e( 'How many posts or pages do you need?', 'persian-fake-contents' ); ?></h2>
        <form method="post">
            <?php wp_nonce_field( 'gfpc_create', 'gfpc_create_nonce' ); ?>
            <table class="gfpc-form">
                <tbody>
                    <tr>
                        <td><label for="post_num"><?php esc_html_e( 'Number:', 'persian-fake-contents' ); ?></label></td>
                        <td><input type="number" id="post_num" name="post_num" value="1" min="1" max="<?php echo esc_attr( GFPC_MAX_POSTS ); ?>" class="small-text"></td>

                        <td><label for="post_type"><?php esc_html_e( 'Type:', 'persian-fake-contents' ); ?></label></td>
                        <td><select name="post_type" id="post_type">
                            <option value="post"><?php esc_html_e( 'Post', 'persian-fake-contents' ); ?></option>
                            <option value="page"><?php esc_html_e( 'Page', 'persian-fake-contents' ); ?></option>
                            <?php
                            $post_types = get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' );
                            foreach ( $post_types as $post_type ) {
                                echo '<option value="' . esc_attr( $post_type->name ) . '">' . esc_html( $post_type->labels->singular_name ) . '</option>';
                            }
                            ?>
                        </select></td>

                        <td><label for="post_category"><?php esc_html_e( 'Category:', 'persian-fake-contents' ); ?></label></td>
                        <td><select name="post_category" id="post_category">
                            <?php
                            $categories = get_categories( array( 'hide_empty' => false ) );
                            foreach ( $categories as $category ) {
                                echo '<option value="' . esc_attr( $category->term_id ) . '">' . esc_html( $category->name ) . '</option>';
                            }
                            ?>
                        </select></td>

                        <td><label for="post_author"><?php esc_html_e( 'Author:', 'persian-fake-contents' ); ?></label></td>
                        <td><select name="post_author" id="post_author">
                            <?php
                            $users = get_users( array( 'capability' => array( 'edit_posts' ) ) );
                            foreach ( $users as $user ) {
                                echo '<option value="' . esc_attr( $user->ID ) . '"' . selected( $user->ID, get_current_user_id(), false ) . '>' . esc_html( $user->display_name ) . '</option>';
                            }
                            ?>
                        </select></td>

                        <td><label for="post_status"><?php esc_html_e( 'Status:', 'persian-fake-contents' ); ?></label></td>
                        <td><select name="post_status" id="post_status">
                            <?php
                            foreach ( GFPC_allowed_statuses() as $value => $label ) {
                                echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
                            }
                            ?>
                        </select></td>

                        <td>
                            <label for="featured_image">
                                <input type="checkbox" id="featured_image" name="featured_image" value="1">
                                <?php esc_html_e( 'Add featured image (downloaded from picsum.photos)', 'persian-fake-contents' ); ?>
                            </label>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button( __( 'Create', 'persian-fake-contents' ) ); ?>
        </form>

        <h2><?php esc_html_e( 'Fake posts that created using this plugin:', 'persian-fake-contents' ); ?></h2>
        <?php GFPC_created_fakes(); ?>

        <h2><?php esc_html_e( 'You can delete all Fake Contents that you created before:', 'persian-fake-contents' ); ?></h2>
        <form method="post">
            <?php wp_nonce_field( 'gfpc_delete', 'gfpc_delete_nonce' ); ?>
            <label for="del"><?php echo wp_kses( __( 'If you are sure that you want to delete fake posts, type <strong>yes</strong> and press the button', 'persian-fake-contents' ), array( 'strong' => array() ) ); ?></label>
            <input type="text" id="del" name="del" placeholder="no" size="3">
            <?php submit_button( __( 'Delete All', 'persian-fake-contents' ), 'delete' ); ?>
        </form>
    </div>
    <?php
}

function GFPC_fake_ids( $post_type = 'any' ) {
    $query = new WP_Query(
        array(
            'meta_key'       => '_grizzly',
            'meta_value'     => 'fake',
            'post_type'      => $post_type,
            'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', 'inherit', 'trash' ),
            'nopaging'       => true,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        )
    );
    return $query->posts;
}

function GFPC_delete_fakes() {
    if ( ! isset( $_POST['del'] ) ) {
        return;
    }
    check_admin_referer( 'gfpc_delete', 'gfpc_delete_nonce' );

    if ( 'yes' !== strtolower( sanitize_text_field( wp_unslash( $_POST['del'] ) ) ) ) {
        echo '<div class="notice notice-warning"><p>' . esc_html__( 'Nothing was deleted. Type "yes" to confirm.', 'persian-fake-contents' ) . '</p></div>';
        return;
    }

    $deleted = 0;
    foreach ( GFPC_fake_ids() as $id ) {
        if ( 'attachment' === get_post_type( $id ) ) {
            wp_delete_attachment( $id, true );
        } elseif ( wp_delete_post( $id, true ) ) {
            $deleted++;
        }
    }

    /* translators: %d: number of deleted posts */
    echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( '%d fake items deleted.', 'persian-fake-contents' ), $deleted ) ) . '</p></div>';
}

function GFPC_created_fakes() {
    $ids = array_filter(
        GFPC_fake_ids(),
        function ( $id ) {
            return 'attachment' !== get_post_type( $id );
        }
    );

    if ( empty( $ids ) ) {
        echo '<p>' . esc_html__( 'No fake content yet.', 'persian-fake-contents' ) . '</p>';
        return;
    }

    echo '<ul>';
    foreach ( $ids as $id ) {
        $link = get_edit_post_link( $id );
        if ( ! $link ) {
            $link = get_permalink( $id );
        }
        echo '<li><a href="' . esc_url( $link ) . '">' . esc_html( get_the_title( $id ) ) . '</a> <em>(' . esc_html( get_post_type( $id ) . ', ' . get_post_status( $id ) ) . ')</em></li>';
    }
    echo '</ul>';
}

function GFPC_set_numberOf_posts() {
    if ( ! isset( $_POST['post_num'], $_POST['post_type'], $_POST['post_status'] ) ) {
        return;
    }
    check_admin_referer( 'gfpc_create', 'gfpc_create_nonce' );

    $num    = absint( $_POST['post_num'] );
    $ptype  = sanitize_key( wp_unslash( $_POST['post_type'] ) );
    $status = sanitize_key( wp_unslash( $_POST['post_status'] ) );
    $pcat   = isset( $_POST['post_category'] ) ? absint( $_POST['post_category'] ) : 0;
    $pauth  = isset( $_POST['post_author'] ) ? absint( $_POST['post_author'] ) : get_current_user_id();
    $image  = ! empty( $_POST['featured_image'] );

    if ( $num < 1 || $num > GFPC_MAX_POSTS ) {
        /* translators: %d: maximum number of posts */
        echo '<div class="notice notice-error"><p>' . esc_html( sprintf( __( 'You can only choose a number between 1 and %d.', 'persian-fake-contents' ), GFPC_MAX_POSTS ) ) . '</p></div>';
        return;
    }
    if ( ! post_type_exists( $ptype ) || ! array_key_exists( $status, GFPC_allowed_statuses() ) || ! get_userdata( $pauth ) ) {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid options, nothing was created.', 'persian-fake-contents' ) . '</p></div>';
        return;
    }
    if ( $pcat && ! term_exists( $pcat, 'category' ) ) {
        $pcat = 0;
    }

    $created = GFPC_lets_go( $num, $ptype, $pcat, $pauth, $status, $image );

    /* translators: %d: number of created posts */
    echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( '%d fake items created.', 'persian-fake-contents' ), $created ) ) . '</p></div>';
}

function GFPC_load_words() {
    $db = new SQLite3( plugin_dir_path( __FILE__ ) . 'data.sqlite', SQLITE3_OPEN_READONLY );
    $ret = $db->query( 'SELECT word FROM dic ORDER BY RANDOM() LIMIT 1000' );
    $words = array();
    while ( $row = $ret->fetchArray( SQLITE3_ASSOC ) ) {
        $words[] = $row['word'];
    }
    $db->close();
    return $words;
}

function GFPC_random_words( $words, $min, $max ) {
    shuffle( $words );
    return implode( ' ', array_slice( $words, 0, rand( $min, $max ) ) );
}

function GFPC_lets_go( $n, $type, $cat, $auth, $status, $image = false ) {
    try {
        $words = GFPC_load_words();
    } catch ( Exception $e ) {
        echo '<div class="notice notice-error"><p>' . esc_html( $e->getMessage() ) . '</p></div>';
        return 0;
    }
    if ( count( $words ) < 100 ) {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Could not read the word dictionary.', 'persian-fake-contents' ) . '</p></div>';
        return 0;
    }

    $created = 0;
    for ( $i = 1; $i <= $n; $i++ ) {
        $title   = GFPC_random_words( $words, 3, 15 );
        $excerpt = GFPC_random_words( $words, 45, 55 );
        $content = '';
        for ( $p = 1, $paragraphs = rand( 2, 7 ); $p <= $paragraphs; $p++ ) {
            $content .= '<p>' . GFPC_random_words( $words, 45, 100 ) . '</p>' . "\n\n";
        }
        if ( GFPC_create_new_post( $title, $excerpt, $content, $type, $cat, $auth, $status, $image ) ) {
            $created++;
        }
    }
    return $created;
}

function GFPC_create_new_post( $t, $e, $c, $pt, $ct, $a, $s, $image = false ) {
    $new_post = array(
        'post_title'     => sanitize_text_field( $t ),
        'post_excerpt'   => sanitize_text_field( $e ),
        'post_content'   => wp_kses_post( $c ),
        'post_type'      => $pt,
        'post_status'    => $s,
        'post_author'    => $a,
        'comment_status' => 'open',
    );
    if ( $ct && is_object_in_taxonomy( $pt, 'category' ) ) {
        $new_post['post_category'] = array( $ct );
    }

    $post_id = wp_insert_post( $new_post, true );
    if ( is_wp_error( $post_id ) ) {
        return 0;
    }
    GFPC_add_post_key( $post_id );
    if ( $image && post_type_supports( $pt, 'thumbnail' ) ) {
        GFPC_Generate_Featured_Image( $post_id );
    }
    return $post_id;
}

function GFPC_add_post_key( $id ) {
    add_post_meta( $id, '_grizzly', 'fake', true );
}

function GFPC_Generate_Featured_Image( $post_id ) {
    $response = wp_remote_get(
        'https://picsum.photos/1200/628.jpg',
        array(
            'redirection' => 10,
            'timeout'     => 20,
        )
    );
    if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
        return 0;
    }
    $image = wp_remote_retrieve_body( $response );
    if ( '' === $image ) {
        return 0;
    }

    $upload = wp_upload_bits( md5( $image ) . '.jpg', null, $image );
    if ( ! empty( $upload['error'] ) ) {
        return 0;
    }
    $file = $upload['file'];

    $wp_filetype = wp_check_filetype_and_ext( $file, basename( $file ) );
    if ( empty( $wp_filetype['type'] ) || 0 !== strpos( $wp_filetype['type'], 'image/' ) ) {
        wp_delete_file( $file );
        return 0;
    }

    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title'     => sanitize_file_name( basename( $file ) ),
        'post_content'   => '',
        'post_status'    => 'inherit',
    );
    $attach_id = wp_insert_attachment( $attachment, $file, $post_id );
    if ( ! $attach_id ) {
        wp_delete_file( $file );
        return 0;
    }
    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $file ) );
    set_post_thumbnail( $post_id, $attach_id );
    GFPC_add_post_key( $attach_id );
    return $attach_id;
}

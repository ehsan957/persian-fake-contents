<?php
/**
 * Plugin Name: Persian Fake contents Generator
 * Plugin URI:  http://jigjoy.ir/plugins
 * Description: A tool for persian wordpress developers to create Fake posts in Persian/Farsi language
 * Version:     1.3.0
 * Author:      Grizzly development Group.
 * Text Domain: Fake-Persian-Contents
 * Author URI:  http://jigjoy.ir/developers
 * License: GPL3
 */
add_action('admin_menu', 'GFPC_setup_menu');
function GFPC_setup_menu(){
    add_menu_page( 'Grizzly Fake Contents', 'Persian Content', 'publish_pages', 'grizzly-plugin', 'GFPC_page_init' );
}

function GFPC_page_init(){
    $user = wp_get_current_user();
    $allowed_roles = array( 'administrator');
    if(!array_intersect($allowed_roles, $user->roles)){
        echo "Sorry, only <strong>administrators</strong> allow to access this plugin!";
    }
    else{
        GFPC_set_numberOf_posts();
        GFPC_delete_fakes();
        ?>

        <style type="text/css">
		.grizzly-form{
			width:20%;
		}
        .button.button-primary{
            border: none;
            border-color: green;
            border-radius: 10px;
            color:white;
            background-color: green;
        }
        .button.button-primary:hover{
            color:green;
            background-color: transparent;
            font-weight: bold;
            border: solid;
        }
        .button.delete{
            border: none;
            border-color: red;
            border-radius: 10px;
            color:white;
            background-color:red;    
        }
        .button.delete:hover{
            color:red;
            font-weight: bold;
            border: solid;
        }
        strong{
            font-size: large;
            color: red;
        }
        </style>
        
        <h1>Fake Persian Contents Generator</h1>
        <h2>How many posts or pages do you need?</h2>
            <form  method="post" enctype="multipart/form-data">
			<table class="form-table grizzly-form">
				<tbody>
					<tr>
						<td><label for="post_num">Number:</label></td>
						<td><input type='text' id='post_num' name='post_num' value="1" class="tiny-text"></td>
					
						<td><label for="post_type">Type:</label></td>
						<td><select name="post_type" id="post_type" class="small-text">
							<option value="post">Post</option>
							<option value="page">Page</option>
                            <?php
                            $args = array(
                                'public'   => true,
                                '_builtin' => false
                            );
                            $post_types = get_post_types($args);
                            foreach($post_types as $post_type){
                                echo '<option value="'.$post_type.'">'.$post_type.'</option>';
                            }
                            ?>			  
						</select></td>
						<td><label for="post_category">Category:</label></td>
						<td><select name="post_category" id="post_category" class="small-text">
                        <?php
                        $args = array(
                            'hide_empty'      => false,
                        );
                        $categories = get_categories($args);
                        foreach($categories as $category){
                            echo '<option value="'.$category->term_id.'">'.$category->name.'</option>';
                        }
                        ?>									  
						</select></td>
                        <td><label for="post_author">Author:</label></td>
						<td><select name="post_author" id="post_author" class="small-text">
                        <?php
                        $users = get_users();
                        foreach($users as $user){
                            echo '<option value="'.$user->ID.'">'.$user->display_name.'</option>';
                        }
                        ?>									  
						</select></td>
                        <td><label for="post_status">Status:</label></td>
						<td><select name="post_status" id="post_status" class="small-text">
							<option value="draft">Draft</option>	
							<option value="publish">Publish</option>									  
						</select></td>
					</tr>
				</tbody>
			</table>					
				  
				<?php submit_button('Create') ?>
            </form>
        <h2>Fake posts that created using this plugin:</h2>    
        <?php
        GFPC_created_fakes();
        ?>
        <h2>You can delete all Fake Contentes that you created them before:</h2> 
        <form  method="post" enctype="multipart/form-data">
            <label for="del">If you sure that want to delete fake posts type <strong>yes</strong> and press the button</button></label>
            <input type='text' id='del' name='del' placeholder="no" size="3">
            <?php submit_button('Delete All','delete') ?>
        </form>   

        <?php
    }    
}
function GFPC_delete_fakes(){
    if(isset($_POST['del']) && sanitize_text_field(strtolower($_POST['del']) == "yes")){
        $args = array(
        'meta_key'   => '_grizzly',
        'meta_value' => 'fake',
		'nopaging'=>true,
        'post_type' => 'any'
    );
        $query = new WP_Query( $args );
        while ( $query->have_posts() ) {
            $query->the_post();
            $id = get_the_id();
            add_action( 'before_delete_post', function( $id ) {
                $attachments = get_attached_media( '', $id );
                foreach ($attachments as $attachment) {
                  wp_delete_attachment( $attachment->ID, 'true' );
                }
              } ); 
            wp_delete_post($id,true);
        }
    }
}
function GFPC_created_fakes(){
    $args = array(
        'meta_key'   => '_grizzly',
        'meta_value' => 'fake',
		'nopaging'=>true,
        'post_type' => 'any'
    );
    $query = new WP_Query( $args );
    // echo $query->found_posts;

    while ( $query->have_posts() ) {
        $query->the_post();
        echo "<a href=\"".get_the_permalink()."\">".get_the_title() . '</a><br>';
    }
}
function GFPC_set_numberOf_posts(){
    // First check if the file appears on the _FILES array
    if(isset($_POST['post_num']) && isset($_POST['post_type']) && isset($_POST['post_status'])){
        $num = sanitize_text_field($_POST['post_num']);
        $num = intval($num);
		$ptype = sanitize_text_field($_POST['post_type']);
		$pcat = sanitize_text_field($_POST['post_category']);
		$pauth = sanitize_text_field($_POST['post_author']);
		$status = sanitize_text_field($_POST['post_status']);
        
        
        if(is_int($num) && $num>0 && $num<11 ){
            GFPC_lets_go($num,$ptype,$pcat,$pauth,$status);
        }else{
            echo "<strong>You can only choose a number smaller than 11</strong><br>";
           
        }
    }
}
function GFPC_lets_go($n,$type,$cat, $auth, $status){
    for($i=1;$i<=$n;$i++){
        $dir = plugin_dir_path( __FILE__ );
        $dbpath = $dir."data.sqlite";
        $db = new GFPC_MyDB($dbpath);
        if(!$db){
            echo $db->lastErrorMsg();
        }
        else{
            $sql =<<<EOF
            SELECT word from dic ORDER BY RANDOM() LIMIT 1000;
            EOF;
            $ret = $db->query($sql);
            $words = array();            
            while($row = $ret->fetchArray(SQLITE3_ASSOC) ){
                array_push($words,$row['word']);
            }
            //title
            $title = "";
            $random_keys=array_rand($words,15);
            for($j=1; $j<=rand(3,15); $j++){
                $title .= $words[$random_keys[$j]]." ";
            }
            //excerpt
            $excerpt = "";
            $random_keys=array_rand($words,55);
            for($j=1; $j<=rand(45,55); $j++){
                $excerpt .= $words[$random_keys[$j]]." ";
            }
            //echo $excerpt."<br>";
            //content
            $content = "";            
            for($p=1; $p<=rand(2,7); $p++){
                $paragraph="<p>";
                $random_keys=array_rand($words,100);
                for($j=1; $j<=rand(45,100); $j++){
                    $paragraph .= $words[$random_keys[$j]]." ";
                }
                $paragraph .= "</p>";                
                $content .= $paragraph;
            }         
            GFPC_create_new_post($title,$excerpt,$content,$type,$cat, $auth, $status);
            $db->close();

        }  
    }    
}
function GFPC_create_new_post($t,$e,$c,$pt,$ct,$a,$s){
    $new_post = array(
        'post_title'    => sanitize_text_field($t),
        'post_excerpt'  => sanitize_text_field($e),
        'post_content'  => sanitize_textarea_field($c),
		'post_type'             => $pt,
        'post_status'   => $s,
        'post_author'   => $a,
        'post_category' => array($ct),
        'comment_status' => 'open'
      );
      $post_id = wp_insert_post( $new_post );
      GFPC_Generate_Featured_Image($post_id);
      GFPC_add_post_key($post_id);
}
function GFPC_add_post_key($id){
    add_post_meta( $id, '_grizzly', 'fake', false );
}
function GFPC_Generate_Featured_Image($post_id){
    $upload_dir = wp_upload_dir();
    $imageRawUrl = "https://picsum.photos/1200/628.jpg";
    $args_for_get = array(
        'redirection' => 10,
    );
    $image = wp_remote_retrieve_body(wp_remote_get($imageRawUrl, $args_for_get));    
    $filename = md5($image).".jpg";
    if(wp_mkdir_p($upload_dir['path']))
      $file = $upload_dir['path'] . '/' . $filename;
    else
      $file = $upload_dir['basedir'] . '/' . $filename;
      file_put_contents($file, $image);

    $wp_filetype = wp_check_filetype($filename, null );
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name($filename),
        'post_content' => '',
        'post_status' => 'inherit'
    );
    $attach_id = wp_insert_attachment( $attachment, $file, $post_id );
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
    $res1= wp_update_attachment_metadata( $attach_id, $attach_data );
    $res2= set_post_thumbnail( $post_id, $attach_id );
    GFPC_add_post_key($attach_id);
}

class GFPC_MyDB extends SQLite3{
    function __construct($db){
        $this->open($db, SQLITE3_OPEN_READONLY);
    }
}


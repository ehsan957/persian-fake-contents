<?php
/**
 * Plugin Name: Persian Fake contents
 * Plugin URI:  http://grizzly.ir/plugins
 * Description: A tool for persian wordpress developers to create Fake posts in Persian/Farsi language
 * Version:     1.0
 * Author:      Grizzly development Group.
 * Text Domain: Fake-Persian-Contents
 * Author URI:  http://grizzly.ir/
 * License: GPL2
 */
?>

<?php
add_action('admin_menu', 'grizzly_setup_menu');
function grizzly_setup_menu(){
    add_menu_page( 'Grizzly Fake Contents', 'Persian Content', 'publish_pages', 'grizzly-plugin', 'page_init' );
}

function page_init(){
    set_numberOf_posts();
    delete_fakes();
    ?>
    <style type="text/css">
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
    
    <h1>Grizzly Fake Persian Contents</h1>
    <h2>How many posts do you need?</h2>
        <form  method="post" enctype="multipart/form-data">
        <input type='text' id='post_num' name='post_num' value="1" size="2">
        <?php submit_button('Create') ?>
        </form>
    <h2>Fake posts that created using this plugin:</h2>    
    <?php
    created_fakes();
    ?>
    <h2>You can delete all Fake Contentes that you created them before:</h2> 
    <form  method="post" enctype="multipart/form-data">
        <label for="del">If you sure that want to delete fake posts type <strong>yes</strong> and press the button</button></label>
        <input type='text' id='del' name='del' placeholder="no" size="3">
        <?php submit_button('Delete All','delete') ?>
    </form>    
    <?php    
}
function delete_fakes(){
    if(isset($_POST['del']) && strtolower($_POST['del']) == "yes"){
        $args = array(
            'meta_key'   => '_grizzly',
            'meta_value' => 'fake',
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
function created_fakes(){
    $args = array(
        'meta_key'   => '_grizzly',
        'meta_value' => 'fake',
        'post_type' => 'any'
    );
    $query = new WP_Query( $args );
    // echo $query->found_posts;

    while ( $query->have_posts() ) {
        $query->the_post();
        echo "<a href=\"".get_the_permalink()."\">".get_the_title() . '</a><br>';
    }
}
function set_numberOf_posts(){
    // First check if the file appears on the _FILES array
    if(isset($_POST['post_num'])){
        $num = $_POST['post_num'];
        $num = intval($num);      
        
        
        if(is_int($num) && $num>0 && $num<100){
            lets_go($num);
        }else{
            echo "<strong>You can only choose a number smaller than 100</strong><br>";
           
        }
    }
}
function lets_go($n){
    for($i=1;$i<=$n;$i++){
        $dbpath = ABSPATH . 'wp-content/plugins/grizzly_fake_persian_content/data.sqlite';
        $db = new MyDB($dbpath);
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
          
            $imageRawUrl = "https://picsum.photos/1200/628.jpg";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $imageRawUrl);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $a = curl_exec($ch);

            $image = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            create_new_post($title,$excerpt,$image,$content);
            $db->close();

        }  
    }    
}
function create_new_post($t,$e,$i,$c){
    $new_post = array(
        'post_title'    => wp_strip_all_tags($t),
        'post_excerpt'  => $e,
        'post_content'  => $c,
        'post_status'   => 'publish',
        'post_author'   => 1,
        'comment_status' => 'open'
      );
      $post_id = wp_insert_post( $new_post );
      Generate_Featured_Image($i, $post_id);
      add_post_key($post_id);
}
function add_post_key($id){
    add_post_meta( $id, '_grizzly', 'fake', false );
}
function Generate_Featured_Image( $image_url, $post_id ){
    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents($image_url);
    $filename = md5($image_url).".jpg";
    if(wp_mkdir_p($upload_dir['path']))
      $file = $upload_dir['path'] . '/' . $filename;
    else
      $file = $upload_dir['basedir'] . '/' . $filename;
    file_put_contents($file, $image_data);

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
    add_post_key($attach_id);
}

class MyDB extends SQLite3{
    function __construct($db){
        $this->open($db);
    }
}
function random_pic($dir)
{
    $files = glob($dir . '/*.*');
    $file = array_rand($files);
    return $files[$file];
}
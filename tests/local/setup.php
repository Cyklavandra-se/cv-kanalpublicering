<?php
$_SERVER['HTTP_HOST']='localhost:8899';$_SERVER['REQUEST_URI']='/';
require getenv('W').'/wp-load.php';
require_once ABSPATH.'wp-admin/includes/plugin.php';
require_once ABSPATH.'wp-admin/includes/image.php';
var_dump(activate_plugin('cv-kanalpublicering/cv-kanalpublicering.php'));
wp_set_current_user(1);
update_option('permalink_structure','/%postname%/'); flush_rewrite_rules();
wp_insert_term('cykla','post_tag'); wp_insert_term('vandra','post_tag');
register_taxonomy_for_object_type('post_tag','page');
function img($name,$w,$h,$color){ $im=imagecreatetruecolor($w,$h); imagefill($im,0,0,imagecolorallocate($im,...$color)); for($i=0;$i<$w;$i+=100) imageline($im,$i,0,$i,$h,imagecolorallocate($im,255,255,255)); $up=wp_upload_dir(); $p=$up['path']."/$name.jpg"; imagejpeg($im,$p,85); $id=wp_insert_attachment(array('post_mime_type'=>'image/jpeg','post_title'=>$name,'post_status'=>'inherit'),$p); wp_update_attachment_metadata($id,wp_generate_attachment_metadata($id,$p)); return $id; }
$trips=array(array('Mosel – cykla en vecka','cykla','bike1',array(60,120,60),'Mosel','Tyskland'),array('Madeira – vandra','vandra','hike2',array(40,80,160),'Madeira','Portugal'),array('Verdon på cykel','cykla','bike3',array(160,90,40),'Verdon','Frankrike'));
foreach($trips as $t){ $f=img($t[4].'-hero',1200,630,$t[3]); $g=img($t[4].'-foto',2000,1500,array(200,200,60));
 $id=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>$t[0],'post_content'=>"<p>En fin resa i {$t[4]}. 8 dagar / 7 nätter med cykel och vin.</p><img class=\"wp-image-$g\" src=\"x.jpg\">",'tags_input'=>array($t[1],$t[2],'sjalvguidad')));
 wp_set_post_tags($id,array($t[1],$t[2],'sjalvguidad')); set_post_thumbnail($id,$f); update_post_meta($id,'_cvkp_country',$t[5]); echo "trip $id\n"; }
wp_create_user('sarah','sarah','s@example.com'); $u=get_user_by('login','sarah'); $u->set_role('editor');

<?php
$_SERVER['HTTP_HOST']='localhost:8899';$_SERVER['REQUEST_URI']='/';
require getenv('W').'/wp-load.php';
@unlink(WP_CONTENT_DIR.'/cvkp-mock.log'); delete_option('cvkp_mock_state');
$o=get_option('cvkp_settings',array()); unset($o['meta_token'],$o['meta_page_id'],$o['meta_ig_id']); update_option('cvkp_settings',$o); // Börja utan Meta-anslutning.
$sarah=get_user_by('login','sarah'); wp_set_current_user($sarah->ID);
function notices(){ $n=get_transient('cvkp_notices_'.get_current_user_id()); delete_transient('cvkp_notices_'.get_current_user_id()); foreach((array)$n as $x) echo "  [{$x['type']}] {$x['message']}\n"; }
function submit($id,$cvkp,$extra=array()){ $_POST=array_merge(array('cvkp_nonce'=>wp_create_nonce('cvkp_save'),'cvkp'=>wp_slash($cvkp)),$extra); wp_update_post(array('ID'=>$id,'post_title'=>'Boka tidigt – november 2026')); $_POST=array(); notices(); }
$id=wp_insert_post(array('post_type'=>'cvkp_utskick','post_title'=>'tmp','post_status'=>'draft'));
$base=array('subject'=>'Boka tidigt 🚲','preheader'=>'Rabatt','heading'=>'Boka er sommarresa nu','intro'=>"<p>Nu kan ni boka nästa års resor. Visste ni att tidiga bokningar får bäst datum?</p><p>Andra stycket.</p>",'code'=>'TIDIG26','code_note'=>'Gäller till 6 december',
 'trips'=>array('a'=>array('id'=>6,'text'=>'Mosel är fint.','ig'=>'1'),'b'=>array('id'=>9,'text'=>'Madeira är grönt.','ig'=>'1'),'c'=>array('id'=>12,'text'=>'Verdon','ig'=>'0')),
 'ig_signer'=>'Sarah','channels'=>array('mailchimp','blog','facebook','instagram'));
echo "== 1. Skapa utkast (alla kanaler)\n"; submit($id,$base,array('cvkp_run'=>1));
echo "status: ".get_post_status($id)."\n";
$d=CVKP_Mailing::get_data($id); echo "--- IG-text:\n{$d['ig_text']}\n--- FB-text:\n{$d['fb_text']}\n---\n";
$ig=CVKP_Social::get_state($id,'instagram'); foreach($ig['draft']['images'] as $im) echo "img {$im['url']} {$im['width']}x{$im['height']}\n";
$blog=(int)get_post_meta($id,'_cvkp_blog_post_id',true); echo "blog $blog ".get_post_status($blog)." title=".get_the_title($blog)."\n";
echo "== 2. Kör igen (ska uppdatera samma kampanj)\n"; $base['ig_text']=$d['ig_text']; $base['fb_text']=$d['fb_text']; submit($id,$base,array('cvkp_run'=>1));
echo "== 3. Schemalägg FB före blogg publicerad (ska nekas)\n"; submit($id,$base,array('cvkp_schedule'=>'facebook','cvkp_when'=>array('facebook'=>wp_date('Y-m-d\TH:i',time()+7200))));
echo "== 4. Schemalägg IG utan Meta-inställning (ska nekas)\n"; submit($id,$base,array('cvkp_schedule'=>'instagram','cvkp_when'=>array('instagram'=>wp_date('Y-m-d\TH:i',time()+7200))));
wp_set_current_user(1); update_option('cvkp_settings',array_merge(get_option('cvkp_settings',array()),array('meta_token'=>'EAAfake'))); 
$r=(new CVKP_Meta())->test(); print_r($r); $o=get_option('cvkp_settings'); $o['meta_page_id']=$r['page_id']; $o['meta_ig_id']=$r['ig_id']; update_option('cvkp_settings',$o);
wp_set_current_user($sarah->ID);
echo "== 5. IG för tidigt (5 min)\n"; submit($id,$base,array('cvkp_schedule'=>'instagram','cvkp_when'=>array('instagram'=>wp_date('Y-m-d\TH:i',time()+300))));
echo "== 6. IG om 2 h\n"; submit($id,$base,array('cvkp_schedule'=>'instagram','cvkp_when'=>array('instagram'=>wp_date('Y-m-d\TH:i',time()+7200))));
echo "cron: ".var_export(wp_next_scheduled('cvkp_instagram_publish',array($id)),true)."\n";
echo "== 7. Ändra texten efter schemaläggning\n"; $b2=$base; $b2['ig_text'].="\nExtra"; submit($id,$b2,array('cvkp_run'=>1));
$st=CVKP_Social::get_state($id,'instagram'); echo "changed: ".($st['scheduled']['fingerprint']!==CVKP_Social::fingerprint('instagram',$st['draft'])?'ja':'nej')."\n";
echo "== 8. Avbryt IG\n"; submit($id,$b2,array('cvkp_cancel'=>'instagram')); echo "cron: ".var_export(wp_next_scheduled('cvkp_instagram_publish',array($id)),true)."\n";
echo "== 9. Schemalägg igen och kör cron\n"; submit($id,$b2,array('cvkp_schedule'=>'instagram','cvkp_when'=>array('instagram'=>wp_date('Y-m-d\TH:i',time()+3600))));
$st=CVKP_Social::get_state($id,'instagram'); $st['scheduled']['at']=time()-60; update_post_meta($id,'_cvkp_instagram',$st);
do_action('cvkp_instagram_publish',$id); $st=CVKP_Social::get_state($id,'instagram'); echo "IG: {$st['status']} {$st['error']} ".json_encode($st['result'])."\n";
echo "== 10. Publicera bloggen, schemalägg FB\n"; wp_publish_post($blog); submit($id,$b2,array('cvkp_schedule'=>'facebook','cvkp_when'=>array('facebook'=>wp_date('Y-m-d\TH:i',time()+7200))));
echo "== 11. Avbryt FB\n"; submit($id,$b2,array('cvkp_cancel'=>'facebook'));
echo "== 12. Sen cron (7 h)\n"; delete_post_meta($id,'_cvkp_instagram'); submit($id,$b2,array('cvkp_schedule'=>'instagram','cvkp_when'=>array('instagram'=>wp_date('Y-m-d\TH:i',time()+3600))));
$st=CVKP_Social::get_state($id,'instagram'); $st['scheduled']['at']=time()-7*3600; update_post_meta($id,'_cvkp_instagram',$st); do_action('cvkp_instagram_publish',$id); $st=CVKP_Social::get_state($id,'instagram'); echo "IG: {$st['status']} {$st['error']}\n";
echo "== 13. Mailchimp nätverksfel vid kontroll (ingen dubblett)\n"; $s=get_option('cvkp_mock_state'); $s['fail']='/campaigns/c'; update_option('cvkp_mock_state',$s); submit($id,$b2,array('cvkp_run'=>1)); $s=get_option('cvkp_mock_state'); $s['fail']=''; update_option('cvkp_mock_state',$s);
echo "== /aktuellt\n"; echo CVKP_Latest_Redirect::latest_url()."\n";
echo "\n== MOCK-LOGG\n"; echo preg_replace('/access_token=[^& ]+/','access_token=***',file_get_contents(WP_CONTENT_DIR.'/cvkp-mock.log'));

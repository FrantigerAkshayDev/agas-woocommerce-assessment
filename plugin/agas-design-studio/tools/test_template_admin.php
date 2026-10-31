<?php
/** Pure mocked WP admin-template persistence test; does not access the database. */
declare(strict_types=1);
error_reporting(E_ALL);
define('ABSPATH','/');
function add_action(...$a) {} function add_filter(...$a) {} function add_shortcode(...$a) {} function register_activation_hook(...$a) {}
function absint($v) {return abs((int)$v);} function current_user_can($cap,$id=0){return true;}
function get_post_status($id){return $GLOBALS['posts'][$id]['post_status']??'draft';}
function wp_json_encode($v){return json_encode($v);} function sanitize_text_field($v){return trim(strip_tags($v));}
function sanitize_key($v){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v));}
function wp_insert_post($params,$throw=false){
 $id=$params['ID']?:++$GLOBALS['next_id'];
 $GLOBALS['posts'][$id]=['ID'=>$id,'post_type'=>$params['post_type'],'post_title'=>$params['post_title'],'post_status'=>$params['post_status']];return $id;
}
function update_post_meta($id,$key,$val){$GLOBALS['meta'][$id][$key]=$val;}
function delete_post_meta($id,$key){unset($GLOBALS['meta'][$id][$key]);}
function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$id][$key]??null;}
function wp_set_object_terms($id,$terms,$tax){$GLOBALS['terms'][$id][$tax]=$terms;}
function rest_ensure_response($v){return $v;}function is_wp_error($v){return $v instanceof WP_Error;}
function add_query_arg($args,$url){return $url.'?'.http_build_query($args);} function get_permalink($id){return '/'.$id.'/';}
function get_option($key,$fallback=null){return $fallback;}
function home_url($p='/'){return 'http://local.test'. $p;}
function wc_get_products($args){return array_map(fn($id,$n)=>new FakeProduct($id,$n),array_keys($GLOBALS['products']),array_values($GLOBALS['products']));}
class FakeProduct {function __construct(private int $id,private string $name){}function get_name(){return $this->name;}function get_id(){return $this->id;}}
class FakeRequest {function __construct(private array $data){}function get_json_params(){return $this->data;}}
class WP_Error {function __construct(public $code,public $message,public $data) {}}
function verify(bool $condition,string $description):void {if(!$condition) throw new RuntimeException('FAIL '.$description);}
$GLOBALS['next_id']=50;$GLOBALS['posts']=[];$GLOBALS['meta']=[];$GLOBALS['terms']=[];
$GLOBALS['products']=[101=>'Yard Sign',102=>'Custom Flag',103=>'Outdoor Vinyl Banner'];
require dirname(__DIR__).'/agas-design-studio.php';
$canvas=['version'=>'5.3.1','objects'=>[['type'=>'textbox','text'=>'Test Draft','left'=>30,'top'=>40]]];
$back=['version'=>'5.3.1','objects'=>[['type'=>'textbox','text'=>'Back side']]];
$data=['template_id'=>0,'product_id'=>101,'product_ids'=>[101,102],'name'=>'Mock Template','category'=>'Events','front'=>$canvas,'back'=>$back,'status'=>'draft','preview'=>'','mockup_profile'=>'sign-event'];
$result=AGAS_Design_Studio::save_template(new FakeRequest($data));
if(is_wp_error($result))throw new RuntimeException('Draft creation returned WP_Error');
$id=$result['id'];
verify($GLOBALS['posts'][$id]['post_status']==='draft','draft status persisted');
verify($GLOBALS['meta'][$id]['_agas_products']===[101,102],'multi-product assignments persisted');
verify(json_decode($GLOBALS['meta'][$id]['_agas_canvas_back'],true)['objects'][0]['text']==='Back side','back-side canvas persisted');
verify($GLOBALS['terms'][$id]['agas_template_category']==='Events','template category persisted');
verify($GLOBALS['meta'][$id]['_agas_mockup_profile']==='sign-event','physical mockup override saved with template');
echo "PASS create draft, multi-product assignment and saved back-side JSON\n";
$data['template_id']=$id;$data['status']='publish';$data['name']='Published Template';
$result2=AGAS_Design_Studio::save_template(new FakeRequest($data));
verify($result2['id']===$id,'edit kept original template ID');
verify($GLOBALS['posts'][$id]['post_status']==='publish','published status persisted');
echo "PASS publish existing template without duplicate post\n";
$data['mockup_profile']='flag-rectangular';
AGAS_Design_Studio::save_template(new FakeRequest($data));
verify(!isset($GLOBALS['meta'][$id]['_agas_mockup_profile']), 'incompatible flag format rejected for sign template');
echo "PASS valid physical mockup persisted and incompatible override rejected\n";
$data['status']='malicious';
$invalid=AGAS_Design_Studio::save_template(new FakeRequest($data));
verify($GLOBALS['posts'][$id]['post_status']==='draft','draft status persisted');
echo "PASS invalid publication status defaults safely to Draft\n";
echo "TEMPLATE_ADMIN_TESTS=PASS\n";

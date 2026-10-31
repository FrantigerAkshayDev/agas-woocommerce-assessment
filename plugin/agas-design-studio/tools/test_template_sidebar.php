<?php
/** Isolated v0.1.18 editor template endpoint tests; no WordPress DB. */
declare(strict_types=1);
error_reporting(E_ALL);
define('ABSPATH', '/');
function add_action(...$args) {} function add_filter(...$args) {} function add_shortcode(...$args) {} function register_activation_hook(...$args) {}
function absint($x){return abs((int)$x);}
function current_user_can($cap,$id=0){return false;}
function wc_get_products($args){return [new FakeProduct(3,'Outdoor Vinyl Banner'),new FakeProduct(4,'Custom Flag'),new FakeProduct(5,'Yard Sign')];}
class FakeProduct{function __construct(private int $id,private string $name){} function get_name(){return $this->name;} function get_id(){return $this->id;}}
function get_post($id){return $GLOBALS['posts'][(int)$id]??null;}
function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$id][$key]??null;}
function wp_get_post_terms($id,$taxonomy,$opts){return ['Country Flags'];}
function is_wp_error($v){return $v instanceof WP_Error;}
function rest_ensure_response($v){return $v;}
function plugins_url($path,$file){return 'http://test.invalid/plugin/'.$path;}
class WP_Error {function __construct(public string $code,public string $message,public array $data) {}}
class FakeRequest implements ArrayAccess{
 function __construct(private int $id,private int $product){}
 function offsetExists(mixed $offset):bool{return $offset==='id';}
 function offsetGet(mixed $offset):mixed{return $this->id;}
 function offsetSet(mixed $offset,mixed $value):void{}
 function offsetUnset(mixed $offset):void{}
 function get_param($key){return $key==='product_id'?$this->product:null;}
}
function verify($value,$message){if(!$value)throw new RuntimeException('FAIL: '.$message);}
require dirname(__DIR__).'/agas-design-studio.php';
$GLOBALS['posts']=[
 101=>(object)['ID'=>101,'post_type'=>'agas_template','post_status'=>'publish','post_title'=>'Flag - Japan Flag'],
 102=>(object)['ID'=>102,'post_type'=>'agas_template','post_status'=>'draft','post_title'=>'Secret Draft'],
 103=>(object)['ID'=>103,'post_type'=>'agas_design','post_status'=>'publish','post_title'=>'Private design'],
];
$front=['objects'=>[['type'=>'textbox','text'=>'Japan']],'background'=>'#ffffff'];
$GLOBALS['meta']=[
 101=>['_agas_products'=>[4], '_agas_canvas'=>json_encode($front),'_agas_canvas_back'=>'', '_agas_seed_preview'=>'flag-japan.png'],
 102=>['_agas_products'=>[4], '_agas_canvas'=>json_encode($front)],
 103=>['_agas_products'=>[4], '_agas_canvas'=>json_encode($front)],
];
$ok=AGAS_Design_Studio::fetch_editor_template(new FakeRequest(101,4));
verify(is_array($ok)&&$ok['front']['objects'][0]['text']==='Japan','published assigned flag JSON readable');
verify($ok['thumbnail']==='http://test.invalid/plugin/assets/thumbs/flag-japan.png','correct thumbnail');
foreach ([[101,5],[101,0],[102,4],[103,4],[999,4]] as $pair){
  $result=AGAS_Design_Studio::fetch_editor_template(new FakeRequest(...$pair));
  verify($result instanceof WP_Error && $result->data['status']===404,'invalid template/product rejected');
}
$GLOBALS['meta'][101]['_agas_legacy_hidden']='1';
$hidden=AGAS_Design_Studio::fetch_editor_template(new FakeRequest(101,4));
verify($hidden instanceof WP_Error,'hidden legacy template is not exposed');
echo "PASS published product-assigned template loads JSON and thumbnail\n";
echo "PASS wrong products, drafts, design records, nonexistent and hidden templates rejected\n";
echo "TEMPLATE_SIDEBAR_SERVER_TESTS=PASS\n";

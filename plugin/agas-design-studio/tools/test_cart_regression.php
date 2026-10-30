<?php
/** Standalone mocked cart/pricing regression test. No WordPress DB access.
 * Run: php tools/test_cart_regression.php
 */
declare(strict_types=1);
error_reporting(E_ALL);
define('ABSPATH', '/');
function add_action(...$args) {}
function add_filter(...$args) {}
function add_shortcode(...$args) {}
function register_activation_hook(...$args) {}
function absint($value) {return abs((int)$value);}
function is_wp_error($value) {return $value instanceof WP_Error;}
class WP_Error {public function __construct(public string $code,public string $message,public array $data=[]) {}}
function get_option($name,$fallback=[]) {return $fallback;}
function wp_parse_args($settings,$defaults) {return array_merge($defaults,$settings);}
function wc_get_price_decimals() {return 2;}
function get_post($id) {return isset($GLOBALS['designs'][$id]) ? (object)['ID'=>$id,'post_type'=>'agas_design','post_author'=>1] : null;}
function get_post_meta($id,$key,$single=true) {return $GLOBALS['designs'][$id][$key] ?? null;}
function esc_html($s) {return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function sanitize_text_field($s) {return $s;}
function wc_get_products($args) {return array_map(fn($n,$id)=>new FakeProduct($id,$n),array_values($GLOBALS['products']),array_keys($GLOBALS['products']));}
class FakeProduct {public $price=0;public function __construct(public int $id,public string $name='') {}public function get_id(){return $this->id;}public function get_name(){return $this->name;}public function set_price($price){$this->price=$price;}public function is_purchasable(){return true;}}
function wc_get_product($id){return new FakeProduct($id,$GLOBALS['products'][$id]??'');}
class WC_Cart {public $cart_contents=[];public function get_cart() {return $this->cart_contents;} public function calculate_totals(){AGAS_Design_Studio::apply_cart_prices($this);}}
class FakeWC {public function __construct(public WC_Cart $cart) {}}
function WC(){return $GLOBALS['wc'];}
$GLOBALS['products']=[101=>'Yard Sign',102=>'Custom Flag',103=>'Outdoor Vinyl Banner'];
$GLOBALS['designs']=[
    201 => ['_agas_token'=>str_repeat('x',48),'_agas_product_id'=>101,'_agas_options'=>['width'=>3,'height'=>2,'unit'=>'ft','double_sided'=>false]],
    202 => ['_agas_token'=>str_repeat('y',48),'_agas_product_id'=>102,'_agas_options'=>['width'=>3,'height'=>2,'unit'=>'ft','double_sided'=>true]],
    203 => ['_agas_token'=>str_repeat('z',48),'_agas_product_id'=>101,'_agas_options'=>['width'=>3,'height'=>2,'unit'=>'ft','double_sided'=>true,'grommets'=>true]],
];
require dirname(__DIR__) . '/agas-design-studio.php';
$cart = new WC_Cart;
$cart->cart_contents=[
 'yard-front'=> ['product_id'=>101,'agas_design_id'=>201,'agas_design_token'=>str_repeat('x',48), 'agas_options'=>['width'=>1,'height'=>1,'unit'=>'ft','double_sided'=>false], 'data'=>new FakeProduct(101)],
 'flag-back'=> ['product_id'=>102,'agas_design_id'=>202,'agas_design_token'=>str_repeat('y',48), 'agas_options'=>['width'=>3,'height'=>2,'unit'=>'ft','double_sided'=>false], 'data'=>new FakeProduct(102)],
 'yard-back-grommets'=> ['product_id'=>101,'agas_design_id'=>203,'agas_design_token'=>str_repeat('z',48), 'agas_options'=>['width'=>3,'height'=>2,'unit'=>'ft','double_sided'=>false], 'data'=>new FakeProduct(101)],
];
$GLOBALS['wc']=new FakeWC($cart);
function check($condition,$label){if(!$condition)throw new RuntimeException('FAIL '.$label);echo 'PASS '.$label."\n";}
$cart->calculate_totals();
check(count($cart->cart_contents)===3,'three independent customized cart lines remain');
check($cart->cart_contents['yard-front']['data']->price===24.0,'first item server price $24 despite stale client options');
check($cart->cart_contents['flag-back']['data']->price===36.0,'double-sided flag server price $36');
check($cart->cart_contents['yard-back-grommets']['data']->price===41.0,'double-sided sign plus grommets $41');
check($cart->cart_contents['flag-back']['agas_options']['double_sided']===true,'snapshot updated to front+back');
$details=AGAS_Design_Studio::cart_item_details([],$cart->cart_contents['flag-back']);
check((bool)array_filter($details,fn($d)=>$d['key']==='Sides' && $d['value']==='Front and Back'),'cart line shows Front and Back');
$GLOBALS['designs'][201]['_agas_options']['double_sided']=true;
$cart->calculate_totals();
check($cart->cart_contents['yard-front']['data']->price===36.0,'editing existing saved design updates cart to $36');
$stale=['product_id'=>102,'agas_design_id'=>202,'agas_design_token'=>str_repeat('y',48),'agas_options'=>['width'=>1,'height'=>1,'unit'=>'ft','double_sided'=>false]];
$restored=AGAS_Design_Studio::sync_session_cart_item($stale,[],'key');
check($restored['agas_options']['double_sided']===true,'session restore reads canonical design options');
class FakeOrderItem {public $metadata=[];public function add_meta_data($key,$value,$unique=true){$this->metadata[$key]=$value;}}
$order_item=new FakeOrderItem;
AGAS_Design_Studio::order_item_meta($order_item,'flag-back',$cart->cart_contents['flag-back'],null);
check(($order_item->metadata['Sides']??'')==='Front and Back','checkout line metadata records both sides');
check(($order_item->metadata['Print Size']??'')==='3 x 2 ft','checkout line metadata records size');
check(!isset($GLOBALS['wc']->cart->cart_contents['nonexistent']),'unrelated cart state untouched');
echo "CART_REGRESSION_TESTS=PASS\n";

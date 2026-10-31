<?php
/** Isolated My Account integration tests. No live WordPress DB calls. */
declare(strict_types=1);
error_reporting(E_ALL);
define('ABSPATH', '/');
define('EP_ROOT', 64);
define('EP_PAGES', 128);
$GLOBALS['user'] = 21;
$GLOBALS['opts'] = [];
$GLOBALS['flushes'] = 0;
$GLOBALS['endpoints'] = [];
$GLOBALS['filters'] = [];
$GLOBALS['actions'] = [];
$GLOBALS['posts'] = [];
$GLOBALS['meta'] = [];
function add_action($name, $callback, $priority = 10) { $GLOBALS['actions'][$name] = $callback; }
function add_filter($name, $callback, $priority = 10) { $GLOBALS['filters'][$name] = $callback; }
function add_shortcode(...$args) {} function register_activation_hook(...$args) {}
function add_rewrite_endpoint($slug, $mask) { $GLOBALS['endpoints'][$slug] = $mask; }
function flush_rewrite_rules($hard = true) { $GLOBALS['flushes']++; }
function get_option($key) { return $GLOBALS['opts'][$key] ?? null; }
function update_option($key,$value) { $GLOBALS['opts'][$key]=$value; }
function absint($value) { return abs((int) $value); }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$v)); }
function sanitize_text_field($v) { return trim(strip_tags((string)$v)); }
function wp_unslash($v) { return stripslashes((string)$v); }
function is_user_logged_in() { return $GLOBALS['user'] > 0; }
function get_current_user_id() { return $GLOBALS['user']; }
function esc_url($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function esc_url_raw($v) { return (string)$v; }
function esc_attr($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function esc_html($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function selected($a,$b) { if ($a===$b) echo ' selected="selected"'; }
function _n($one,$many,$count,$domain='') { return $count===1 ? $one : $many; }
function home_url($path='/') { return 'http://local.test'.$path; }
function add_query_arg($args,$url) { return $url.'?'.http_build_query($args); }
function wc_get_account_endpoint_url($ep) { return 'http://local.test/my-account/'.$ep.'/'; }
function get_post($id) { return $GLOBALS['posts'][$id] ?? null; }
function get_the_modified_date($fmt,$id) { return date('M j, Y',strtotime($GLOBALS['posts'][$id]->post_modified)); }
function get_post_meta($id,$key,$single=true) { return $GLOBALS['meta'][$id][$key] ?? null; }
function wp_upload_dir() { return ['baseurl'=>'http://local.test/uploads', 'error'=>false]; }
function trailingslashit($s) { return rtrim($s,'/').'/'; }
function wc_get_products($args) {
 return [new FakeProduct(101,'Yard Sign'),new FakeProduct(102,'Custom Flag'),new FakeProduct(103,'Outdoor Vinyl Banner')];
}
function wc_get_product($id) { foreach(wc_get_products([]) as $p){ if($p->get_id()===$id)return $p; }return false; }
class FakeProduct { public function __construct(private int $id,private string $name){} public function get_id(){return $this->id;} public function get_name(){return $this->name;} }
function get_posts($args) {
 if(($args['post_type']??'')!=='agas_template') throw new RuntimeException('Unexpected get_posts call');
 $results=[];
 foreach ($GLOBALS['posts'] as $p) {
  if($p->post_type==='agas_template' && $p->post_status===($args['post_status']??'publish')
    && stripos($p->post_title,$args['s']??'')!==false) $results[]=$p->ID;
 }
 return $results;
}
class WP_Query {
 public array $posts=[];
 public int $found_posts=0;
 public int $max_num_pages=0;
 public function __construct(array $args){
  if(($args['post_type']??'')!=='agas_design' || ($args['post_status']??'')!=='private') throw new RuntimeException('Account list query scope invalid');
  if(($args['author']??0)!==$GLOBALS['user'])throw new RuntimeException('Account list query missing owner filter');
  $matched=[];
  foreach($GLOBALS['posts'] as $p){
   if($p->post_type!=='agas_design'||$p->post_status!=='private'||$p->post_author!==$args['author'])continue;
   if(isset($args['p'])&&$p->ID!==$args['p'])continue;
   if(isset($args['post__in'])&&!in_array($p->ID,$args['post__in'],true))continue;
   if(isset($args['meta_query'])&&!self::metaMatch($p->ID,$args['meta_query']))continue;
   $matched[]=$p;
  }
  usort($matched,static fn($a,$b) => strcmp($a->post_modified,$b->post_modified) * (($args['order']??'DESC')==='ASC'?1:-1));
  $this->found_posts=count($matched);
  $per=max(1,(int)($args['posts_per_page']??10));
  $this->max_num_pages=(int)ceil($this->found_posts/$per);
  $this->posts=array_slice($matched,((int)($args['paged']??1)-1)*$per,$per);
 }
 private static function metaMatch($id,$clause):bool{
  $relation=$clause['relation']??'AND';$results=[];
  foreach($clause as $key=>$value){
   if($key==='relation')continue;
   if(isset($value['relation'])){$results[]=self::metaMatch($id,$value);continue;}
   $stored=(string)($GLOBALS['meta'][$id][$value['key']]??'');
   $expected=$value['value'];
   $results[] = ($value['compare']??'=')==='IN' ? in_array($stored,array_map('strval',$expected),true) : $stored===(string)$expected;
  }
  return $relation==='OR'?in_array(true,$results,true):!in_array(false,$results,true);
 }
}
function design_post(int $id,int $author,int $product,int $template,string $modified,string $preview='') {
 $GLOBALS['posts'][$id]=(object)['ID'=>$id,'post_type'=>'agas_design','post_status'=>'private','post_author'=>$author,'post_modified'=>$modified];
 $GLOBALS['meta'][$id]=['_agas_product_id'=>$product,'_agas_template_id'=>$template,'_agas_preview'=>$preview,'_agas_options'=>['width'=>3,'height'=>2,'unit'=>'ft','double_sided'=>$id===301]];
}
function assert_ok($condition, $message) { if(!$condition)throw new RuntimeException('FAIL '.$message);echo 'PASS '.$message."\n"; }
function render_page(){ob_start();AGAS_Design_Studio::account_designs_page();return ob_get_clean();}
$GLOBALS['posts'][501]=(object)['ID'=>501,'post_type'=>'agas_template','post_status'=>'publish','post_title'=>'Custom Flag - Japan'];
$GLOBALS['posts'][502]=(object)['ID'=>502,'post_type'=>'agas_template','post_status'=>'publish','post_title'=>'Yard Sign - Open House'];
$GLOBALS['posts'][503]=(object)['ID'=>503,'post_type'=>'agas_template','post_status'=>'draft','post_title'=>'SECRET DRAFT'];
design_post(301,21,102,501,'2026-10-31 10:20:00','http://local.test/uploads/agas-design-301-test.png');
design_post(302,21,101,502,'2026-10-29 12:00:00');
design_post(303,22,103,501,'2026-10-31 10:00:00');
design_post(304,0,101,502,'2026-10-30 12:00:00');
require dirname(__DIR__).'/agas-design-studio.php';
AGAS_Design_Studio::register_account_endpoint();
assert_ok(($GLOBALS['endpoints']['my-designs']??null)===(EP_ROOT|EP_PAGES),'account endpoint registered');
AGAS_Design_Studio::maybe_flush_account_endpoint();
AGAS_Design_Studio::maybe_flush_account_endpoint();
assert_ok($GLOBALS['flushes']===1,'account rewrites refreshed only once');
$menu=AGAS_Design_Studio::account_menu_items(['dashboard'=>'Dashboard','orders'=>'Orders','edit-account'=>'Account details']);
assert_ok(array_keys($menu)===['dashboard','orders','my-designs','edit-account'],'My Designs follows Orders in account nav');
assert_ok(isset($GLOBALS['actions']['woocommerce_account_my-designs_endpoint']),'WooCommerce endpoint has its own renderer');
assert_ok(!str_contains(json_encode($GLOBALS['filters']['the_content'] ?? []), 'account_designs_content'),'old account-wide injected list removed');
$_GET=[];$html=render_page();
assert_ok(str_contains($html,'My Designs')&&str_contains($html,'Japan')&&str_contains($html,'Open House'),'saved cards render product, template, and metadata');
assert_ok(str_contains($html,'data-agas-preview=')&&str_contains($html,'Edit design'),'saved image previews and editor CTA render');
assert_ok(str_contains($html,'agas_from_account=1')&&!str_contains($html,'agas_token='),'account owner editor links do not expose guest token');
assert_ok(!str_contains($html,'#303')&&!str_contains($html,'#304'),'other user and anonymous designs are excluded');
$_GET=['agas_search'=>'Japan'];$html=render_page();
assert_ok(str_contains($html,'#301')&&!str_contains($html,'#302'),'template name search is scoped to owner');
$_GET=['agas_search'=>'#303'];$html=render_page();
assert_ok(str_contains($html,'No matching designs')&&!str_contains($html,'class="agas-account-card"'),'design ID search cannot read another owner');
$_GET=['agas_product'=>'101'];$html=render_page();
assert_ok(str_contains($html,'#302')&&!str_contains($html,'#301'),'product filtering works');
$_GET=['agas_search'=>'<script>alert(1)</script>'];$html=render_page();
assert_ok(!str_contains($html,'<script>'),'search input is sanitized and escaped');
$_GET=[];
for($n=305;$n<317;$n++) design_post($n,21,101,502,'2026-10-'.sprintf('%02d',1+($n-300)).' 10:00:00');
$html=render_page();assert_ok(str_contains($html,'Page 1 of 2')&&str_contains($html,'agas_page=2'),'pagination exposes next page');
$_GET=['agas_page'=>'2'];$html=render_page();assert_ok(str_contains($html,'Page 2 of 2')&&str_contains($html,'Previous'),'pagination exposes previous page');
$GLOBALS['user']=0;$_GET=[];$html=render_page();
assert_ok(str_contains($html,'Please sign in')&&!str_contains($html,'#301'),'signed-out users cannot view designs');
echo "ACCOUNT_DESIGNS_TESTS=PASS\n";

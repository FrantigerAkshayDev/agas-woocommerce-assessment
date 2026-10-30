/* Isolated editor state regression without a browser or WordPress installation. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const src = fs.readFileSync(path.join(__dirname, '../assets/studio.js'), 'utf8');
const start = src.indexOf('  async function saveDesign(cartMode) {');
const end = src.indexOf('  async function save(thenCart) {', start);
assert(start !== -1 && end > start, 'saveDesign function found');
const fn = src.slice(start,end);

async function scenario(mode, original) {
  let posts = [];
  let lastUrl = '';
  let lastMessage = '';
  const context = {
    cfg: { productId:101, returnToCart: !!original },
    originalCartDesign: original ? {id:40,token:'original-secret',cartKey:'cart-key'} : null,
    savedDesign: original ? {id:40,token:'original-secret'} : null,
    template: null,
    serializeAll:async()=>({front:{objects:[{type:'textbox',text:'CHANGED'}]},back:null,preview:'data:image/png;base64,AA=='}),
    getOptions:()=>({width:3,height:2,unit:'ft',double_sided:true}),
    postJson:async (route,payload)=>{
      assert.equal(route,'designs');
      posts.push(payload);
      const id=payload.design_id || 123;
      return {id, token: payload.token || 'independent-token',edit_url:'http://localhost:8099/design-studio/?agas_design='+id};
    },
    message:text=>{lastMessage=text;},
    window:{location:{href:'http://localhost:8099/design-studio/'},history:{replaceState:(_,__,url)=>lastUrl=url}},
    URL
  };
  vm.createContext(context);
  vm.runInContext(fn,context);
  const result = await context.saveDesign(mode);
  return {posts, result, lastUrl,lastMessage,original:context.originalCartDesign,saved:context.savedDesign};
}

(async()=>{
  const cloned = await scenario('add',true);
  assert.equal(cloned.posts[0].design_id,0);
  assert.equal(cloned.posts[0].token,'');
  assert.equal(cloned.saved.id,123);
  assert.equal(cloned.original,null);
  assert(!cloned.lastUrl.includes('agas_from_cart'));
  assert(cloned.lastMessage.includes('original cart item is unchanged'));
  console.log('PASS save separately creates independent design and exits cart edit context');

  const replaced = await scenario('replace',true);
  assert.equal(replaced.posts[0].design_id,40);
  assert.equal(replaced.posts[0].token,'original-secret');
  assert.equal(replaced.saved.id,40);
  assert(replaced.lastUrl.includes('agas_from_cart=1'));
  assert(replaced.lastUrl.includes('agas_cart_key=cart-key'));
  console.log('PASS replace uses original design and keeps cart edit context');

  const fresh = await scenario('auto',false);
  assert.equal(fresh.posts[0].design_id,0);
  assert.equal(fresh.saved.id,123);
  console.log('PASS fresh gallery design saves normally');

  // The actual choice dialog resolves to the clicked choice; cancellation cannot save.
  const startChoice = src.indexOf('  function chooseCartMode(thenCart) {');
  const endChoice = src.indexOf('  async function saveDesign(cartMode) {',startChoice);
  assert(startChoice > 0 && endChoice > startChoice);
  const choiceSource = src.slice(startChoice,endChoice);
  class FakeElement {
    constructor(id, doc){this.id=id;this.doc=doc;this.hidden=true;this.handlers={};this.textContent='';this.strong={textContent:''};}
    addEventListener(name,fn){(this.handlers[name] ||= []).push(fn);}
    removeEventListener(name,fn){this.handlers[name]=(this.handlers[name]||[]).filter(f=>f!==fn);}
    focus(){this.doc.activeElement=this;}
    querySelector(sel){assert.equal(sel,'strong');return this.strong;}
    click(){for(const fn of (this.handlers.click||[]))fn({target:this});}
  }
  const doc={activeElement:null, handlers:{},addEventListener(name,fn){(this.handlers[name] ||= []).push(fn);},removeEventListener(name,fn){this.handlers[name]=(this.handlers[name]||[]).filter(f=>f!==fn);}};
  const elems=Object.fromEntries(['agas-cart-choice','agas-choice-replace','agas-choice-add','agas-choice-cancel','agas-cart-choice-help'].map(x=>[x,new FakeElement(x,doc)]));
  const modeCtx={originalCartDesign:{id:40,token:'original-secret'},$:id=>elems[id],document:doc,Promise};
  vm.createContext(modeCtx);vm.runInContext(choiceSource,modeCtx);
  const pendingAdd=modeCtx.chooseCartMode(true);
  assert.equal(elems['agas-cart-choice'].hidden,false);
  assert.equal(elems['agas-choice-add'].strong.textContent,'Add as a separate cart item');
  elems['agas-choice-add'].click();
  assert.equal(await pendingAdd,'add');
  assert.equal(elems['agas-cart-choice'].hidden,true);
  console.log('PASS cart choice dialog explicitly chooses independent add');
  const pendingReplace=modeCtx.chooseCartMode(true);
  elems['agas-choice-replace'].click();
  assert.equal(await pendingReplace,'replace');
  console.log('PASS cart choice dialog explicitly chooses replace');
  const pendingCancel=modeCtx.chooseCartMode(false);
  elems['agas-choice-cancel'].click();
  assert.equal(await pendingCancel,null);
  console.log('PASS cart choice dialog cancel makes no save decision');
  console.log('CART_CHOICE_JS_TESTS=PASS');
})().catch(err=>{console.error(err);process.exitCode=1;});

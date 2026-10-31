'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const pluginDir = path.join(__dirname, '..');
const php = fs.readFileSync(path.join(pluginDir, 'agas-design-studio.php'),'utf8');
const studio = fs.readFileSync(path.join(pluginDir,'assets/studio.js'),'utf8');
const gallery = fs.readFileSync(path.join(pluginDir,'assets/gallery.js'),'utf8');
const css = fs.readFileSync(path.join(pluginDir,'assets/studio.css'),'utf8');
for (const term of ['agas-gallery-searchbox','agas-gallery-filters','agas-gallery-mockup','agas-card-pick','agas-selected-customize','agas-template-card','agas-gallery-empty']) assert(php.includes(term), term);
for (const term of ['agas-dual-preview','agas-preview-flat-image','agas-preview-mockup-image','agas-preview-side-front','agas-preview-side-back']) assert(php.includes(term), term);
for (const term of ['previewArt','showPreviewSide','scratch.loadFromJSON','previewRenderToken']) assert(studio.includes(term), term);
assert(css.includes('grid-template-columns:repeat(2,minmax(0,1fr))'));
console.log('PASS shortcode markup includes two-card gallery and selected product mockup');
console.log('PASS dual editor preview includes flat artwork, mockup, and front/back controls');
class FakeClassList {
  constructor(classes=[]){this.classes=new Set(classes);}
  contains(s){return this.classes.has(s);}
  add(s){this.classes.add(s);}
  remove(s){this.classes.delete(s);}
  toggle(s,state){state ? this.add(s) : this.remove(s);}
}
class FakeElement {
  constructor(fields={}){Object.assign(this,{dataset:{},hidden:false,value:'',textContent:'',src:'',href:'',style:{},events:{},attributes:{},classList:new FakeClassList()},fields);}
  addEventListener(e,fn){this.events[e]=fn;}
  dispatch(e,arg={}){assert(this.events[e], 'event registered: '+e); this.events[e](arg);}
  setAttribute(key,value){this.attributes[key]=value;}
  removeAttribute(key){delete this[key];}
  focus(){this.focused=true;}
  querySelector(selector){return selector === '.agas-card-pick'?this.pick:null;}
}
const records = [
  {name:'Grand Opening',category:'events',preview:'grand.png'},
  {name:'Flash Sale',category:'sale',preview:'sale.png'},
  {name:'Community Fundraiser',category:'community',preview:'fund.png'},
  {name:'Now Hiring',category:'business',preview:'hiring.png'},
];
const cards = records.map((data,i)=>new FakeElement({
  dataset:{search:(data.name+' '+data.category).toLowerCase(),name:data.name,category:data.category,preview:data.preview,background:'#294B72',editorUrl:'editor/'+(i+1)},
  classList:new FakeClassList(i===0?['agas-template-card','is-selected']:['agas-template-card']),
  pick:new FakeElement()
}));
const root=new FakeElement({querySelectorAll:s=>s==='.agas-template-card'?cards:[]});
const ids = {'agas-gallery':root,'agas-product-select':new FakeElement(), 'agas-category-filter':new FakeElement(), 'agas-gallery-search':new FakeElement(), 'agas-gallery-clear':new FakeElement(), 'agas-gallery-empty':new FakeElement(), 'agas-gallery-results':new FakeElement(), 'agas-gallery-mockup':new FakeElement(), 'agas-gallery-mockup-image':new FakeElement(), 'agas-gallery-mockup-fallback':new FakeElement(), 'agas-selected-name':new FakeElement(), 'agas-selected-category':new FakeElement(), 'agas-selected-customize':new FakeElement()};
let navigated='';
vm.runInNewContext(gallery,{
  document:{readyState:'complete',getElementById:id=>ids[id]||null},
  window:{location:{assign:url=>{navigated=url;}}},
  console
});
assert.equal(ids['agas-selected-name'].textContent,'Grand Opening');
assert.equal(ids['agas-selected-customize'].href,'editor/1');
assert.equal(ids['agas-gallery-mockup-image'].src,'grand.png');
assert.equal(ids['agas-gallery-results'].textContent.startsWith('4 designs'),true);
console.log('PASS initial selection drives left-hand mockup and editor CTA');
cards[1].pick.dispatch('click');
assert.equal(ids['agas-selected-name'].textContent,'Flash Sale');
assert.equal(cards[1].classList.contains('is-selected'),true);
assert.equal(cards[0].pick.attributes['aria-pressed'],'false');
console.log('PASS card click changes preview instead of opening editor');
ids['agas-gallery-search'].value='hiring';ids['agas-gallery-search'].dispatch('input');
assert.equal(cards.filter(c=>!c.hidden).length,1);
assert.equal(ids['agas-selected-name'].textContent,'Now Hiring');
console.log('PASS live search filters titles and automatically selects a visible design');
ids['agas-gallery-search'].value='';ids['agas-gallery-search'].dispatch('input');
ids['agas-category-filter'].value='events';ids['agas-category-filter'].dispatch('change');
assert.equal(cards.filter(c=>!c.hidden).length,1);
assert.equal(ids['agas-selected-name'].textContent,'Grand Opening');
console.log('PASS category filtering applies selected product-specific cards');
ids['agas-gallery-search'].value='not in gallery';ids['agas-gallery-search'].dispatch('input');
assert.equal(cards.filter(c=>!c.hidden).length,0);
assert.equal(ids['agas-gallery-empty'].hidden,false);
assert.equal(ids['agas-selected-customize'].hidden,true);
console.log('PASS no-results state hides stale Customize CTA');
ids['agas-gallery-clear'].dispatch('click');
assert.equal(ids['agas-gallery-search'].value,'');
assert.equal(ids['agas-gallery-empty'].hidden,true);
assert.equal(ids['agas-gallery-search'].focused,true);
console.log('PASS clear search restores results without a page reload');
ids['agas-product-select'].value='https://test.example/?agas_product=102';
ids['agas-product-select'].dispatch('change');
assert.equal(navigated,'https://test.example/?agas_product=102');
console.log('PASS product selector uses the assigned product gallery URL');
console.log('GALLERY_PREVIEW_TESTS=PASS');

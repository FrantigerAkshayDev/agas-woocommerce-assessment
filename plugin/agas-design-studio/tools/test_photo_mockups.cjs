'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const path=require('node:path');
const base=path.resolve(__dirname,'..');
const php=fs.readFileSync(path.join(base,'agas-design-studio.php'),'utf8');
const gallery=fs.readFileSync(path.join(base,'assets/gallery.js'),'utf8');
const editor=fs.readFileSync(path.join(base,'assets/studio.js'),'utf8');
const styles=fs.readFileSync(path.join(base,'assets/studio.css'),'utf8');
const renderer=fs.readFileSync(path.join(base,'assets/realistic-mockups.js'),'utf8');
assert.match(php,/const VERSION = '0\.1\.16';/);
assert.match(php,/agas-realistic-mockups/);
assert.match(php,/data-mockup-photo/);
assert.match(php,/data-mockup-kind/);
assert.match(gallery,/AGASRealisticMockups\.render\(preview, source\)/);
assert.match(editor,/AGASRealisticMockups\.render\(\$\('agas-editor-mockup'\), url\)/);
assert.match(styles,/\.agas-mockup-photo-ready \.agas-mockup-photo-canvas/);
assert.match(php,/agas-preview-flat-image/);
for(const product of ['banner','flag','sign']) {
  const photo=path.join(base,'assets/mockups/photos',product+'.jpg');
  assert(fs.statSync(photo).size>10000,product+' photo present');
}
console.log('PASS three photographic scenes are bundled and both gallery and editor use the shared renderer');

class Cls {constructor(){this.v=new Set()} add(x){this.v.add(x)} remove(x){this.v.delete(x)} contains(x){return this.v.has(x)}}
class Canvas {
  constructor(){this.context=new Context();this.width=300;this.height=200;this.context.canvas=this;}
  getContext(){return this.context;}
  setAttribute(){}
}
class Context {
  constructor(){this.draws=[];}
  save(){}restore(){}beginPath(){}moveTo(){}lineTo(){}closePath(){}clip(){}
  transform(){}translate(){}rotate(){}bezierCurveTo(){}quadraticCurveTo(){}
  stroke(){}fill(){}fillRect(){}arc(){}
  drawImage(im){this.draws.push(im.src||'[canvas]');}
  createLinearGradient(){return {addColorStop(){}};}
}
class MockImage {
  constructor(){this.naturalWidth=720;this.naturalHeight=480;this.width=720;this.height=480;}
  set src(val){this._source=val;Promise.resolve().then(()=>{if(val.includes('missing'))this.onerror(new Error('missing'));else this.onload();});}
  get src(){return this._source;}
}
class Stage {
  constructor(kind){this.attrs={'data-mockup-photo':'/assets/'+kind+'.jpg','data-mockup-kind':kind};this.classList=new Cls();this.canvas=null;}
  getAttribute(k){return this.attrs[k]||null;}
  querySelector(sel){return sel==='.agas-mockup-photo-canvas'?this.canvas:null;}
  appendChild(c){this.canvas=c;}
}
const host={console};
const context={window:host,console,Promise,WeakMap,Image:MockImage,document:{createElement(type){assert.equal(type,'canvas');return new Canvas();}}};
vm.runInNewContext(renderer,context);
async function main(){
 for(const kind of ['banner','flag','sign']){
  const stage=new Stage(kind);
  assert.equal(await host.AGASRealisticMockups.render(stage,kind+'-design-1.png'),true);
  assert(stage.classList.contains('agas-mockup-photo-ready'));
  const first=stage.canvas.context.draws;
  assert(first.includes(kind==='flag'?'[canvas]':kind+'-design-1.png'));
  assert(first.length>50,'mesh draws multiple mapped triangles');
  assert.equal(await host.AGASRealisticMockups.render(stage,kind+'-design-2.png'),true);
  assert(stage.canvas.context.draws.includes(kind==='flag'?'[canvas]':kind+'-design-2.png'));
  assert.equal(await host.AGASRealisticMockups.render(stage,''),false);
  assert(!stage.classList.contains('agas-mockup-photo-ready'));
  console.log('PASS '+kind+' artwork changes, live render and fallback');
 }
 const stage=new Stage('banner');
 assert.equal(await host.AGASRealisticMockups.render(stage,'missing-art.png'),false);
 assert(!stage.classList.contains('agas-mockup-photo-ready'));
 console.log('PASS missing images fall back without blocking other customer actions');
 console.log('REALISTIC_MOCKUP_TESTS=PASS');
}
main().catch(e=>{console.error(e);process.exitCode=1;});

/* v0.1.15 product + category + physical-subtype routing and asset test. */
'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');const vm=require('node:vm');const path=require('node:path');
const root=path.resolve(__dirname,'..');
const code=fs.readFileSync(path.join(root,'assets/realistic-mockups.js'),'utf8');
const php=fs.readFileSync(path.join(root,'agas-design-studio.php'),'utf8');
const gallery=fs.readFileSync(path.join(root,'assets/gallery.js'),'utf8');
const editor=fs.readFileSync(path.join(root,'assets/studio.js'),'utf8');
const preset=JSON.parse(fs.readFileSync(path.join(root,'assets/seed-presets.json'),'utf8'));
const dom={createElement(){throw Error('Unexpected canvas creation during routing test')}};
const context={document:dom,window:{},WeakMap,Promise};
vm.runInNewContext(code,context);
const R=context.window.AGASRealisticMockups;
assert.match(php,/const VERSION = '0\.1\.18';/);
assert.match(php,/_agas_mockup_profile/);
assert.match(php,/data-mockup-profile/);
assert.match(php,/agas-template-mockup/);
assert.match(gallery,/AGASRealisticMockups\.configure\(preview/);
assert.match(editor,/choosePreviewMockup\(\)/);
assert.match(editor,/mockup_profile:\$\('agas-template-mockup'\)\.value/);
let count=0;
for (const [product,items] of Object.entries(preset)) {
 const kind=/flag/.test(product)?'flag':/sign/.test(product)?'sign':'banner';
 for (const entry of items) {
  const profile=R.resolve(kind,entry.category,entry.name);
  assert.equal(profile,entry.mockup_profile,`profile ${entry.key}`);
  assert.equal(R.scenes[profile].kind,kind,`product-compatible profile ${entry.key}`);
  count++;
 }
}
assert.equal(count,18);
console.log('PASS all 18 product-specific starter templates route to expected physical mockups');
assert.equal(R.resolve('flag','Country Flags','Japan Flag'),'flag-rectangular');
assert.equal(R.resolve('flag','Business','Company Brand Flag'),'flag-feather');
assert.equal(R.resolve('banner','Events','Music Festival'),'banner-event');
assert.equal(R.resolve('banner','Sale','Flash Sale'),'banner-storefront');
assert.equal(R.resolve('sign','Real Estate','For Sale'),'sign-lawn');
assert.equal(R.resolve('sign','Directions','Reserved Parking'),'sign-parking');
assert.equal(R.resolve('sign','Events','Event Entrance'),'sign-event');
assert.equal(R.resolve('flag','Business','Company Flag','flag-rectangular'),'flag-rectangular');
assert.equal(R.resolve('sign','Directions','Reserved Parking','flag-rectangular'),'sign-parking');
assert.equal(R.resolve('banner','Unknown','Custom'),'banner-storefront');
assert.equal(R.resolve('flag','Unknown','Custom'),'flag-feather');
assert.equal(R.resolve('sign','Unknown','Custom'),'sign-lawn');
console.log('PASS explicit override, invalid cross-product override, and safe product fallbacks');
const country=R.scenes['flag-rectangular'];
assert(country.quad && !country.feather);
const w=country.quad[1][0]-country.quad[0][0];
const h=country.quad[3][1]-country.quad[0][1];
assert(w/h>1.35 && w/h<1.75,'country flag aspect ratio preserves circle shapes');
assert(R.scenes['flag-feather'].feather);
for (const [name,scene] of Object.entries(R.scenes)) {
 const f=path.join(root,'assets/mockups/photos',scene.photo);
 assert(fs.statSync(f).size>20000,`${name} photograph exists`);
}
console.log('PASS all 7 physical scene files, including true rectangular country flag geometry');
class Stage{
 constructor(kind){this.attrs={'data-mockup-kind':kind,'data-mockup-base':'/photo/'};}
 setAttribute(key,value){this.attrs[key]=value;}
 getAttribute(key){return this.attrs[key]||'';}
}
const stage=new Stage('flag');
assert.equal(R.configure(stage,{kind:'flag',category:'Country Flags',name:'France Flag'}).profile,'flag-rectangular');
assert.equal(stage.getAttribute('data-mockup-photo'),'/photo/flag-rectangular.jpg');
assert.equal(R.configure(stage,{kind:'flag',category:'Business',name:'Company Brand'}).profile,'flag-feather');
assert.equal(stage.getAttribute('data-mockup-photo'),'/photo/flag.jpg');
console.log('PASS switching categories changes photo scene on the same gallery stage');
console.log('MOCKUP_PROFILE_TESTS=PASS');

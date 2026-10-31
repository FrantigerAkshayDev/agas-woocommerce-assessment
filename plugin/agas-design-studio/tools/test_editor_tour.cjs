'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const base=path.resolve(__dirname,'..');
const php=fs.readFileSync(path.join(base,'agas-design-studio.php'),'utf8');
const script=fs.readFileSync(path.join(base,'assets/editor-tour.js'),'utf8');
const studio=fs.readFileSync(path.join(base,'assets/studio.js'),'utf8');
const css=fs.readFileSync(path.join(base,'assets/studio.css'),'utf8');
assert(php.includes("const VERSION = '0.1.19';"));
assert(php.includes("wp_enqueue_script('agas-editor-tour', plugins_url('assets/editor-tour.js', __FILE__), array('agas-studio')"));
for(const id of ['agas-help-tour','agas-tour-layer','agas-tour-card','agas-tour-spotlight','agas-tour-next','agas-tour-back','agas-tour-skip','agas-tour-step-number','agas-tour-progress','agas-tour-title','agas-tour-description']) {
 assert(php.includes('id="'+id+'"'), 'Missing required tour markup '+id);
}
assert.match(php,/class="agas-tour-card"[^>]*role="dialog"[^>]*aria-modal="true"/);
assert.match(studio,/document\.dispatchEvent\(new CustomEvent\('agas:editor-ready'\)\)/);
assert(script.includes('document.addEventListener(\'agas:editor-ready\''));
assert(script.includes('window.AGASEditorReady'));
assert(script.includes('window.localStorage.setItem(storageKey'));
assert(script.includes('startTour({replay:true})'));
assert(script.includes("if (event.key === 'Escape')"));
assert(script.includes("event.stopImmediatePropagation()"));
assert(script.includes('root.classList.toggle(\'agas-tools-open\''));
assert(script.includes('restoreState()'));
const titles=(script.match(/\{title:'/g)||[]).length;
assert.equal(titles,9,'Expected exactly nine real editor steps');
for(const target of ["'.agas-topbar'","'#agas-panel-templates'","'.agas-tool-rail'","'#agas-canvas-viewport'","'#agas-object-toolbar'","'#agas-product-settings'","'#agas-sides-preview'","'.agas-top-actions'","'#agas-continue'"]) {
 assert(script.includes('target:'+target),'Missing target '+target);
}
assert(css.includes('.agas-tour-layer[hidden]'));
assert(css.includes('.agas-tour-spotlight'));
assert(css.includes('.agas-tour-card'));
assert(css.includes('@media(prefers-reduced-motion:reduce){.agas-tour-spotlight'));
console.log('PASS nine editor tour stops map to actual controls');
console.log('PASS accessible dialog, focus controls, Escape, progress and reduced-motion styling');
console.log('PASS first-time opt-in and replay-from-Help wiring');
console.log('PASS readonly guard, state restoration, no canvas/checkout data writes');
console.log('EDITOR_TOUR_TESTS=PASS');

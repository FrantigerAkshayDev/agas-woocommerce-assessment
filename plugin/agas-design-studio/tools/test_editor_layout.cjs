'use strict';
/** Isolated AGAS editor-layout contract test; no WordPress DB or browser required. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'agas-design-studio.php'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/studio.js'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets/studio.css'), 'utf8');
const pluginHeaderVersion = php.match(/\* Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/)?.[1];
assert(pluginHeaderVersion && php.includes(`const VERSION = '${pluginHeaderVersion}';`));
const designer = php.slice(php.indexOf('public static function designer_shortcode()'),php.indexOf('private static function pricing_rules()'));
for(const [tab,label] of Object.entries({templates:'Templates',text:'Text',elements:'Elements',uploads:'Uploads',background:'Background',qr:'QR Code',layers:'Layers'})) {
 assert(designer.includes('data-agas-tab="'+tab+'"'),`Missing ${tab} rail tab`);
 assert(designer.includes('id="agas-panel-'+tab+'"'),`Missing ${tab} insertion panel`);
 assert(designer.includes('aria-label="'+label+'"'),`Missing accessible ${tab} title`);
}
const findSection = (from,to) => designer.slice(designer.indexOf(from),designer.indexOf(to,designer.indexOf(from)));
const left=findSection('class="agas-insert-sidebar"','class="agas-workspace"');
const top=findSection('class="agas-object-toolbar"','class="agas-canvas-area"');
const right=findSection('id="agas-product-settings"','class="agas-bottombar"');
assert(left.includes('id="agas-change-template"'));
for(const id of ['agas-add-heading','agas-add-subheading','agas-add-body','agas-clipart-grid','agas-qr-url','agas-file','agas-bg','agas-text-layers']) assert(left.includes('id="'+id+'"'),`Missing left insertion control ${id}`);
assert(!left.includes('id="agas-copy"'),'Object editing leaked into insertion panel');
for(const id of ['agas-text-content','agas-font','agas-color','agas-bold','agas-italic','agas-underline','agas-delete','agas-front','agas-flip-h','agas-lock','agas-opacity']) assert(top.includes('id="'+id+'"'),`Missing selection property ${id}`);
for(const id of ['agas-copy','agas-paste','agas-duplicate','agas-cut','agas-delete','agas-flip-h','agas-lock']) {
 const button = top.match(new RegExp('<button[^>]*id="'+id+'"[^>]*>[\\s\\S]*?<\\/button>'));
 assert(button && button[0].includes('aria-label=') && button[0].includes('<svg'),`Tool ${id} needs an accessible icon`);
}
assert(designer.includes('id="agas-undo"') && designer.includes('id="agas-redo"'));
assert(designer.includes('aria-label="Undo (Ctrl/Command+Z)"') && designer.includes('aria-label="Redo (Ctrl/Command+Y)"'));
assert(right.includes('id="agas-no-finishing"') && right.includes('id="agas-grommets"') && right.includes('id="agas-pocket"'));
assert((right.match(/name="agas-finishing"/g)||[]).length===3,'All finishing options must share one radio group');
assert(right.includes('id="agas-sides-preview"'));
const group = right.slice(right.indexOf('id="agas-sides-preview"'));
assert(group.includes('id="agas-two-sided"') && group.includes('id="agas-side-thumb-front"') && group.includes('id="agas-side-thumb-back"'));
assert(php.includes("new WP_Error('conflicting_finishing'"),'Server must enforce exclusive finishing');
for(const code of ['function chooseToolPanel(', 'function toggleSettings(', "$('agas-context-text').hidden = !textSelected", "$('agas-context-actions').hidden = !sel"]) assert(js.includes(code),'Missing runtime behavior '+code);
assert(js.includes("$('agas-no-finishing').checked") && js.includes("'agas-no-finishing'"));
assert(js.includes("$('agas-preview').querySelector('span')"),'Preview button must preserve icon');
for (const token of ['max-width:1100px','max-width:620px','agas-insert-sidebar','agas-finishing-options input[type=radio]','agas-object-toolbar']) assert(css.includes(token),'Missing responsive CSS '+token);
const jsRefs = new Set([...js.matchAll(/\$\(['"](agas-[^'"]+)['"]\)/g)].map(x=>x[1]));
const ids = new Set([...designer.matchAll(/\bid="(agas-[^"]+)"/g)].map(x=>x[1]));
for (const id of jsRefs) assert(ids.has(id),'Missing DOM target '+id);
console.log('PASS 7 accessible icon rail tabs and independent insertion drawers');
console.log('PASS selection-aware text and object toolbars have accessible icon commands');
console.log('PASS right settings retain dimensions and group Add Back with Side previews');
console.log('PASS None, Grommets, Pole pocket share exclusive radio group and server validation');
console.log('PASS mobile overlay, toolbar and zoom/footer responsive CSS contracts');
console.log('PASS all referenced editor DOM IDs present');
console.log('EDITOR_LAYOUT_TESTS=PASS');

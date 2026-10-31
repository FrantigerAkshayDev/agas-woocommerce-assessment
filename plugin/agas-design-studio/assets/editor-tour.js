/**
 * AGAS Design Studio beginner tour (v0.1.19).
 * Only presents UI guidance. Never modifies the Fabric canvas or submits data.
 * Persist a small, non-identifying completion flag in this browser only.
 */
(function () {
  'use strict';
  var root = document.getElementById('agas-designer-root');
  var layer = document.getElementById('agas-tour-layer');
  var cfg = window.AGAS_DS;
  if (!root || !layer || !cfg) return;

  var $ = function (id) { return document.getElementById(id); };
  var spotlight = $('agas-tour-spotlight');
  var card = $('agas-tour-card');
  var title = $('agas-tour-title');
  var description = $('agas-tour-description');
  var number = $('agas-tour-step-number');
  var progress = $('agas-tour-progress');
  var next = $('agas-tour-next');
  var back = $('agas-tour-back');
  var skip = $('agas-tour-skip');
  var storageKey = 'agas_design_studio_tour_v1_seen';
  var active = false;
  var stepIndex = 0;
  var savedState = null;
  var lastFocus = null;
  var positionFrame = 0;
  var settleTimer = null;
  var ready = false;
  var startedAutomatically = false;
  var layerCount = 0;

  // The targets are existing, stable editor controls; no dummy UI is added.
  var steps = [
    {title:'Welcome to your design workspace', text:'This is where you turn a ready-made template into your own print. Your product and main actions stay at the top.', target:'.agas-topbar', mode:'closed'},
    {title:'Browse and switch templates', text:'Choose Templates on the left to search designs for this product. Select one to switch; we will ask before replacing your artwork.', target:'#agas-panel-templates', mode:'tools', tool:'templates'},
    {title:'Add text, images and graphics', text:'Use the left icons to add headings, shapes, uploaded logos, backgrounds, QR codes and layers. Each icon opens its own tool panel.', target:'.agas-tool-rail', mode:'closed'},
    {title:'Make it yours on the canvas', text:'Drag items on your design, resize them with the handles, and select a text element to change its wording. Your safety guides do not appear in print exports.', target:'#agas-canvas-viewport', mode:'closed'},
    {title:'Edit with the contextual toolbar', text:'Select any element. The toolbar above your canvas shows the right actions for that selection: fonts for text, and arrange or transform tools for other objects.', target:'#agas-object-toolbar', mode:'closed'},
    {title:'Choose size, units and finishing', text:'Product Setup controls your dimensions and the live price. On mobile and iPad, open it using the gear icon in the top-right corner.', target:'#agas-product-settings', mode:'settings'},
    {title:'Design both front and back', text:'In Side Previews, turn on Add Back if you need two-sided printing. Switch between Front and Back thumbnails to work on each side.', target:'#agas-sides-preview', mode:'settings', scroll:true},
    {title:'Save and inspect your design', text:'Use the disk icon to save, the eye icon to preview the flat artwork and realistic mockup, and the curved arrows to undo or redo changes.', target:'.agas-top-actions', mode:'closed'},
    {title:'Continue when you are ready', text:'Check the live price, then use Continue to Cart. You can return from the cart to edit your design again. Replay this tour any time from Need Help.', target:'#agas-continue', mode:'closed'}
  ];
  if (cfg.adminTemplateMode) {
    steps[steps.length - 1].title = 'Save and publish your template';
    steps[steps.length - 1].text = 'Review your artwork and choose Save Template. You can set its product assignment and publication status before it appears in the customer gallery.';
  }

  function hasSeen() {
    try { return window.localStorage.getItem(storageKey) === '1'; }
    catch (e) { return false; }
  }
  function markSeen() {
    try { window.localStorage.setItem(storageKey, '1'); }
    catch (e) { /* Browser may block local storage; tour remains optional. */ }
  }
  function currentActiveTab() {
    var tab = root.querySelector('[data-agas-tab].is-active');
    return tab ? tab.getAttribute('data-agas-tab') : '';
  }
  function settingsOpen() { return root.classList.contains('agas-settings-open'); }
  function toolsOpen() { return root.classList.contains('agas-tools-open'); }
  function setSettings(show) {
    var toggle = $('agas-settings-toggle');
    if (settingsOpen() !== !!show && toggle && !toggle.disabled) toggle.click();
  }
  function setTools(tabName) {
    setSettings(false);
    var tab = root.querySelector('[data-agas-tab="' + tabName + '"]');
    if (tab && !tab.disabled && (!toolsOpen() || currentActiveTab() !== tabName)) tab.click();
  }
  function resetPanels() {
    setSettings(false);
    root.classList.remove('agas-tools-open');
  }
  function preserveState() {
    lastFocus = document.activeElement;
    savedState = {tools:toolsOpen(), settings:settingsOpen(), tab:currentActiveTab()};
  }
  function restoreState() {
    if (!savedState) return;
    var state = savedState;
    savedState = null;
    if (state.tab && state.tab !== currentActiveTab()) {
      var tab = root.querySelector('[data-agas-tab="' + state.tab + '"]');
      if (tab && !tab.disabled) tab.click();
    }
    if (state.settings) setSettings(true);
    else setSettings(false);
    root.classList.toggle('agas-tools-open', state.tools && !state.settings);
    if (lastFocus && document.contains(lastFocus) && !lastFocus.disabled && typeof lastFocus.focus === 'function') {
      lastFocus.focus({preventScroll:true});
    }
  }
  function isInsideViewport(rect) {
    return rect.bottom > 0 && rect.top < window.innerHeight && rect.right > 0 && rect.left < window.innerWidth;
  }
  function prepareStep(step) {
    if (step.mode === 'tools') setTools(step.tool);
    else if (step.mode === 'settings') {
      root.classList.remove('agas-tools-open');
      setSettings(true);
    } else resetPanels();
    if (step.scroll) {
      // Only scroll inside the product-settings panel, never the whole editor.
      var settings = $('agas-product-settings');
      var target = root.querySelector(step.target);
      if (settings && target) {
        var targetTop = target.offsetTop - settings.offsetTop;
        if (!isInsideViewport(target.getBoundingClientRect())) {
          settings.scrollTop = Math.max(0, targetTop - settings.clientHeight * .28);
        }
      }
    }
  }
  function rectForStep() {
    var step = steps[stepIndex];
    var el = root.querySelector(step.target);
    var w = Math.max(window.innerWidth || 0, 240);
    var h = Math.max(window.innerHeight || 0, 320);
    if (!el) return {left:w*.22,top:h*.2,right:w*.78,bottom:h*.7,width:w*.56,height:h*.5};
    var rect = el.getBoundingClientRect();
    // Keep the ring inside the visible viewport; panels may scroll or animate.
    if (!isInsideViewport(rect)) {
      return {left:w*.22,top:h*.25,right:w*.78,bottom:h*.75,width:w*.56,height:h*.5};
    }
    var left = Math.max(5,Math.min(w - 25,rect.left - 5));
    var top = Math.max(5,Math.min(h - 25,rect.top - 5));
    var right = Math.min(w - 5,Math.max(left + 20,rect.right + 5));
    var bottom = Math.min(h - 5,Math.max(top + 20,rect.bottom + 5));
    return {left:left,top:top,right:right,bottom:bottom,width:right-left,height:bottom-top};
  }
  function position() {
    if (!active) return;
    var rect = rectForStep();
    var viewportW = window.innerWidth;
    var viewportH = window.innerHeight;
    spotlight.style.left = rect.left + 'px';
    spotlight.style.top = rect.top + 'px';
    spotlight.style.width = rect.width + 'px';
    spotlight.style.height = rect.height + 'px';
    // Match the current card dimensions after updating its content.
    var cardW = Math.min(360, viewportW - 24);
    card.style.width = cardW + 'px';
    card.style.maxHeight = 'min(80dvh, ' + Math.max(165, viewportH - 24) + 'px)';
    var cardH = Math.min(card.getBoundingClientRect().height || 245, viewportH - 24);
    var spaceBelow = viewportH - rect.bottom - 12;
    var spaceAbove = rect.top - 12;
    var y, x;
    if (viewportW >= 850 && viewportW - rect.right >= cardW + 30) {
      x = rect.right + 18;
      y = Math.max(12,Math.min(viewportH - cardH - 12,rect.top));
    } else if (viewportW >= 850 && rect.left >= cardW + 30) {
      x = rect.left - cardW - 18;
      y = Math.max(12,Math.min(viewportH - cardH - 12,rect.top));
    } else if (spaceBelow >= cardH + 14) {
      x = Math.max(12,Math.min(viewportW - cardW - 12,rect.left + rect.width/2 - cardW/2));
      y = rect.bottom + 14;
    } else if (spaceAbove >= cardH + 14) {
      x = Math.max(12,Math.min(viewportW - cardW - 12,rect.left + rect.width/2 - cardW/2));
      y = rect.top - cardH - 14;
    } else {
      // When the focused element fills the screen (canvas/mobile drawer),
      // put the card in the half with more unoccupied space.
      x = Math.max(12,(viewportW - cardW)/2);
      y = rect.top < viewportH/2 ? Math.max(12,viewportH - cardH - 12) : 12;
    }
    card.style.left = Math.round(x) + 'px';
    card.style.top = Math.round(Math.max(12,Math.min(viewportH - cardH - 12,y))) + 'px';
    card.style.bottom = 'auto';
  }
  function schedulePosition() {
    if (!active || positionFrame) return;
    positionFrame = window.requestAnimationFrame(function(){positionFrame=0;position();});
  }
  function paintStep() {
    if (!active) return;
    var step = steps[stepIndex];
    prepareStep(step);
    title.textContent = step.title;
    description.textContent = step.text;
    number.textContent = 'Step ' + (stepIndex + 1) + ' of ' + steps.length;
    back.disabled = stepIndex === 0;
    back.hidden = stepIndex === 0;
    next.innerHTML = stepIndex === steps.length - 1 ? 'Finish tour <span aria-hidden="true">✓</span>' : 'Next <span aria-hidden="true">→</span>';
    progress.textContent = '';
    steps.forEach(function(_,i) {
      var part = document.createElement('span');
      part.className = 'agas-tour-progress-segment' + (i <= stepIndex ? ' is-complete' : '');
      part.setAttribute('aria-hidden','true');
      progress.appendChild(part);
    });
    position();
    if (settleTimer) window.clearTimeout(settleTimer);
    settleTimer = window.setTimeout(function(){if(active) position();},280);
    next.focus({preventScroll:true});
  }
  function startTour(options) {
    options = options || {};
    if (active || cfg.readonly) return false;
    var help = $('agas-help-overlay');
    var fromHelp = !!(help && !help.hidden);
    if (fromHelp) help.hidden = true;
    preserveState();
    // On replay, return keyboard focus to the visible Need Help control,
    // never to the now-hidden button inside the help dialog.
    if (fromHelp) lastFocus = $('agas-help-open');
    stepIndex = 0;
    active = true;
    layer.hidden = false;
    layerCount++;
    paintStep();
    return true;
  }
  function endTour() {
    if (!active) return;
    active = false;
    layer.hidden = true;
    markSeen();
    if (settleTimer) window.clearTimeout(settleTimer);
    if (positionFrame) window.cancelAnimationFrame(positionFrame);
    settleTimer=positionFrame=0;
    restoreState();
  }
  function forward() { if(stepIndex < steps.length - 1) {stepIndex++;paintStep();} else endTour(); }
  function backward() { if(stepIndex > 0) {stepIndex--;paintStep();} }
  function onKeyDown(event) {
    if (!active) return;
    if (event.key === 'Escape') {event.preventDefault();event.stopImmediatePropagation();endTour();return;}
    if (event.key === 'ArrowRight') {event.preventDefault();event.stopImmediatePropagation();forward();return;}
    if (event.key === 'ArrowLeft') {event.preventDefault();event.stopImmediatePropagation();backward();return;}
    if (event.key !== 'Tab') return;
    var focusable = Array.prototype.slice.call(card.querySelectorAll('button:not([disabled]):not([hidden])'));
    if (!focusable.length) return;
    var first = focusable[0], last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {event.preventDefault();last.focus();}
    else if (!event.shiftKey && document.activeElement === last) {event.preventDefault();first.focus();}
  }
  next.addEventListener('click',forward);
  back.addEventListener('click',backward);
  skip.addEventListener('click',endTour);
  document.addEventListener('keydown',onKeyDown,true);
  window.addEventListener('resize',schedulePosition,{passive:true});
  window.addEventListener('orientationchange',schedulePosition,{passive:true});
  document.addEventListener('scroll',schedulePosition,true);
  var helpTour = $('agas-help-tour');
  if (helpTour) helpTour.addEventListener('click',function(){startTour({replay:true});});

  function launchFirstTime() {
    if (startedAutomatically || cfg.readonly || cfg.adminTemplateMode || cfg.loadedDesign || hasSeen()) return;
    startedAutomatically = true;
    window.setTimeout(function () {
      if (!active && !hasSeen() && !root.classList.contains('agas-preview') && (!$('agas-help-overlay') || $('agas-help-overlay').hidden)) {
        startTour({replay:false});
      }
    },600);
  }
  document.addEventListener('agas:editor-ready',function(){ready=true;launchFirstTime();});
  if (window.AGASEditorReady) {ready=true;launchFirstTime();}

  // Stable exposed API supports independent tests without any canvas dependency.
  window.AGASEditorTour = {start:startTour,stop:endTour,get active(){return active;},get step(){return stepIndex;},get length(){return steps.length;},get launches(){return layerCount;}};
})();

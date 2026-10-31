(function () {
  'use strict';
  var cfg = window.AGAS_DS;
  var root = document.getElementById('agas-designer-root');
  if (!cfg || !root) return;
  var $ = function (id) { return document.getElementById(id); };
  var status = $('agas-status');
  function message(text, isError) {
    status.textContent = text;
    status.classList.toggle('agas-error', !!isError);
  }
  if (typeof fabric === 'undefined') {
    message('Fabric.js did not load. Check your internet connection and retry.', true);
    return;
  }

  var canvas = new fabric.Canvas('agas-canvas', {
    preserveObjectStacking: true, selection: true, backgroundColor: '#ffffff',
    enableRetinaScaling: true
  });
  var savedDesign = cfg.loadedDesign || null;
  var originalCartDesign = cfg.returnToCart && cfg.loadedDesign
    ? {id: cfg.loadedDesign.id, token: cfg.loadedDesign.token, cartKey: cfg.cartEditKey || ''}
    : null;
  var template = cfg.template || null;
  var sides = {front: null, back: null};
  var currentSide = 'front';
  var isLoading = false;
  var dirty = false;
  var viewing = false;
  var history = [];
  var historyAt = -1;
  var internalClipboard = null;
  var pasteCount = 0;
  var quoteTimer = null;
  var rules = cfg.rules;
  var originalCanvas = {width: 900, height: 600};
  $('agas-back-price-hint').textContent = 'Add Back for $' + Number(rules.back || 0).toFixed(2);

  /** The compact rail controls insertion panels only; editing properties remain above the canvas. */
  var railTabs = Array.prototype.slice.call(root.querySelectorAll('[data-agas-tab]'));
  var toolPanels = Array.prototype.slice.call(root.querySelectorAll('.agas-tool-panel'));
  var toolSidebar = root.querySelector('.agas-insert-sidebar');
  var settingsToggle = $('agas-settings-toggle');
  function chooseToolPanel(name, focusPanel) {
    var chosen = railTabs.find(function(tab) {return tab.dataset.agasTab === name;});
    if (!chosen) return;
    railTabs.forEach(function (tab) {
      var selected = tab === chosen;
      tab.classList.toggle('is-active', selected);
      tab.setAttribute('aria-selected', String(selected));
      tab.tabIndex = selected ? 0 : -1;
    });
    toolPanels.forEach(function (panel) {
      panel.hidden = panel.id !== 'agas-panel-' + name;
    });
    root.classList.add('agas-tools-open');
    if (focusPanel) {
      var first = $('agas-panel-' + name).querySelector('button:not([disabled]), input, select, a');
      if (first) first.focus();
    }
  }
  railTabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      // On narrow viewports tapping the selected tab dismisses its drawer.
      if (window.innerWidth < 1100 && tab.classList.contains('is-active') && root.classList.contains('agas-tools-open')) {
        root.classList.remove('agas-tools-open');
        return;
      }
      chooseToolPanel(tab.dataset.agasTab, false);
    });
    tab.addEventListener('keydown', function (event) {
      if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
      event.preventDefault();
      var index = railTabs.indexOf(tab);
      var next = railTabs[(index + (event.key === 'ArrowDown' ? 1 : -1) + railTabs.length) % railTabs.length];
      chooseToolPanel(next.dataset.agasTab, false);
      next.focus();
    });
  });
  root.querySelectorAll('.agas-drawer-dismiss').forEach(function(button) {
    button.addEventListener('click', function(){root.classList.remove('agas-tools-open');});
  });
  function toggleSettings(show) {
    root.classList.toggle('agas-settings-open', !!show);
    settingsToggle.setAttribute('aria-expanded', String(!!show));
    if (show) root.classList.remove('agas-tools-open');
  }
  settingsToggle.addEventListener('click', function () {
    toggleSettings(!root.classList.contains('agas-settings-open'));
  });
  $('agas-settings-close').addEventListener('click', function () {toggleSettings(false);settingsToggle.focus();});
  document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && !viewing) {
      if (root.classList.contains('agas-settings-open')) {toggleSettings(false);settingsToggle.focus();}
      else if (window.innerWidth < 1100 && root.classList.contains('agas-tools-open')) {root.classList.remove('agas-tools-open');}
    }
  });
  if (window.innerWidth < 1100) root.classList.remove('agas-tools-open');
  else root.classList.add('agas-tools-open');

  var canvasArea = root.querySelector('.agas-canvas-area');
  var canvasViewport = $('agas-canvas-viewport');
  var canvasShell = $('agas-canvas-shell');
  var zoomMultiplier = 1;
  var fitRaf = null;

  /** Fit the visual canvas into its workspace without changing saved coordinates. */
  function fitCanvas() {
    if (!canvasArea || !canvasViewport || !canvasShell) return;
    var width = canvas.getWidth(), height = canvas.getHeight();
    var roomW = Math.max(180, canvasArea.clientWidth - 42);
    var roomH = Math.max(180, canvasArea.clientHeight - 88);
    var fitted = Math.min(1, roomW / width, roomH / height);
    var scale = Math.max(.1, fitted * zoomMultiplier);
    canvasViewport.style.width = Math.round(width * scale) + 'px';
    canvasViewport.style.height = Math.round(height * scale) + 'px';
    canvasShell.style.transform = 'scale(' + scale + ')';
  }
  function scheduleFit() {
    if (fitRaf) cancelAnimationFrame(fitRaf);
    fitRaf = requestAnimationFrame(function () {fitRaf = null; fitCanvas();});
  }
  if (typeof ResizeObserver !== 'undefined') {
    new ResizeObserver(scheduleFit).observe(canvasArea);
  }
  window.addEventListener('resize', scheduleFit);
  scheduleFit();

  function getOptions() {
    return {
      width: Number($('agas-width').value),
      height: Number($('agas-height').value),
      unit: $('agas-unit').value,
      double_sided: $('agas-two-sided').checked,
      grommets: $('agas-grommets').checked,
      pole_pocket: $('agas-pocket').checked
    };
  }

  function setOptions(o) {
    if (!o) return;
    $('agas-unit').value = o.unit || 'ft';
    $('agas-width').value = Number(o.width) || 3;
    $('agas-height').value = Number(o.height) || 2;
    $('agas-two-sided').checked = !!o.double_sided;
    // Legacy designs may have both extras; use one valid selection in the editor.
    // The server now rejects newly submitted options that request both.
    $('agas-grommets').checked = !!o.grommets;
    $('agas-pocket').checked = !o.grommets && !!o.pole_pocket;
    $('agas-no-finishing').checked = !o.grommets && !o.pole_pocket;
    $('agas-size').value = 'custom';
    updateSideControls();
    updateRuler();
  }

  /** Save JSON even if a malformed legacy object prevents Fabric serialization. */
  function safeObjectJSON(obj) {
    try {
      return obj.toObject(['agasLocked','agasImagePlaceholder','agasPlaceholderKind','agasLogoSlot','agasOriginalName','agasDeleteLocked','agasAdminLocked']);
    } catch (err) {
      console.warn('[AGAS Studio] Object serialization fallback:', obj.type, err);
      var props = ['left','top','width','height','scaleX','scaleY','angle','opacity',
        'originX','originY','flipX','flipY','fill','stroke','strokeWidth',
        'rx','ry','radius','text','fontSize','fontFamily','fontWeight',
        'fontStyle','underline','textAlign','lineHeight','charSpacing',
        'lockMovementX','lockMovementY','selectable','evented','agasLocked',
        'agasImagePlaceholder','agasPlaceholderKind','agasLogoSlot','agasOriginalName','agasDeleteLocked','agasAdminLocked'];
      var result = {type: obj.type || 'rect'};
      props.forEach(function(key) {
        if (obj[key] !== undefined && (typeof obj[key] !== 'object' || Array.isArray(obj[key]))) {
          result[key] = obj[key];
        }
      });
      if (obj.type === 'group' && typeof obj.getObjects === 'function') {
        result.objects = obj.getObjects().map(safeObjectJSON);
      }
      if (obj.type === 'polygon' && Array.isArray(obj.points)) {
        result.points = obj.points.map(function(pt) { return {x:pt.x, y:pt.y}; });
      }
      if (obj.type === 'image' && typeof obj.getSrc === 'function') {
        result.src = obj.getSrc();
      }
      return result;
    }
  }
  function serializedCanvas() {
    try {
      var normal = canvas.toJSON(['agasLocked','agasImagePlaceholder','agasPlaceholderKind','agasLogoSlot','agasOriginalName','agasDeleteLocked','agasAdminLocked']);
      if (normal && Array.isArray(normal.objects)) return normal;
    } catch (error) {
      console.warn('[AGAS Studio] Canvas serialization fallback:', error);
    }
    return {
      version: fabric.version || '5.3.1',
      background: canvas.backgroundColor || '#ffffff',
      objects: canvas.getObjects().map(safeObjectJSON)
    };
  }
  function loadJSON(json) {
    return new Promise(function (resolve, reject) {
      isLoading = true;
      if (!json || !Array.isArray(json.objects)) {
        canvas.clear();
        canvas.backgroundColor = '#ffffff';
        canvas.requestRenderAll();
        isLoading = false;
        scheduleFit();
        resolve();
        return;
      }
      try {
        canvas.loadFromJSON(json, function () {
          try {
            canvas.requestRenderAll();
            isLoading = false;
            scheduleFit();
            resolve();
          } catch (error) { isLoading = false; reject(error); }
        });
      } catch (error) { isLoading = false; reject(error); }
    });
  }
  function capture() {
    sides[currentSide] = serializedCanvas();
  }
  function updateSideControls() {
    var withBack = $('agas-two-sided').checked;
    $('agas-side-back').disabled = !withBack;
    if (!withBack && currentSide === 'back') switchSide('front');
  }
  async function switchSide(which) {
    if (which === 'back' && !$('agas-two-sided').checked) return;
    if (which === currentSide) return;
    capture();
    currentSide = which;
    await loadJSON(sides[which]);
    $('agas-side-front').classList.toggle('active', which === 'front');
    $('agas-side-back').classList.toggle('active', which === 'back');
    resetHistory();
    message(which === 'front' ? 'Editing front side' : 'Editing back side');
  }

  function recordHistory() {
    if (isLoading || viewing) return;
    var snapshot = JSON.stringify(serializedCanvas());
    if (history[historyAt] === snapshot) return;
    history = history.slice(0, historyAt + 1);
    history.push(snapshot);
    if (history.length > 25) history.shift();
    historyAt = history.length - 1;
    dirty = true;
    $('agas-undo').disabled = historyAt < 1;
    $('agas-redo').disabled = true;
  }
  function resetHistory() {
    history = [JSON.stringify(serializedCanvas())];
    historyAt = 0;
    $('agas-undo').disabled = true;
    $('agas-redo').disabled = true;
  }
  async function travel(delta) {
    var index = historyAt + delta;
    if (index < 0 || index >= history.length) return;
    historyAt = index;
    await loadJSON(JSON.parse(history[index]));
    $('agas-undo').disabled = historyAt < 1;
    $('agas-redo').disabled = historyAt >= history.length - 1;
    dirty = true;
  }

  function active() { return canvas.getActiveObject(); }
  function setSelected(prop, value) {
    var selected = active();
    if (!selected || selected.agasLocked) return;
    selected.set(prop, value);
    selected.setCoords();
    canvas.requestRenderAll();
    recordHistory();
  }
  function objectAdd(obj) {
    obj.set({originX: 'center', originY: 'center', left: canvas.width / 2, top: canvas.height / 2});
    canvas.add(obj);
    canvas.setActiveObject(obj);
    canvas.requestRenderAll();
  }
  function fontControls() {
    var obj = active();
    if (!obj) return;
    if (typeof obj.fontFamily === 'string') $('agas-font').value = obj.fontFamily;
    if (typeof obj.fontSize === 'number') $('agas-font-size').value = Math.round(obj.fontSize);
    if (typeof obj.fill === 'string' && /^#[0-9a-f]{6}$/i.test(obj.fill)) $('agas-color').value = obj.fill;
    $('agas-opacity').value = Math.round((typeof obj.opacity === 'number' ? obj.opacity : 1) * 100);
  }
  function editableSelection() {
    var sel = active();
    if (!sel) return null;
    var objects = sel.type === 'activeSelection' ? sel.getObjects() : [sel];
    return objects.some(function(obj) {return obj.agasLocked;}) ? null : sel;
  }
  function isTextLayer(obj) {
    return !!obj && ['textbox','i-text','text'].indexOf(obj.type) !== -1;
  }
  var selectedTextObject = null;
  var pendingTextHistory = false;
  var textHistoryTimer = null;

  function flushTextHistory() {
    if (textHistoryTimer) clearTimeout(textHistoryTimer);
    textHistoryTimer = null;
    if (pendingTextHistory) {
      pendingTextHistory = false;
      recordHistory();
    }
  }
  function renderTextLayers() {
    var holder = $('agas-text-layers');
    holder.replaceChildren();
    var layers = canvas.getObjects().filter(isTextLayer).reverse();
    if (!layers.length) {
      var msg = document.createElement('small');
      msg.textContent = 'No text yet. Use Add Heading or Add Body.';
      holder.appendChild(msg);
    }
    layers.forEach(function (obj) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'agas-text-layer-button' + (active() === obj ? ' is-selected' : '');
      var label = (obj.text || '(empty text)').replace(/\s+/g, ' ').trim();
      b.textContent = label.length > 38 ? label.slice(0, 38) + '...' : label;
      b.title = 'Select and edit: ' + label;
      b.disabled = !!cfg.readonly || viewing;
      b.addEventListener('click', function () {
        flushTextHistory();
        canvas.setActiveObject(obj);
        canvas.requestRenderAll();
        updateObjectActions();
        if (!obj.agasLocked) $('agas-text-content').focus();
      });
      holder.appendChild(b);
    });
  }
  function syncTextEditor() {
    var obj = active();
    if (selectedTextObject !== obj) flushTextHistory();
    var isText = isTextLayer(obj) && obj.type !== 'activeSelection';
    var panel = $('agas-text-edit-panel');
    panel.hidden = !isText;
    var field = $('agas-text-content');
    var changed = selectedTextObject !== obj;
    selectedTextObject = obj;
    if (isText && (changed || (document.activeElement !== field && !pendingTextHistory))) {
      field.value = obj.text || '';
    }
    field.disabled = !isText || !!obj.agasLocked || !!cfg.readonly || viewing;
    $('agas-edit-on-canvas').disabled = !isText || !!obj.agasLocked ||
      !!cfg.readonly || viewing || typeof obj.enterEditing !== 'function';
    renderTextLayers();
  }
  function editTextOnCanvas() {
    var obj = active();
    if (!isTextLayer(obj)) {
      message('Select a text element first, or select it from the Text layers list.');
      return;
    }
    if (obj.agasLocked || cfg.readonly || viewing) {
      message('This text is locked. Unlock it before editing.');
      return;
    }
    if (typeof obj.enterEditing !== 'function') {
      $('agas-text-content').focus();
      message('Edit this text using the toolbar above the canvas.');
      return;
    }
    canvas.setActiveObject(obj);
    obj.enterEditing();
    if (obj.hiddenTextarea) obj.hiddenTextarea.focus();
    if (typeof obj.selectAll === 'function') obj.selectAll();
    message('Typing will replace the selected text. Press Escape to finish editing.');
  }
  $('agas-text-content').addEventListener('input', function () {
    var obj = selectedTextObject;
    if (!isTextLayer(obj) || obj.agasLocked || cfg.readonly || viewing) return;
    obj.set('text', this.value);
    obj.setCoords();
    canvas.requestRenderAll();
    dirty = true;
    pendingTextHistory = true;
    renderTextLayers();
    if (textHistoryTimer) clearTimeout(textHistoryTimer);
    textHistoryTimer = setTimeout(flushTextHistory, 700);
  });
  $('agas-text-content').addEventListener('blur', flushTextHistory);
  $('agas-edit-on-canvas').addEventListener('click', editTextOnCanvas);
  canvas.on('mouse:dblclick', function (ev) {
    if (isTextLayer(ev.target) && !ev.target.agasLocked && !cfg.readonly && !viewing) {
      canvas.setActiveObject(ev.target);
      if (typeof ev.target.enterEditing === 'function' && !ev.target.isEditing) {
        ev.target.enterEditing();
        if (ev.target.hiddenTextarea) ev.target.hiddenTextarea.focus();
      }
    }
  });
  canvas.on('text:editing:exited', function () {
    recordHistory();
    syncTextEditor();
  });

  function updateObjectActions() {
    var sel = active();
    var canEdit = !!editableSelection() && !cfg.readonly && !viewing;
    ['agas-copy','agas-cut','agas-duplicate','agas-delete','agas-front','agas-backward',
     'agas-rotate-left','agas-rotate-right','agas-flip-h','agas-flip-v','agas-center','agas-opacity']
      .forEach(function(id) { $(id).disabled = !canEdit; });
    $('agas-paste').disabled = !internalClipboard || viewing || cfg.readonly;
    $('agas-lock').disabled = !sel || sel.type === 'activeSelection' || viewing || cfg.readonly ||
      (!!sel.agasAdminLocked && !cfg.adminTemplateMode);
    var lockLabel = sel && sel.agasAdminLocked && !cfg.adminTemplateMode ? 'Locked by template' :
      (sel && sel.agasLocked ? 'Unlock object' : 'Lock object');
    $('agas-lock').setAttribute('aria-label', lockLabel);
    $('agas-lock').title = lockLabel;
    $('agas-lock').setAttribute('aria-pressed', String(!!(sel && sel.agasLocked)));
    $('agas-protect-delete').disabled = !cfg.adminTemplateMode || !sel || sel.type === 'activeSelection' || viewing;
    var protectionLabel = sel && sel.agasDeleteLocked ? 'Allow deletion' : 'Protect from deletion';
    $('agas-protect-delete').setAttribute('aria-label', protectionLabel);
    $('agas-protect-delete').title = protectionLabel;
    $('agas-protect-delete').setAttribute('aria-pressed', String(!!(sel && sel.agasDeleteLocked)));
    var canReplace = sel && sel.type !== 'activeSelection' &&
      (sel.agasImagePlaceholder || sel.type === 'image') && !cfg.readonly && !viewing && !sel.agasLocked;
    $('agas-replace-image').disabled = !canReplace;
    $('agas-replace-image').textContent = sel && sel.agasImagePlaceholder ? 'Upload logo to this placeholder' : 'Replace selected image';
    if (sel) fontControls();
    var textSelected = isTextLayer(sel) && sel.type !== 'activeSelection';
    $('agas-context-empty').hidden = !!sel;
    $('agas-context-text').hidden = !textSelected;
    $('agas-context-actions').hidden = !sel;
    $('agas-object-toolbar').classList.toggle('agas-toolbar-has-selection', !!sel);
    syncTextEditor();
  }
  canvas.on('object:added', recordHistory);
  canvas.on('object:removed', recordHistory);
  canvas.on('object:modified', recordHistory);
  canvas.on('text:changed', recordHistory);
  canvas.on('selection:created', updateObjectActions);
  canvas.on('selection:updated', updateObjectActions);
  canvas.on('selection:cleared', updateObjectActions);
  canvas.on('object:added', renderTextLayers);
  canvas.on('object:removed', renderTextLayers);
  canvas.on('text:changed', renderTextLayers);

  /** Internal canvas clipboard. The operating system clipboard is not accessed. */
  function cloneSelected(callback) {
    var selected = editableSelection();
    if (!selected) {
      message('Select an unlocked element to copy or duplicate.');
      return;
    }
    selected.clone(function(clone) {
      if (!clone) { message('Unable to copy the selected object.', true); return; }
      callback(clone);
    }, ['agasLocked','agasImagePlaceholder','agasPlaceholderKind','agasLogoSlot','agasOriginalName','agasDeleteLocked','agasAdminLocked']);
  }
  function copySelected(afterCopy) {
    cloneSelected(function(clone) {
      internalClipboard = clone;
      pasteCount = 0;
      updateObjectActions();
      message('Copied to the design editor clipboard.');
      if (typeof afterCopy === 'function') afterCopy();
    });
  }
  function addClonedObject(cloned, offset) {
    isLoading = true; // One undo snapshot for multi-object operations.
    try {
      canvas.discardActiveObject();
      cloned.set({left: (cloned.left || 0) + offset, top: (cloned.top || 0) + offset});
      cloned.setCoords();
      if (cloned.type === 'activeSelection') {
        cloned.canvas = canvas;
        cloned.forEachObject(function(obj) {
          obj.set({evented:true,selectable:true});
          canvas.add(obj);
        });
        cloned.setCoords();
        canvas.setActiveObject(cloned);
      } else {
        cloned.set({evented:true,selectable:true});
        canvas.add(cloned);
        canvas.setActiveObject(cloned);
      }
      canvas.requestRenderAll();
    } finally {
      isLoading = false;
    }
    recordHistory();
    updateObjectActions();
  }
  function pasteSelected() {
    if (!internalClipboard || viewing) return;
    internalClipboard.clone(function(cloned) {
      if (!cloned) {message('Cannot paste the copied object.',true);return;}
      pasteCount += 1;
      addClonedObject(cloned, 22 * pasteCount);
      message('Pasted. Drag the selected element to reposition it.');
    }, ['agasLocked','agasImagePlaceholder','agasPlaceholderKind','agasLogoSlot','agasOriginalName','agasDeleteLocked','agasAdminLocked']);
  }
  function duplicateSelected() {
    cloneSelected(function(cloned) {
      addClonedObject(cloned, 25);
      message('Object duplicated.');
    });
  }
  function deleteSelected() {
    var selected = editableSelection();
    if (!selected) {message('Select an unlocked element to delete.');return;}
    var deleteItems = selected.type === 'activeSelection' ? selected.getObjects() : [selected];
    if (deleteItems.some(function(o) {return o.agasDeleteLocked;})) {
      message('This element is protected against deletion by the template owner.',true);return;
    }
    isLoading = true;
    try {
      var targets = selected.type === 'activeSelection' ? selected.getObjects().slice() : [selected];
      canvas.discardActiveObject();
      targets.forEach(function(obj) {canvas.remove(obj);});
      canvas.requestRenderAll();
    } finally {
      isLoading = false;
    }
    recordHistory();
    updateObjectActions();
  }
  function cutSelected() {
    copySelected(deleteSelected);
  }
  function rotateSelected(degrees) {
    var selected = editableSelection();
    if (!selected) return;
    selected.rotate((selected.angle || 0) + degrees);
    selected.setCoords(); canvas.requestRenderAll();recordHistory();
  }
  function flipSelected(axis) {
    var selected = editableSelection();
    if (!selected) return;
    selected.set(axis, !selected[axis]);
    selected.setCoords();canvas.requestRenderAll();recordHistory();
  }
  function toggleLock() {
    var selected = active();
    if (!selected || selected.type === 'activeSelection') return;
    if (selected.agasAdminLocked && !cfg.adminTemplateMode) {
      message('This element is locked by the template owner.',true);return;
    }
    var locked = !selected.agasLocked;
    selected.set({agasLocked:locked,agasAdminLocked:cfg.adminTemplateMode ? locked : !!selected.agasAdminLocked,
      lockMovementX:locked,lockMovementY:locked,
      lockScalingX:locked,lockScalingY:locked,lockRotation:locked,hasControls:!locked});
    // Still selectable so the user can unlock it in the same panel.
    selected.selectable = true;
    selected.setCoords();canvas.requestRenderAll();recordHistory();updateObjectActions();
    message(locked ? 'Object locked. Select it again to unlock.' : 'Object unlocked.');
  }
  function centerSelected() {
    var selected = editableSelection();
    if (!selected) return;
    canvas.centerObject(selected);selected.setCoords();canvas.requestRenderAll();recordHistory();
  }

  function addHeading() {
    objectAdd(new fabric.Textbox('YOUR HEADLINE', {width: canvas.width * .75, fontSize: 65, fontFamily: 'Arial', fill: '#1A365D', fontWeight: 'bold', textAlign: 'center', editable: true}));
  }
  function addBody() {
    objectAdd(new fabric.Textbox('Add your message here', {width: canvas.width * .6, fontSize: 32, fontFamily: 'Arial', fill: '#1A365D', textAlign: 'center', editable: true}));
  }
  function addStar() {
    var pts = [], inner = 35, outer = 72;
    for (var i = 0; i < 10; i++) {
      var radius = i % 2 === 0 ? outer : inner;
      var angle = i * Math.PI / 5 - Math.PI / 2;
      pts.push({x: Math.cos(angle) * radius + outer, y: Math.sin(angle) * radius + outer});
    }
    objectAdd(new fabric.Polygon(pts, {fill: '#F59E0B'}));
  }

  $('agas-add-heading').addEventListener('click', addHeading);
  $('agas-add-body').addEventListener('click', addBody);
  $('agas-add-rect').addEventListener('click', function () { objectAdd(new fabric.Rect({width: 200, height: 125, fill: '#F59E0B', rx: 8, ry: 8})); });
  $('agas-add-circle').addEventListener('click', function () { objectAdd(new fabric.Circle({radius: 65, fill: '#0D9488'})); });
  $('agas-add-star').addEventListener('click', addStar);
  $('agas-bg').addEventListener('input', function () { canvas.setBackgroundColor(this.value, canvas.requestRenderAll.bind(canvas)); recordHistory(); });
  $('agas-font').addEventListener('change', function () { setSelected('fontFamily', this.value); });
  $('agas-font-size').addEventListener('change', function () { setSelected('fontSize', Number(this.value)); });
  $('agas-color').addEventListener('input', function () { setSelected('fill', this.value); });
  $('agas-bold').addEventListener('click', function () { var o = active(); if (o) setSelected('fontWeight', o.fontWeight === 'bold' ? 'normal' : 'bold'); });
  $('agas-copy').addEventListener('click', function () {copySelected();});
  $('agas-paste').addEventListener('click', pasteSelected);
  $('agas-duplicate').addEventListener('click', duplicateSelected);
  $('agas-cut').addEventListener('click', cutSelected);
  $('agas-delete').addEventListener('click', deleteSelected);
  $('agas-front').addEventListener('click', function () { var o = editableSelection(); if (o) {canvas.bringForward(o);recordHistory();} });
  $('agas-backward').addEventListener('click', function () { var o = editableSelection(); if (o) {canvas.sendBackwards(o);recordHistory();} });
  $('agas-rotate-left').addEventListener('click', function () {rotateSelected(-15);});
  $('agas-rotate-right').addEventListener('click', function () {rotateSelected(15);});
  $('agas-flip-h').addEventListener('click', function () {flipSelected('flipX');});
  $('agas-flip-v').addEventListener('click', function () {flipSelected('flipY');});
  $('agas-lock').addEventListener('click', toggleLock);
  $('agas-protect-delete').addEventListener('click', function(){
    var o=active();if (!cfg.adminTemplateMode || !o || viewing) return;
    o.set('agasDeleteLocked',!o.agasDeleteLocked);
    recordHistory();updateObjectActions();
    message(o.agasDeleteLocked ? 'Customers cannot delete this object, but can edit it if unlocked.' : 'Deletion protection removed.');
  });
  $('agas-center').addEventListener('click', centerSelected);
  $('agas-opacity').addEventListener('input', function () {setSelected('opacity', Number(this.value) / 100);});
  $('agas-undo').addEventListener('click', function () { travel(-1); });
  $('agas-redo').addEventListener('click', function () { travel(1); });


/** Image-slot uploads are not editable text. Clicking the placeholder opens the image picker. */
var pendingImageTarget = null;
var clickStart = null;
function isImageSlot(obj) {
  return !!obj && (obj.agasImagePlaceholder || obj.agasLogoSlot || obj.type === 'image');
}
function askForImage(target) {
  if (cfg.readonly || viewing) return;
  if (target && (target.agasLocked || !isImageSlot(target))) return;
  pendingImageTarget = target || null;
  $('agas-logo-file').value = '';
  $('agas-logo-file').click();
}
function addLogoSlot() {
  if (cfg.readonly || viewing) return;
  var w = Math.min(520, canvas.width * .76), h = Math.min(138, canvas.height * .28);
  var box = new fabric.Rect({left:-w/2, top:-h/2, width:w, height:h,
    fill:'rgba(20,184,166,.06)',stroke:'#0D9488',strokeWidth:3,strokeDashArray:[10,8],rx:10,ry:10});
  var label = new fabric.Textbox('CLICK TO UPLOAD YOUR LOGO', {
    left:-w*.46,top:-18,width:w*.92,fontSize:Math.min(26,Math.max(13,w/18)),
    fontFamily:'Arial',fontWeight:'bold',fill:'#0D9488',textAlign:'center'});
  var group = new fabric.Group([box,label], {
    agasImagePlaceholder:true,agasPlaceholderKind:'logo',hasControls:true
  });
  objectAdd(group);
  message('Logo placeholder added. Click it to choose an image.');
}
$('agas-add-logo-slot').addEventListener('click', addLogoSlot);
$('agas-replace-image').addEventListener('click', function () { askForImage(active()); });
canvas.on('mouse:down', function (ev) {
  clickStart = ev.e && typeof ev.e.clientX === 'number' ? {x:ev.e.clientX,y:ev.e.clientY} : null;
});
canvas.on('mouse:up', function (ev) {
  if (!ev.target || !ev.target.agasImagePlaceholder || !clickStart || !ev.e || cfg.readonly || viewing) return;
  var distance = Math.hypot(ev.e.clientX - clickStart.x, ev.e.clientY - clickStart.y);
  clickStart = null;
  if (distance < 6) askForImage(ev.target);
});

function replaceTargetWithImage(img, target, name) {
  var old = target.getBoundingRect(true, true);
  var maxW = Math.max(10, old.width * (target.agasImagePlaceholder ? .92 : 1));
  var maxH = Math.max(10, old.height * (target.agasImagePlaceholder ? .88 : 1));
  var scale = Math.min(maxW / img.width, maxH / img.height);
  if (!Number.isFinite(scale) || scale <= 0) throw new Error('Invalid logo dimensions.');
  img.set({scaleX:scale,scaleY:scale,
    left:old.left+(old.width-img.width*scale)/2,
    top:old.top+(old.height-img.height*scale)/2,
    originX:'left',originY:'top',
    agasLogoSlot:true,agasOriginalName:name || 'Uploaded logo'});
  var at = canvas.getObjects().indexOf(target);
  isLoading = true;
  try {
    canvas.discardActiveObject();
    canvas.remove(target);
    canvas.insertAt(img, Math.max(0, at), false);
    canvas.setActiveObject(img);
    img.setCoords();
    canvas.requestRenderAll();
  } finally {isLoading = false;}
  recordHistory();
  updateObjectActions();
  message('Logo uploaded. You can resize it, reposition it, or replace it again.');
}

function processImage(file, replaceTarget) {
  if (cfg.readonly || viewing) return;
  if (!file || !['image/png', 'image/jpeg'].includes(file.type)) {
    message('Use a PNG or JPG image. SVG support is scheduled for the next milestone.', true);return;
  }
  if (file.size > 1024 * 1024) {message('Image must be under 1 MB for this demo.', true);return;}
  var reader = new FileReader();
  reader.onerror = function () {message('Unable to read the selected image.', true);};
  reader.onload = function () {
    fabric.Image.fromURL(String(reader.result), function (img) {
      if (!img || !img.width || !img.height) {message('Cannot decode image.', true);return;}
      try {
        if (replaceTarget && canvas.getObjects().indexOf(replaceTarget) !== -1) {
          replaceTargetWithImage(img, replaceTarget, file.name);
        } else {
          img.scaleToWidth(Math.min(canvas.width * .55, img.width));
          if (img.getScaledHeight() > canvas.height * .7) img.scaleToHeight(canvas.height * .7);
          img.set({agasOriginalName:file.name});
          objectAdd(img);
          updateObjectActions();
          message('Image added to canvas.');
        }
      } catch (error) {message('Image placement failed: ' + error.message, true);}
    });
  };
  reader.readAsDataURL(file);
}
$('agas-logo-file').addEventListener('change', function () {
  var target = pendingImageTarget;
  pendingImageTarget = null;
  if (this.files[0]) processImage(this.files[0], target);
  this.value = '';
});
$('agas-file').addEventListener('change', function () {
  if (this.files[0]) processImage(this.files[0], null);
  this.value = '';
});
var dropArea = root.querySelector('.agas-canvas-area');
dropArea.addEventListener('dragover', function (ev) {ev.preventDefault();});
dropArea.addEventListener('drop', function (ev) {
  ev.preventDefault();
  if (ev.dataTransfer.files[0]) processImage(ev.dataTransfer.files[0], isImageSlot(active()) ? active() : null);
});

  /** Additional Part-2 editor controls; no WooCommerce order/cart state is modified. */
  function addSubheading() {
    objectAdd(new fabric.Textbox('YOUR SUBHEADING', {
      width: canvas.width * .68, fontSize: 43, fontFamily: 'Inter',
      fill: '#1A365D', fontWeight:'normal', textAlign:'center', editable:true
    }));
  }
  $('agas-add-subheading').addEventListener('click', addSubheading);

  function updateTextControls() {
    var o = active();
    if (!isTextLayer(o)) return;
    $('agas-text-align').value = o.textAlign || 'left';
    $('agas-char-spacing').value = Math.round(Number(o.charSpacing) || 0);
    $('agas-line-height').value = Number(o.lineHeight) || 1.16;
    ['bold','italic','underline'].forEach(function(kind){
      var on = kind === 'bold' ? o.fontWeight === 'bold' || Number(o.fontWeight) >= 700
        : kind === 'italic' ? o.fontStyle === 'italic' : !!o.underline;
      $('agas-' + kind).setAttribute('aria-pressed', String(on));
    });
  }
  ['selection:created','selection:updated','object:modified','text:changed'].forEach(function(event) {
    canvas.on(event, updateTextControls);
  });
  $('agas-font').addEventListener('change', function() {
    var family=this.value;
    if (document.fonts && typeof document.fonts.load === 'function') {
      document.fonts.load('16px "' + family.replace(/"/g,'') + '"').then(function(){canvas.requestRenderAll();}).catch(function(){});
    }
  });
  $('agas-italic').addEventListener('click', function(){
    if (!isTextLayer(active())) return;
    setSelected('fontStyle', active().fontStyle === 'italic' ? 'normal' : 'italic');updateTextControls();
  });
  $('agas-underline').addEventListener('click', function(){
    if (!isTextLayer(active())) return;
    setSelected('underline', !active().underline);updateTextControls();
  });
  $('agas-text-align').addEventListener('change', function(){if (isTextLayer(active())) setSelected('textAlign',this.value);});
  $('agas-char-spacing').addEventListener('change', function(){
    if (!isTextLayer(active())) return;
    setSelected('charSpacing',Math.max(-500,Math.min(1500,Number(this.value)||0)));
  });
  $('agas-line-height').addEventListener('change', function(){
    if (!isTextLayer(active())) return;
    setSelected('lineHeight',Math.max(.7,Math.min(3,Number(this.value)||1.16)));
  });
  $('agas-bold').addEventListener('click', function(){setTimeout(updateTextControls,0);});

  function alignObject(edge) {
    var obj=editableSelection();if (!obj) return;
    var box=obj.getBoundingRect(true,true);
    if (edge==='left') obj.left += -box.left;
    if (edge==='right') obj.left += canvas.width-(box.left+box.width);
    if (edge==='top') obj.top += -box.top;
    if (edge==='bottom') obj.top += canvas.height-(box.top+box.height);
    obj.setCoords();canvas.requestRenderAll();recordHistory();
  }
  ['left','right','top','bottom'].forEach(function(edge){
    $('agas-align-'+edge).addEventListener('click', function(){alignObject(edge);});
  });

  /** Each graphic is our own geometric SVG illustration, no third-party artwork. */
  var cliparts=[
    ['business','Store','<path d="M16 42h68l-6-20H22zM24 42v39h52V42M42 81V56h16v25M16 42c0 13 14 16 19 3 7 15 22 12 26 0 10 13 23 9 23-3"/>'],
    ['business','Sale tag','<path d="M12 24h46l28 26-35 36-39-40z"/><circle cx="28" cy="39" r="6"/>'],
    ['business','Megaphone','<path d="M17 45h22l42-20v50L39 55H17zM28 55l9 27h17L44 55"/>'],
    ['business','Truck','<path d="M11 30h49v41H11zM60 44h17l12 15v12H60z"/><circle cx="29" cy="72" r="9"/><circle cx="72" cy="72" r="9"/>'],
    ['business','Shopping bag','<path d="M20 36h60l-5 48H25zM36 36V28a14 14 0 0 1 28 0v8"/>'],
    ['business','Phone','<path d="M35 18h30v64H35zM44 26h12M46 73h8"/><circle cx="50" cy="77" r="2"/>'],
    ['events','Balloon','<path d="M50 12c21 0 28 18 22 35-5 13-13 20-22 23-9-3-17-10-22-23-6-17 1-35 22-35zM44 70h12l-6 7zM50 77c-16 5 18 10 0 17"/>'],
    ['events','Gift','<path d="M17 38h66v46H17zM12 31h76v14H12zM45 31v53M55 31v53M50 31C36 5 17 17 31 29zM50 31C64 5 83 17 69 29z"/>'],
    ['events','Trophy','<path d="M28 18h44v31c0 17-10 25-22 25S28 66 28 49zM28 25H12v15c0 13 8 17 18 17M72 25h16v15c0 13-8 17-18 17M50 74v12M30 88h40"/>'],
    ['events','Music','<path d="M41 28v50M41 28l39-10v45M41 39l39-10"/><ellipse cx="30" cy="77" rx="13" ry="9"/><ellipse cx="69" cy="62" rx="13" ry="9"/>'],
    ['events','Cake','<path d="M16 47h68v36H16zM16 57h68M38 47V25M62 47V25"/><path d="M38 14c-7 7-7 14 0 14 7 0 7-7 0-14M62 14c-7 7-7 14 0 14 7 0 7-7 0-14"/>'],
    ['events','Confetti','<path d="M21 80 78 24l-7 46-12-14zM14 18l8 13M39 13l-3 15M75 76l11 8M81 12l-8 9M12 58l17-3"/>'],
    ['nature','Sun','<circle cx="50" cy="50" r="19"/><path d="M50 9v14M50 77v14M9 50h14M77 50h14M21 21l10 10M69 69l10 10M79 21 69 31M31 69 21 79"/>'],
    ['nature','Moon','<path d="M70 11c-29 4-43 40-21 60 12 10 26 7 37 0C62 92 25 81 17 52 10 27 35 5 70 11z"/>'],
    ['nature','Leaf','<path d="M83 17C40 12 18 32 18 57c0 18 12 27 27 25 28-3 43-33 38-65zM22 83c14-21 32-37 55-50"/>'],
    ['nature','Tree','<path d="M50 9 22 42h14L16 64h68L64 42h14zM43 64v27h14V64"/>'],
    ['nature','Mountain','<path d="m7 83 31-57 18 31 13-19 24 45zM29 43l9 15 9-15"/>'],
    ['nature','Cloud','<path d="M25 74c-14 0-20-13-16-25 4-10 14-16 24-13 8-24 39-24 47 0 23 0 27 38 0 38z"/>'],
    ['symbols','Heart','<path d="M50 83 18 52C-1 30 21 8 40 24l10 10 10-10c19-16 41 6 22 28z"/>'],
    ['symbols','Shield','<path d="M50 10 83 23v24c0 21-12 36-33 45-21-9-33-24-33-45V23zM36 49l10 10 19-21"/>'],
    ['symbols','Star','<path d="m50 9 12 28 31 3-23 20 7 30-27-16-27 16 7-30L7 40l31-3z"/>'],
    ['symbols','Check mark','<path d="M16 52 39 76 84 24"/>'],
    ['symbols','Location pin','<path d="M50 91s31-30 31-53a31 31 0 0 0-62 0c0 23 31 53 31 53z"/><circle cx="50" cy="38" r="10"/>'],
    ['symbols','Lightning','<path d="m56 7-38 49h28L36 93l46-53H52z"/>']
  ];
  function svgMarkup(paths) {
    return '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100">'
      + '<g fill="none" stroke="#1e40af" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">'
      + paths + '</g></svg>';
  }
  function addSVGMarkup(svg, name, target) {
    fabric.loadSVGFromString(svg, function(objects, options) {
      if (!objects || !objects.length) {message('SVG has no supported shapes.',true);return;}
      var obj=fabric.util.groupSVGElements(objects,options);
      if (!obj || !obj.width || !obj.height) {message('SVG dimensions could not be read.',true);return;}
      if (target && canvas.getObjects().indexOf(target)!==-1) {
        replaceTargetWithImage(obj,target,name);return;
      }
      if (obj.width > canvas.width * .45 || obj.height > canvas.height * .55) {
        var scale=Math.min(canvas.width*.45/obj.width,canvas.height*.55/obj.height);
        obj.scale(Math.max(.01,scale));
      }
      obj.set('agasOriginalName','agasDeleteLocked','agasAdminLocked',name || 'SVG graphic');
      objectAdd(obj);updateObjectActions();message((name||'Graphic')+' added. Resize or reposition it.');
    });
  }
  function fillCliparts() {
    var grid=$('agas-clipart-grid'), selected=$('agas-clipart-category').value;
    grid.replaceChildren();
    cliparts.forEach(function(item){
      if(selected!=='all' && selected!==item[0]) return;
      var btn=document.createElement('button');btn.type='button';btn.className='agas-clipart-item';
      btn.title='Add '+item[1];btn.setAttribute('aria-label','Add '+item[1]);
      var label=document.createElement('span');label.textContent=item[1];
      var icon=document.createElement('span');icon.className='agas-clipart-icon';icon.innerHTML=svgMarkup(item[2]);
      btn.append(icon,label);
      btn.addEventListener('click',function(){addSVGMarkup(svgMarkup(item[2]),item[1]);});
      grid.appendChild(btn);
    });
  }
  $('agas-clipart-category').addEventListener('change',fillCliparts);
  fillCliparts();

  function qrSvg(text) {
    if (!window.AGAS_QR || typeof window.AGAS_QR.create!=='function') {
      throw new Error('QR library unavailable. Refresh the editor.');
    }
    var modules=window.AGAS_QR.create(text), quiet=4, n=modules.length;
    if (n<21 || n>177) throw new Error('Invalid QR matrix');
    var paths=[];
    modules.forEach(function(row,y) {row.forEach(function(dark,x) {
      if (dark) paths.push('M'+(x+quiet)+' '+(y+quiet)+'h1v1h-1z');
    });});
    var size=n+quiet*2;
    return '<svg xmlns="http://www.w3.org/2000/svg" width="'+size+'" height="'+size+'" viewBox="0 0 '+size+' '+size+'"><rect width="'+size+'" height="'+size+'" fill="#ffffff"/><path fill="#101827" d="'+paths.join('')+'"/></svg>';
  }
  $('agas-add-qr').addEventListener('click',function(){
    try {
      var value=$('agas-qr-url').value.trim();
      if (!value) throw new Error('Enter the URL for this QR code.');
      if (!/^https?:\/\//i.test(value)) value='https://'+value;
      var url=new URL(value);
      if (!['https:','http:'].includes(url.protocol) || !url.hostname || value.length>240) throw new Error('Enter a valid http(s) URL (240 characters maximum).');
      addSVGMarkup(qrSvg(url.toString()),'QR code — '+url.hostname);
    } catch(error){message(error.message,true);}
  });

  /** Strictly supported subset. Reject external resources, scripts, animations and CSS. */
  function sanitizeSimpleSVG(source) {
    if (typeof source!=='string' || source.length>1000000 || /<!\s*(?:doctype|entity)/i.test(source)) throw new Error('SVG is too large or contains unsupported declarations.');
    var doc=new DOMParser().parseFromString(source,'image/svg+xml');
    if (doc.getElementsByTagName('parsererror').length || doc.documentElement.localName!=='svg') throw new Error('SVG cannot be parsed.');
    var allowedTags=['svg','g','path','rect','circle','ellipse','line','polygon','polyline'];
    var allowedAttrs=['width','height','viewBox','d','x','y','x1','y1','x2','y2','cx','cy','r','rx','ry','points','fill','stroke','stroke-width','stroke-linecap','stroke-linejoin','stroke-dasharray','fill-rule','opacity','fill-opacity','stroke-opacity','transform','preserveAspectRatio'];
    var clean=document.implementation.createDocument('http://www.w3.org/2000/svg','svg',null),count=0;
    function walk(from,to) {
      if (++count>500) throw new Error('SVG contains too many objects.');
      if (!allowedTags.includes(from.localName)) throw new Error('Unsupported SVG element: '+from.localName+'. Use simple paths and shapes.');
      Array.from(from.attributes).forEach(function(a){
        var value=a.value;
        if (!allowedAttrs.includes(a.localName) || a.namespaceURI || /url\s*\(|javascript:|data:|@import|expression\s*\(/i.test(value)) {
          if (a.name==='xmlns') return;
          throw new Error('Unsafe or unsupported SVG attribute: '+a.name);
        }
        to.setAttribute(a.localName,value);
      });
      Array.from(from.childNodes).forEach(function(child){
        if (child.nodeType===1) {
          var next=clean.createElementNS('http://www.w3.org/2000/svg',child.localName);
          to.appendChild(next);walk(child,next);
        } else if (child.nodeType!==3 && child.nodeType!==8) {
          throw new Error('Unsupported SVG node.');
        }
      });
    }
    walk(doc.documentElement,clean.documentElement);
    if (!clean.documentElement.hasAttribute('viewBox') && !clean.documentElement.hasAttribute('width')) {
      throw new Error('SVG needs a viewBox or physical dimensions.');
    }
    return new XMLSerializer().serializeToString(clean);
  }
  // Add SVG upload support without changing the existing PNG/JPG placement path.
  var existingProcessImage=processImage;
  processImage=function(file,target){
    if (!file) return;
    if (file.type==='image/svg+xml' || /\.svg$/i.test(file.name)) {
      if (cfg.readonly || viewing) return;
      if (file.size>1024*1024) {message('SVG must be under 1MB.',true);return;}
      var reader=new FileReader();
      reader.onerror=function(){message('Cannot read SVG.',true);};
      reader.onload=function(){
        try {addSVGMarkup(sanitizeSimpleSVG(String(reader.result)),file.name,target);}
        catch(error){message('SVG upload rejected: '+error.message,true);}
      };
      reader.readAsText(file);return;
    }
    existingProcessImage(file,target);
  };

  $('agas-bg-file').addEventListener('change',function(){
    var file=this.files[0];this.value='';
    if (!file) return;
    if (!['image/png','image/jpeg'].includes(file.type) || file.size>1024*1024) {
      message('Use a PNG or JPG background under 1MB.',true);return;
    }
    var reader=new FileReader();
    reader.onload=function(){
      fabric.Image.fromURL(String(reader.result),function(image){
        if (!image||!image.width||!image.height) {message('Cannot decode background image.',true);return;}
        var scale=Math.max(canvas.width/image.width,canvas.height/image.height);
        image.set({left:0,top:0,originX:'left',originY:'top',scaleX:scale,scaleY:scale,agasOriginalName:file.name});
        canvas.setBackgroundImage(image,function(){canvas.requestRenderAll();recordHistory();scheduleSideThumbs();});
        message('Background image added. Use Remove background image to clear it.');
      });
    };
    reader.onerror=function(){message('Cannot read background.',true);};
    reader.readAsDataURL(file);
  });
  $('agas-bg-remove').addEventListener('click',function(){
    canvas.setBackgroundImage(null,function(){canvas.requestRenderAll();recordHistory();scheduleSideThumbs();});
    message('Background image removed. Background color remains.');
  });

  var sidePreviewTimer=null, sidePreviewGeneration=0;
  function sidePreview(which,scene,gen) {
    var target=$('agas-thumb-'+which);
    if (!target || !scene || !Array.isArray(scene.objects)) return;
    var scratch=new fabric.StaticCanvas(document.createElement('canvas'),{
      width:canvas.width,height:canvas.height,enableRetinaScaling:false,renderOnAddRemove:false
    });
    try {scratch.loadFromJSON(scene,function(){
      try {
        scratch.renderAll();
        var url=scratch.toDataURL({format:'png',multiplier:Math.min(.2,180/canvas.width)});
        if (gen===sidePreviewGeneration) target.src=url;
      } catch(e){console.warn('[AGAS] Side preview unavailable',e);}
      scratch.dispose();
    });}catch(e){scratch.dispose();}
  }
  function scheduleSideThumbs(){
    if (isLoading || viewing) return;
    if (sidePreviewTimer) clearTimeout(sidePreviewTimer);
    sidePreviewTimer=setTimeout(function(){
      var gen=++sidePreviewGeneration;
      var here=serializedCanvas();
      sidePreview(currentSide,here,gen);
      var other=currentSide==='front'?'back':'front';
      if (sides[other]) sidePreview(other,sides[other],gen);
      $('agas-side-thumb-back').disabled=!$('agas-two-sided').checked;
    },340);
  }
  $('agas-side-thumb-front').addEventListener('click',function(){switchSide('front');});
  $('agas-side-thumb-back').addEventListener('click',function(){switchSide('back');});
  ['object:added','object:removed','object:modified','text:changed'].forEach(function(evt){canvas.on(evt,scheduleSideThumbs);});
  $('agas-two-sided').addEventListener('change',scheduleSideThumbs);
  $('agas-side-front').addEventListener('click',scheduleSideThumbs);
  $('agas-side-back').addEventListener('click',scheduleSideThumbs);
  $('agas-bg').addEventListener('input',scheduleSideThumbs);
  setTimeout(scheduleSideThumbs,600);

  function scaledJson(json, sx, sy) {
    if (!json || !Array.isArray(json.objects)) return json;
    var copy = JSON.parse(JSON.stringify(json));
    copy.objects.forEach(function (obj) {
      if (typeof obj.left === 'number') obj.left *= sx;
      if (typeof obj.top === 'number') obj.top *= sy;
      obj.scaleX = (typeof obj.scaleX === 'number' ? obj.scaleX : 1) * sx;
      obj.scaleY = (typeof obj.scaleY === 'number' ? obj.scaleY : 1) * sy;
    });
    return copy;
  }
  function physicalFt(w, h, unit) {
    var factor = unit === 'in' ? 12 : unit === 'cm' ? 30.48 : 1;
    return {w: w / factor, h: h / factor};
  }
  function updateRuler() {
    var o = getOptions();
    $('agas-ruler').textContent = '0  |  ' + (o.width / 4).toFixed(1) + '  |  ' + (o.width / 2).toFixed(1) + '  |  ' + (o.width * 3 / 4).toFixed(1) + '  |  ' + o.width + ' ' + o.unit;
    $('agas-ruler-vertical').textContent = '0  ·  ' + (o.height / 2).toFixed(1) + '  ·  ' + o.height + ' ' + o.unit;
    var feet=physicalFt(o.width,o.height,o.unit);
    var bleedX = feet.w>0 ? Math.max(2,.125/(feet.w*12)*canvas.width) : 4;
    var bleedY = feet.h>0 ? Math.max(2,.125/(feet.h*12)*canvas.height) : 4;
    $('agas-bleed').style.inset = bleedY.toFixed(2) + 'px ' + bleedX.toFixed(2) + 'px';
  }
  function resizeForRatio() {
    var opt = getOptions();
    var feet = physicalFt(opt.width, opt.height, opt.unit);
    if (!Number.isFinite(feet.w) || !Number.isFinite(feet.h) || feet.w < .25 || feet.h < .25 || feet.w > 20 || feet.h > 20) return;
    capture();
    var ratio = feet.w / feet.h;
    var nextW = ratio >= 1 ? 900 : Math.max(260, Math.round(600 * ratio));
    var nextH = ratio >= 1 ? Math.max(240, Math.round(900 / ratio)) : 600;
    var sx = nextW / canvas.width, sy = nextH / canvas.height;
    if (Math.abs(sx - 1) < .001 && Math.abs(sy - 1) < .001) return;
    sides.front = scaledJson(sides.front, sx, sy);
    sides.back = scaledJson(sides.back, sx, sy);
    canvas.setDimensions({width: nextW, height: nextH});
    scheduleFit();
    var future = sides[currentSide];
    loadJSON(future).then(function () {resetHistory();});
    updateRuler();
  }
  /**
   * WordPress REST URLs vary with permalink configuration:
   * - pretty: /wp-json/agas-design/v1/
   * - plain:  /?rest_route=/agas-design/v1/
   * Do not append path/query strings directly to cfg.rest.
   */
  function restEndpoint(route) {
    var endpoint = new URL(cfg.rest, window.location.href);
    var suffix = String(route).replace(/^\/+/, '');
    if (endpoint.searchParams.has('rest_route')) {
      var baseRoute = endpoint.searchParams.get('rest_route') || '/';
      endpoint.searchParams.set('rest_route', baseRoute.replace(/\/+$/, '') + '/' + suffix);
    } else {
      endpoint.pathname = endpoint.pathname.replace(/\/+$/, '') + '/' + suffix;
    }
    return endpoint;
  }
  function priceFallback(o) {
    var feet = physicalFt(o.width, o.height, o.unit);
    return Math.max(Number(rules.minimum), feet.w * feet.h * Number(rules.sqft_rate)) + (o.double_sided ? Number(rules.back) : 0) + (o.grommets ? Number(rules.grommets) : 0) + (o.pole_pocket ? Number(rules.pole_pocket) : 0);
  }
  function quote() {
    clearTimeout(quoteTimer);
    quoteTimer = setTimeout(async function () {
      var opts = getOptions();
      var local = priceFallback(opts);
      $('agas-price').textContent = Number.isFinite(local) ? '$' + local.toFixed(2) : 'Invalid size';
      try {
        var endpoint = restEndpoint('price');
        var params = {width: opts.width, height: opts.height, unit: opts.unit, double_sided: opts.double_sided ? 1 : 0, grommets: opts.grommets ? 1 : 0, pole_pocket: opts.pole_pocket ? 1 : 0};
        Object.keys(params).forEach(function (key) {endpoint.searchParams.set(key, String(params[key]));});
        var response = await fetch(endpoint.toString(), {credentials:'same-origin'});
        var body = await response.json();
        if (!response.ok) throw new Error(body.message || 'Invalid size');
        $('agas-price').textContent = body.currency + Number(body.price).toFixed(2);
        if (status.classList.contains('agas-error') && /route was found matching/i.test(status.textContent)) message('', false);
      } catch (e) {
        message(e.message, true);
      }
    }, 160);
  }
  $('agas-size').addEventListener('change', function () {
    if (this.value !== 'custom') {
      var nums = this.value.split('x').map(Number);
      var o = getOptions();
      var factor = o.unit === 'in' ? 12 : (o.unit === 'cm' ? 30.48 : 1);
      $('agas-width').value = Number((nums[0] * factor).toFixed(2));
      $('agas-height').value = Number((nums[1] * factor).toFixed(2));
    }
    resizeForRatio();quote();
  });
  ['agas-width', 'agas-height'].forEach(function (id) {$(id).addEventListener('change', function () {$('agas-size').value = 'custom';resizeForRatio();updateRuler();quote();});});
  $('agas-unit').addEventListener('change', function () {
    var old = this.dataset.previous || 'ft';
    var newUnit = this.value;
    var oldFactor = old === 'in' ? 12 : old === 'cm' ? 30.48 : 1;
    var newFactor = newUnit === 'in' ? 12 : newUnit === 'cm' ? 30.48 : 1;
    $('agas-width').value = Number((Number($('agas-width').value) / oldFactor * newFactor).toFixed(3));
    $('agas-height').value = Number((Number($('agas-height').value) / oldFactor * newFactor).toFixed(3));
    this.dataset.previous = newUnit;
    updateRuler();quote();
  });
  ['agas-two-sided', 'agas-no-finishing', 'agas-grommets', 'agas-pocket'].forEach(function (id) {
    $(id).addEventListener('change', function () {updateSideControls();quote();});
  });
  $('agas-side-front').addEventListener('click', function () {switchSide('front');});
  $('agas-side-back').addEventListener('click', function () {switchSide('back');});
  $('agas-guides').addEventListener('change', function () {root.classList.toggle('agas-hide-guides', !this.checked);});
  $('agas-zoom').addEventListener('input', function () {
    $('agas-zoom-select').value = this.value;
    zoomMultiplier = Number(this.value) / 100;
    fitCanvas();
    $('agas-zoom-out').textContent = this.value + '% of fit';
  });

  $('agas-zoom-select').addEventListener('change', function(){
    $('agas-zoom').value=this.value;
    $('agas-zoom').dispatchEvent(new Event('input',{bubbles:true}));
  });
  function hideCenterGuides(){
    $('agas-guide-vertical').style.display='none';
    $('agas-guide-horizontal').style.display='none';
  }
  canvas.on('object:moving',function(ev){
    if (!ev.target || ev.target.agasLocked || viewing) return;
    var selected=ev.target, center=selected.getCenterPoint();
    var dx=canvas.width/2-center.x,dy=canvas.height/2-center.y;
    var xSnap=Math.abs(dx)<=8,ySnap=Math.abs(dy)<=8;
    $('agas-guide-vertical').style.display=xSnap?'block':'none';
    $('agas-guide-horizontal').style.display=ySnap?'block':'none';
    if (xSnap) selected.left+=dx;
    if (ySnap) selected.top+=dy;
    if (xSnap||ySnap) {selected.setCoords();canvas.requestRenderAll();}
  });
  canvas.on('object:modified',hideCenterGuides);
  canvas.on('mouse:up',hideCenterGuides);
  $('agas-help-open').addEventListener('click',function(){
    $('agas-help-overlay').hidden=false;$('agas-help-close').focus();
  });
  $('agas-help-close').addEventListener('click',function(){
    $('agas-help-overlay').hidden=true;$('agas-help-open').focus();
  });
  $('agas-help-overlay').addEventListener('click',function(e){
    if (e.target===this) $('agas-help-close').click();
  });

  var previewSide = 'front';
  var previewRenderToken = 0;
  function choosePreviewMockup() {
    var stage = $('agas-editor-mockup');
    if (!stage || !window.AGASRealisticMockups) return;
    var category = cfg.adminTemplateMode ? $('agas-template-category').value :
      (template && template.category ? template.category : cfg.mockupCategory || '');
    var override = cfg.adminTemplateMode ? $('agas-template-mockup').value :
      (template && template.mockup_profile ? template.mockup_profile : '');
    window.AGASRealisticMockups.configure(stage, {
      kind: cfg.productKind, category:category,
      name:template && template.name ? template.name : '', override:override
    });
  }
  function previewArt(url) {
    choosePreviewMockup();
    $('agas-preview-flat-image').src = url;
    $('agas-preview-mockup-image').src = url;
    if (window.AGASRealisticMockups) window.AGASRealisticMockups.render($('agas-editor-mockup'), url);
    $('agas-dual-preview-error').hidden = true;
  }
  function previewError(err) {
    var target = $('agas-dual-preview-error');
    target.textContent = 'Could not render this side: ' + (err && err.message ? err.message : 'Unknown image error') + '. Exit Preview to continue editing.';
    target.hidden = false;
  }
  function showPreviewSide(which) {
    if (!viewing || (which === 'back' && !$('agas-two-sided').checked)) return;
    previewSide = which;
    var token = ++previewRenderToken;
    $('agas-preview-side-front').setAttribute('aria-pressed', which === 'front' ? 'true' : 'false');
    $('agas-preview-side-back').setAttribute('aria-pressed', which === 'back' ? 'true' : 'false');
    var json = sides[which];
    if (which === currentSide) {
      try {
        canvas.requestRenderAll();
        previewArt(canvas.toDataURL({format:'png', multiplier:Math.min(1,1100/canvas.getWidth())}));
      } catch (err) {previewError(err);}
      return;
    }
    // Render the other side without switching the live editing canvas or history.
    var scratch = new fabric.StaticCanvas(document.createElement('canvas'), {
      width:canvas.getWidth(),height:canvas.getHeight(),enableRetinaScaling:false,renderOnAddRemove:false
    });
    try {
      scratch.loadFromJSON(json && Array.isArray(json.objects) ? json : {objects:[],background:'#ffffff'}, function () {
        try {
          scratch.forEachObject(function(o){if(o.agasImagePlaceholder) o.visible=false;});
          scratch.renderAll();
          if (token === previewRenderToken && viewing) previewArt(scratch.toDataURL({format:'png', multiplier:Math.min(1,1100/canvas.getWidth())}));
        } catch (err) {if(token === previewRenderToken) previewError(err);}
        finally {scratch.dispose();}
      });
    } catch(err) {scratch.dispose();previewError(err);}
  }
  async function preview() {
    viewing = !viewing;
    if (viewing) capture(); // Keep the editing snapshot, not its temporary preview visibility.
    root.classList.toggle('agas-preview', viewing);
    $('agas-dual-preview').hidden = !viewing;
    var previewText = $('agas-preview').querySelector('span');
    if (previewText) previewText.textContent = viewing ? 'Exit Preview' : 'Preview';
    $('agas-preview').setAttribute('aria-label', viewing ? 'Exit preview' : 'Preview design');
    $('agas-preview').setAttribute('aria-expanded', viewing ? 'true' : 'false');
    canvas.discardActiveObject();canvas.selection = !viewing && !cfg.readonly;
    canvas.forEachObject(function (o) {
      if (o.agasImagePlaceholder) o.visible = !viewing;
      o.selectable = !viewing && !cfg.readonly && !o.agasLocked;
      o.hasControls = !viewing && !cfg.readonly && !o.agasLocked;
    });
    canvas.requestRenderAll();
    $('agas-preview-side-back').disabled = !$('agas-two-sided').checked;
    if (viewing) showPreviewSide(currentSide);
    else {
      previewRenderToken++;
      $('agas-preview-flat-image').removeAttribute('src');
      $('agas-preview-mockup-image').removeAttribute('src');
    }
    updateObjectActions();
    scheduleFit();
  }
  $('agas-preview').setAttribute('aria-controls', 'agas-dual-preview');
  $('agas-preview').setAttribute('aria-expanded', 'false');
  $('agas-preview').addEventListener('click', preview);
  $('agas-preview-side-front').addEventListener('click', function(){showPreviewSide('front');});
  $('agas-preview-side-back').addEventListener('click', function(){showPreviewSide('back');});
  /** Change templates in place, without navigating away from the canvas. */
  function mountTemplateSidebar() {
    if (!window.AGASTemplateSidebar) return;
    window.AGASTemplateSidebar.mount({
      root: root,
      templates: cfg.availableTemplates || [],
      getCurrentId: function () {return template ? Number(template.id) : 0;},
      onError: function (error) {message('Template unavailable: ' + (error.message || error), true);},
      onChoose: async function (chosen) {
        if (cfg.readonly || viewing) return false;
        var nextId = chosen ? Number(chosen.id) : 0;
        // Allow clearing an existing saved design even if its template ID is 0.
        // A customer editing saved artwork should also be able to reapply the original template.
        var currentTemplateId = template ? Number(template.id) : 0;
        if (currentTemplateId === nextId && !dirty && !savedDesign &&
            (nextId !== 0 || (!canvas.getObjects().length && !sides.back))) return false;
        var warning = cfg.adminTemplateMode
          ? 'Replace the artwork in the admin template being edited? Saving afterward will overwrite that template.'
          : 'Replace the current artwork with this template? This changes both sides. Saved designs remain unchanged until you save.';
        if (!window.confirm(warning)) return false;
        var previous = {template: template, sides: sides, side: currentSide,
          backEnabled: $('agas-two-sided').checked, dirty: dirty,
          category: cfg.mockupCategory, profile: cfg.mockupProfile};
        capture();
        try {
          var incoming = null;
          if (nextId) {
            var endpoint = restEndpoint('templates/' + nextId);
            endpoint.searchParams.set('product_id', String(cfg.productId));
            var response = await fetch(endpoint.toString(), {credentials:'same-origin', headers:{'X-WP-Nonce':cfg.nonce}});
            incoming = await response.json().catch(function () {return {};});
            if (!response.ok || !incoming.front || !Array.isArray(incoming.front.objects))
              throw new Error(incoming.message || 'This template could not be loaded.');
          }
          // Template seed coordinates are on the standard 900x600 canvas.
          // Reflow to the customer's currently selected print size.
          var sx = canvas.getWidth() / 900, sy = canvas.getHeight() / 600;
          var front = incoming ? scaledJson(incoming.front, sx, sy) : null;
          var back = incoming && incoming.back ? scaledJson(incoming.back, sx, sy) : null;
          var selected = incoming ? incoming : null;
          await loadJSON(front);
          template = selected;
          cfg.mockupCategory = selected ? selected.category : '';
          cfg.mockupProfile = selected ? selected.mockup_profile : '';
          sides = {front: serializedCanvas(), back: back};
          currentSide = 'front';
          $('agas-two-sided').checked = !!back;
          $('agas-side-front').classList.add('active');
          $('agas-side-back').classList.remove('active');
          updateSideControls();
          $('agas-bg').value = /^#[0-9A-Fa-f]{6}$/.test(canvas.backgroundColor) ? canvas.backgroundColor : '#ffffff';
          var header = root.querySelector('.agas-template-name');
          if (header) header.textContent = 'Template: ' + (chosen ? chosen.name : 'Blank design');
          resetHistory();
          dirty = true;
          renderTextLayers();
          updateObjectActions();
          scheduleSideThumbs();
          updateRuler();quote();scheduleFit();
          if (window.innerWidth < 1100) root.classList.remove('agas-tools-open');
          message(chosen ? 'Loaded ' + chosen.name + '. Customize it, then Save or Continue.' : 'Blank canvas ready.');
          return true;
        } catch (error) {
          template = previous.template;
          sides = previous.sides;
          currentSide = previous.side;
          cfg.mockupCategory = previous.category;
          cfg.mockupProfile = previous.profile;
          $('agas-two-sided').checked = previous.backEnabled;
          $('agas-side-front').classList.toggle('active', currentSide === 'front');
          $('agas-side-back').classList.toggle('active', currentSide === 'back');
          await loadJSON(previous.sides[currentSide]);
          updateSideControls();
          dirty = previous.dirty;
          resetHistory();renderTextLayers();updateObjectActions();
          throw error;
        }
      }
    });
  }

  window.addEventListener('beforeunload', function (e) {
    if (dirty) {e.preventDefault();e.returnValue = '';}
  });

  function shortcuts(e) {
    if (cfg.readonly || !$('agas-cart-choice').hidden) return;
    var node = document.activeElement;
    var isTyping = node && (['INPUT','TEXTAREA','SELECT'].includes(node.tagName) || node.isContentEditable || (active() && active().isEditing));
    if (isTyping) return;
    var command = (e.ctrlKey || e.metaKey) && !e.altKey;
    var key = e.key.toLowerCase();
    if (e.key === 'Enter' && isTextLayer(active()) && !command) {
      e.preventDefault(); editTextOnCanvas();
    }
    else if (command && key === 'z') {e.preventDefault();travel(e.shiftKey ? 1 : -1);}
    else if (command && key === 'y') {e.preventDefault();travel(1);}
    else if (command && key === 'c' && editableSelection()) {e.preventDefault();copySelected();}
    else if (command && key === 'v' && internalClipboard) {e.preventDefault();pasteSelected();}
    else if (command && key === 'x' && editableSelection()) {e.preventDefault();cutSelected();}
    else if (command && key === 'd' && editableSelection()) {e.preventDefault();duplicateSelected();}
    else if ((e.key === 'Delete' || e.key === 'Backspace') && editableSelection()) {e.preventDefault();deleteSelected();}
    else if (['ArrowUp','ArrowDown','ArrowLeft','ArrowRight'].includes(e.key) && editableSelection()) {
      e.preventDefault();
      var o = active();
      if (e.key === 'ArrowUp') o.top -= 1;
      if (e.key === 'ArrowDown') o.top += 1;
      if (e.key === 'ArrowLeft') o.left -= 1;
      if (e.key === 'ArrowRight') o.left += 1;
      o.setCoords();canvas.requestRenderAll();recordHistory();
    }
  }
  document.addEventListener('keydown', shortcuts);

  async function serializeAll() {
    if (viewing) await preview();
    capture();
    var front = sides.front;
    var back = $('agas-two-sided').checked ? (sides.back || {version:'5.3.1',background:'#ffffff',objects:[]}) : null;
    var was = currentSide;
    if (currentSide === 'back') {
      await switchSide('front');
    }
    canvas.discardActiveObject();canvas.requestRenderAll();
    var hiddenSlots = canvas.getObjects().filter(function(o) {return o.agasImagePlaceholder && o.visible !== false;});
    hiddenSlots.forEach(function(o) {o.visible = false;});
    canvas.requestRenderAll();
    var previewImage;
    try {
      previewImage = canvas.toDataURL({format:'png', multiplier: Math.min(.9, 800 / canvas.width), quality:.85});
    } finally {
      hiddenSlots.forEach(function(o) {o.visible = true;});
      canvas.requestRenderAll();
    }
    if (was === 'back') await switchSide('back');
    return {front: front, back: back, preview: previewImage};
  }
  async function postJson(route, body) {
    var response = await fetch(restEndpoint(route).toString(), {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json','X-WP-Nonce':cfg.nonce}, body:JSON.stringify(body)});
    var result = await response.json().catch(function () {return {};});
    if (!response.ok) throw new Error(result.message || 'Server could not save this design.');
    return result;
  }

  async function saveTemplate() {
    if (!cfg.adminTemplateMode) throw new Error('Template editing is restricted to administrators.');
    var name=$('agas-template-title').value.trim();
    if (!name) throw new Error('Enter a template title.');
    var category=$('agas-template-category').value.trim() || 'Business';
    var status=$('agas-template-status').value;
    if (!['draft','publish'].includes(status)) throw new Error('Select Draft or Published.');
    var assigned=Array.from(root.querySelectorAll('.agas-template-product:checked')).map(function(el){return Number(el.value);});
    if (!assigned.includes(Number(cfg.productId))) {
      throw new Error('Assign the template to the product currently being edited.');
    }
    var json = await serializeAll();
    var result = await postJson('templates', {
      template_id:cfg.editingTemplate || 0,product_id:cfg.productId,
      product_ids:assigned,name:name,category:category,status:status,
      mockup_profile:$('agas-template-mockup').value,
      front:json.front,back:json.back,preview:json.preview
    });
    cfg.editingTemplate = result.id;
    cfg.adminTemplateMode = true;
    template = {id:result.id,name:name,category:category,status:status,product_ids:assigned,
      mockup_profile:$('agas-template-mockup').value,
      front:json.front,back:json.back};
    window.history.replaceState({}, '', result.template_url);
    message('Template #' + result.id + ' saved as ' + (status==='publish'?'Published':'Draft') + '.');
    dirty = false;
    return result;
  }
  /** Ask before writing to an existing cart design: Save is not a silent overwrite. */
  function chooseCartMode(thenCart) {
    if (!originalCartDesign) return Promise.resolve('auto');
    var overlay = $('agas-cart-choice');
    if (!overlay) return Promise.reject(new Error('Cart choice dialog unavailable.'));
    var replace = $('agas-choice-replace');
    var add = $('agas-choice-add');
    var cancel = $('agas-choice-cancel');
    var previousFocus = document.activeElement;
    var help = $('agas-cart-choice-help');
    help.textContent = thenCart
      ? 'Choose what happens when this edited design goes into your cart.'
      : 'Choose where to save your changes. A separate copy keeps the original cart design untouched.';
    replace.querySelector('strong').textContent = thenCart ? 'Replace existing cart item' : 'Save changes to existing design';
    add.querySelector('strong').textContent = thenCart ? 'Add as a separate cart item' : 'Save as a separate design';
    overlay.hidden = false;
    replace.focus();
    return new Promise(function (resolve) {
      function finish(value) {
        replace.removeEventListener('click', onReplace);
        add.removeEventListener('click', onAdd);
        cancel.removeEventListener('click', onCancel);
        overlay.removeEventListener('click', onBackdrop);
        document.removeEventListener('keydown', onKeyDown, true);
        overlay.hidden = true;
        if (previousFocus && typeof previousFocus.focus === 'function') previousFocus.focus();
        resolve(value);
      }
      function onReplace() {finish('replace');}
      function onAdd() {finish('add');}
      function onCancel() {finish(null);}
      function onBackdrop(e) {if (e.target === overlay) finish(null);}
      function onKeyDown(e) {
        if (e.key === 'Escape') {e.preventDefault();e.stopPropagation();finish(null);}
        if (e.key === 'Tab') {
          var controls = [replace, add, cancel];
          var index = controls.indexOf(document.activeElement);
          if (e.shiftKey && index === 0) {e.preventDefault();cancel.focus();}
          else if (!e.shiftKey && index === 2) {e.preventDefault();replace.focus();}
        }
      }
      replace.addEventListener('click', onReplace);
      add.addEventListener('click', onAdd);
      cancel.addEventListener('click', onCancel);
      overlay.addEventListener('click', onBackdrop);
      document.addEventListener('keydown', onKeyDown, true);
    });
  }
  async function saveDesign(cartMode) {
    var json = await serializeAll();
    // Cloning creates a truly separate saved-design record and access token.
    // Editing an existing item uses its original ID ONLY after explicit choice.
    var target = cartMode === 'replace' && originalCartDesign
      ? originalCartDesign : (cartMode === 'add' && originalCartDesign ? null : savedDesign);
    var payload = {product_id:cfg.productId,template_id:(template ? template.id : 0),front:json.front,back:json.back,options:getOptions(),preview:json.preview,design_id:target ? target.id : 0,token:target ? target.token : ''};
    var result = await postJson('designs', payload);
    if (cartMode === 'replace' && originalCartDesign && result.edit_url) {
      var editLink = new URL(result.edit_url, window.location.href);
      editLink.searchParams.set('agas_from_cart', '1');
      if (originalCartDesign.cartKey) editLink.searchParams.set('agas_cart_key', originalCartDesign.cartKey);
      result.edit_url = editLink.toString();
    }
    savedDesign = {id:result.id,token:result.token};
    if (cartMode === 'add' && originalCartDesign) {
      // Subsequent saves update the new copy; original cart item remains intact.
      originalCartDesign = null;
      cfg.returnToCart = false;
    }
    dirty = false;
    message(cartMode === 'add' ? 'Separate design #' + result.id + ' saved. Your original cart item is unchanged.' :
      (cartMode === 'replace' ? 'Existing design #' + result.id + ' updated.' : 'Design saved as #' + result.id + '.') +
      ' Editing link: ' + result.edit_url);
    window.history.replaceState({}, '', result.edit_url);
    return result;
  }
  async function save(thenCart) {
    var button = $('agas-continue');
    var saveButton = $('agas-save');
    button.disabled = true;
    saveButton.disabled = true;
    try {
      if (cfg.adminTemplateMode) {
        await saveTemplate();
      } else {
        var source = originalCartDesign;
        var mode = await chooseCartMode(thenCart);
        if (mode === null) return;
        var result = await saveDesign(mode);
        if (thenCart) {
          var form = document.createElement('form');form.method = 'POST';form.action = cfg.cartEndpoint;
          var data = {action:'agas_design_add_cart',_wpnonce:cfg.cartNonce,design_id:result.id,token:result.token,
            cart_mode:source ? mode : 'auto',source_design_id:source ? source.id : 0,source_cart_key:source ? source.cartKey : ''};
          Object.keys(data).forEach(function (key) {var field = document.createElement('input');field.type = 'hidden';field.name = key;field.value = data[key];form.appendChild(field);});
          document.body.appendChild(form);
          form.submit();
        }
      }
    } catch (e) {message(e.message, true);}
    finally {button.disabled = false; saveButton.disabled = false;}
  }
  $('agas-save').addEventListener('click', function () {save(false);});
  $('agas-continue').addEventListener('click', function () {save(true);});

  async function setup() {
    message('Loading design editor...');
    quote(); // Pricing should not be blocked by problems in Fabric initialization.
    var initial = template && template.front ? template.front : null;
    if (template && template.back && !savedDesign) {
      sides.back = template.back;
      $('agas-two-sided').checked = true;
      updateSideControls();
    }
    if (savedDesign && savedDesign.front) {
      initial = savedDesign.front;
      sides.back = savedDesign.back || null;
      setOptions(savedDesign.options);
    }
    await loadJSON(initial);
    renderTextLayers();
    sides.front = serializedCanvas();
    $('agas-bg').value = /^#[0-9A-Fa-f]{6}$/.test(canvas.backgroundColor) ? canvas.backgroundColor : '#ffffff';
    $('agas-unit').dataset.previous = $('agas-unit').value;
    updateRuler();quote();resetHistory();scheduleFit();dirty = false;
    updateObjectActions();
    mountTemplateSidebar();
    if (cfg.readonly) {
      root.classList.add('agas-readonly');
      canvas.selection = false;
      canvas.forEachObject(function (obj) {obj.selectable = false;obj.evented = false;});
      root.querySelectorAll('button, input, select').forEach(function (control) {
        if (!['agas-preview','agas-preview-side-front','agas-preview-side-back'].includes(control.id)) control.disabled = true;
      });
      message('Read-only order design preview.');
    } else {
      message('Ready. Select an element to edit it above the canvas. Use the left icons to add content.');
    }
    window.AGASEditorReady = true;
    document.dispatchEvent(new CustomEvent('agas:editor-ready'));
  }
  setup().catch(function (error) { console.error('[AGAS Studio] Initialization error', error); message('Designer failed to load: ' + error.message + '. See browser Console for details.', true); });
})();

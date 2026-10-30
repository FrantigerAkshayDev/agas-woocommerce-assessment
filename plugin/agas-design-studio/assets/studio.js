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
    $('agas-grommets').checked = !!o.grommets;
    $('agas-pocket').checked = !!o.pole_pocket;
    $('agas-size').value = 'custom';
    updateSideControls();
    updateRuler();
  }

  /** Save JSON even if a malformed legacy object prevents Fabric serialization. */
  function safeObjectJSON(obj) {
    try {
      return obj.toObject(['agasLocked','agasImagePlaceholder','agasPlaceholderKind','agasLogoSlot','agasOriginalName']);
    } catch (err) {
      console.warn('[AGAS Studio] Object serialization fallback:', obj.type, err);
      var props = ['left','top','width','height','scaleX','scaleY','angle','opacity',
        'originX','originY','flipX','flipY','fill','stroke','strokeWidth',
        'rx','ry','radius','text','fontSize','fontFamily','fontWeight',
        'fontStyle','underline','textAlign','lineHeight','charSpacing',
        'lockMovementX','lockMovementY','selectable','evented','agasLocked',
        'agasImagePlaceholder','agasPlaceholderKind','agasLogoSlot','agasOriginalName'];
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
      var normal = canvas.toJSON(['agasLocked','agasImagePlaceholder','agasPlaceholderKind','agasLogoSlot','agasOriginalName']);
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
  function updateObjectActions() {
    var sel = active();
    var canEdit = !!editableSelection() && !cfg.readonly && !viewing;
    ['agas-copy','agas-cut','agas-duplicate','agas-delete','agas-front','agas-backward',
     'agas-rotate-left','agas-rotate-right','agas-flip-h','agas-flip-v','agas-center','agas-opacity']
      .forEach(function(id) { $(id).disabled = !canEdit; });
    $('agas-paste').disabled = !internalClipboard || viewing || cfg.readonly;
    $('agas-lock').disabled = !sel || sel.type === 'activeSelection' || viewing || cfg.readonly;
    $('agas-lock').textContent = sel && sel.agasLocked ? 'Unlock object' : 'Lock object';
    var canReplace = sel && sel.type !== 'activeSelection' &&
      (sel.agasImagePlaceholder || sel.type === 'image') && !cfg.readonly && !viewing && !sel.agasLocked;
    $('agas-replace-image').disabled = !canReplace;
    $('agas-replace-image').textContent = sel && sel.agasImagePlaceholder ? 'Upload logo to this placeholder' : 'Replace selected image';
    if (sel) fontControls();
  }
  canvas.on('object:added', recordHistory);
  canvas.on('object:removed', recordHistory);
  canvas.on('object:modified', recordHistory);
  canvas.on('text:changed', recordHistory);
  canvas.on('selection:created', updateObjectActions);
  canvas.on('selection:updated', updateObjectActions);
  canvas.on('selection:cleared', updateObjectActions);

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
    }, ['agasLocked','agasImagePlaceholder','agasPlaceholderKind','agasLogoSlot','agasOriginalName']);
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
    }, ['agasLocked','agasImagePlaceholder','agasPlaceholderKind','agasLogoSlot','agasOriginalName']);
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
    var locked = !selected.agasLocked;
    selected.set({agasLocked:locked,lockMovementX:locked,lockMovementY:locked,
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
  ['agas-two-sided', 'agas-grommets', 'agas-pocket'].forEach(function (id) {$(id).addEventListener('change', function () {updateSideControls();quote();});});
  $('agas-side-front').addEventListener('click', function () {switchSide('front');});
  $('agas-side-back').addEventListener('click', function () {switchSide('back');});
  $('agas-guides').addEventListener('change', function () {root.classList.toggle('agas-hide-guides', !this.checked);});
  $('agas-zoom').addEventListener('input', function () {
    zoomMultiplier = Number(this.value) / 100;
    fitCanvas();
    $('agas-zoom-out').textContent = this.value + '% of fit';
  });

  async function preview() {
    viewing = !viewing;
    root.classList.toggle('agas-preview', viewing);
    $('agas-preview').textContent = viewing ? 'Exit Preview' : 'Preview';
    canvas.discardActiveObject();canvas.selection = !viewing;
    canvas.forEachObject(function (o) {
      if (o.agasImagePlaceholder) o.visible = !viewing;
      o.selectable = !viewing;
      o.hasControls = !viewing && !o.agasLocked;
    });
    canvas.requestRenderAll();
    updateObjectActions();
    scheduleFit();
  }
  $('agas-preview').addEventListener('click', preview);
  $('agas-change-template').addEventListener('click', function (e) {
    if (dirty && !window.confirm('Changing the template will discard changes that were not saved. Continue?')) e.preventDefault();
  });
  window.addEventListener('beforeunload', function (e) {
    if (dirty) {e.preventDefault();e.returnValue = '';}
  });

  function shortcuts(e) {
    if (cfg.readonly) return;
    var node = document.activeElement;
    var isTyping = node && (['INPUT','TEXTAREA','SELECT'].includes(node.tagName) || node.isContentEditable || (active() && active().isEditing));
    if (isTyping) return;
    var command = (e.ctrlKey || e.metaKey) && !e.altKey;
    var key = e.key.toLowerCase();
    if (command && key === 'z') {e.preventDefault();travel(e.shiftKey ? 1 : -1);}
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
    var json = await serializeAll();
    var name = window.prompt('Template title', (template && template.name) || 'New design template');
    if (!name) throw new Error('Template save cancelled');
    var cat = window.prompt('Template category (Business, Events, Safety, Sale)', (template && template.category) || 'Business');
    if (!cat) cat = 'Business';
    var result = await postJson('templates', {template_id:cfg.editingTemplate || 0,product_id:cfg.productId,product_ids:(template && template.product_ids) || [cfg.productId],name:name,category:cat,front:json.front,preview:json.preview});
    cfg.editingTemplate = result.id;
    window.history.replaceState({}, '', result.template_url);
    message('Template saved as #' + result.id + '.');
    dirty = false;
    return result;
  }
  async function saveDesign() {
    var json = await serializeAll();
    var payload = {product_id:cfg.productId,template_id:(template ? template.id : 0),front:json.front,back:json.back,options:getOptions(),preview:json.preview,design_id:savedDesign ? savedDesign.id : 0,token:savedDesign ? savedDesign.token : ''};
    var result = await postJson('designs', payload);
    savedDesign = {id:result.id,token:result.token};
    dirty = false;
    message('Design saved as #' + result.id + '. Shareable editing link: ' + result.edit_url);
    window.history.replaceState({}, '', result.edit_url);
    return result;
  }
  async function save(thenCart) {
    var button = $('agas-continue');
    button.disabled = true;
    try {
      if (cfg.editingTemplate) {
        await saveTemplate();
      } else {
        var result = await saveDesign();
        if (thenCart) {
          var form = document.createElement('form');form.method = 'POST';form.action = cfg.cartEndpoint;
          var data = {action:'agas_design_add_cart',_wpnonce:cfg.cartNonce,design_id:result.id,token:result.token};
          Object.keys(data).forEach(function (key) {var field = document.createElement('input');field.type = 'hidden';field.name = key;field.value = data[key];form.appendChild(field);});
          document.body.appendChild(form);
          form.submit();
        }
      }
    } catch (e) {message(e.message, true);}
    finally {button.disabled = false;}
  }
  $('agas-save').addEventListener('click', function () {save(false);});
  $('agas-continue').addEventListener('click', function () {save(true);});

  async function setup() {
    message('Loading design editor...');
    quote(); // Pricing should not be blocked by problems in Fabric initialization.
    var initial = template && template.front ? template.front : null;
    if (savedDesign && savedDesign.front) {
      initial = savedDesign.front;
      sides.back = savedDesign.back || null;
      setOptions(savedDesign.options);
    }
    await loadJSON(initial);
    sides.front = serializedCanvas();
    $('agas-bg').value = /^#[0-9A-Fa-f]{6}$/.test(canvas.backgroundColor) ? canvas.backgroundColor : '#ffffff';
    $('agas-unit').dataset.previous = $('agas-unit').value;
    updateRuler();quote();resetHistory();scheduleFit();dirty = false;
    updateObjectActions();
    if (cfg.readonly) {
      root.classList.add('agas-readonly');
      canvas.selection = false;
      canvas.forEachObject(function (obj) {obj.selectable = false;obj.evented = false;});
      root.querySelectorAll('button, input, select').forEach(function (control) {
        if (control.id !== 'agas-preview') control.disabled = true;
      });
      message('Read-only order design preview.');
    } else {
      message('Ready. Edit the template, then Save or Continue to Cart.');
    }
  }
  setup().catch(function (error) { console.error('[AGAS Studio] Initialization error', error); message('Designer failed to load: ' + error.message + '. See browser Console for details.', true); });
})();

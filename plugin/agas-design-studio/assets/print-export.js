(function () {
  'use strict';

  var status = document.getElementById('agas-print-status');
  var details = document.getElementById('agas-print-dimensions');
  var dataElement = document.getElementById('agas-print-data');
  if (!status || !dataElement) return;
  var config;
  try { config = JSON.parse(dataElement.textContent); }
  catch (err) { status.textContent = 'Invalid saved design data: ' + err.message; status.className = 'bad'; return; }

  function message(text, error) {
    status.textContent = text;
    status.className = error ? 'bad' : 'good';
  }

  var fabricReady = typeof window.fabric !== 'undefined';
  var pdfReady = !!(window.jspdf && window.jspdf.jsPDF);
  var svgReady = pdfReady && typeof window.jspdf.jsPDF.API.svg === 'function';
  if (!fabricReady) {
    message('Fabric.js failed to load. Check internet access before exporting artwork.', true);
    return;
  }
  if (!pdfReady) {
    document.querySelectorAll('[data-agas-export="pdf"]').forEach(function (button) { button.disabled = true; });
    message('PNG export is available; PDF export requires jsPDF to load.', true);
  } else {
    message(svgReady ? 'Ready to export: PDF vector conversion available.' : 'Ready to export: PDF will use a raster fallback because svg2pdf.js is unavailable.', false);
  }

  function inches(value, unit) {
    var multiplier = unit === 'cm' ? 1 / 2.54 : unit === 'ft' ? 12 : 1;
    return Number(value) * multiplier;
  }
  var widthIn = inches(config.size.width, config.size.unit);
  var heightIn = inches(config.size.height, config.size.unit);
  var bleed = Number(config.bleedInches) || .125;
  var dpi = Math.max(150, Number(config.dpi) || 150);
  if (!Number.isFinite(widthIn) || !Number.isFinite(heightIn) || widthIn <= 0 || heightIn <= 0) {
    message('Invalid physical print size in saved design.', true);
    return;
  }
  var totalWIn = widthIn + 2 * bleed;
  var totalHIn = heightIn + 2 * bleed;
  var pixelW = Math.round(totalWIn * dpi);
  var pixelH = Math.round(totalHIn * dpi);
  details.textContent = 'Print area: ' + widthIn.toFixed(2) + ' × ' + heightIn.toFixed(2) + ' inches; PDF page with bleed: ' + totalWIn.toFixed(2) + ' × ' + totalHIn.toFixed(2) + ' inches; PNG dimensions: ' + pixelW + ' × ' + pixelH + ' pixels (' + dpi + ' DPI).';

  function safeName(value) {
    return String(value).replace(/[^a-zA-Z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 70) || 'Product';
  }
  function nameFor(side, extension) {
    function compact(n) { return String(Number(n)).replace(/\./g, 'p'); }
    var suffix = compact(config.size.width) + 'x' + compact(config.size.height) + config.size.unit;
    return [config.orderId, config.lineIndex, safeName(config.product), suffix, side === 'back' ? 'Back' : 'Front'].join('_') + '.' + extension;
  }
  function hexRGB(color) {
    var match = /^#([0-9a-f]{6})$/i.exec(String(color));
    if (!match) return [255, 255, 255];
    var hex = match[1];
    return [parseInt(hex.slice(0, 2), 16), parseInt(hex.slice(2, 4), 16), parseInt(hex.slice(4, 6), 16)];
  }
  function backgroundFor(scene) {
    var background = String(scene.background || scene.backgroundColor || '#ffffff');
    return /^#[0-9a-f]{6}$/i.test(background) ? background : '#ffffff';
  }
  function downloadBlob(blob, name) {
    var url = URL.createObjectURL(blob);
    var link = document.createElement('a');
    link.href = url; link.download = name;
    document.body.appendChild(link); link.click(); link.remove();
    window.setTimeout(function () { URL.revokeObjectURL(url); }, 20000);
  }
  function dataUrlToBlob(dataUrl) {
    var chunks = dataUrl.split(',');
    if (chunks.length !== 2) throw new Error('Could not serialize PNG.');
    var bytes = atob(chunks[1]);
    var buffer = new Uint8Array(bytes.length);
    for (var i = 0; i < bytes.length; i++) buffer[i] = bytes.charCodeAt(i);
    return new Blob([buffer], {type: 'image/png'});
  }

  function loadScene(side) {
    var scene = config[side];
    if (!scene || !Array.isArray(scene.objects)) return Promise.reject(new Error('Saved ' + side + '-side canvas is missing.'));
    // Incomplete image slots are UI aids, never print artwork.
    var clean = Object.assign({}, scene, {
      objects: scene.objects.filter(function (obj) { return !obj.agasImagePlaceholder; })
    });
    var el = document.createElement('canvas');
    var visual = new fabric.StaticCanvas(el, {
      width: 900, height: 600, enableRetinaScaling: false, renderOnAddRemove: false
    });
    return new Promise(function (resolve, reject) {
      try {
        visual.loadFromJSON(clean, function () {
          try {
            visual.renderAll();
            resolve({canvas: visual, scene: clean});
          } catch (err) { visual.dispose(); reject(err); }
        });
      } catch (err) { visual.dispose(); reject(err); }
    });
  }

  function newFinalCanvas() {
    if (pixelW > 16000 || pixelH > 16000 || pixelW * pixelH > 80000000) {
      throw new Error('150-DPI raster export exceeds the demo browser memory limit (' + pixelW + ' × ' + pixelH + '). Use PDF or a production rendering service for this print size.');
    }
    var out = document.createElement('canvas');
    out.width = pixelW; out.height = pixelH;
    var ctx = out.getContext('2d', {alpha: false});
    if (!ctx) throw new Error('Could not initialize high-resolution output.');
    return {canvas: out, ctx: ctx};
  }
  function imageLoaded(src) {
    return new Promise(function (resolve, reject) {
      var image = new Image();
      image.onload = function () { resolve(image); };
      image.onerror = function () { reject(new Error('Failed to load the rasterized design.')); };
      image.src = src;
    });
  }
  /** Set the PNG pHYs resolution to 150 pixels per inch (not just pixel dimensions). */
  async function withDpiMetadata(blob) {
    var bytes = new Uint8Array(await blob.arrayBuffer());
    var signature = [137, 80, 78, 71, 13, 10, 26, 10];
    if (signature.some(function (v, i) {return bytes[i] !== v;})) throw new Error('Generated PNG has an invalid signature.');
    function read32(o) {return (((bytes[o] << 24) >>> 0) + (bytes[o + 1] << 16) + (bytes[o + 2] << 8) + bytes[o + 3]) >>> 0;}
    function set32(a, o, v) {a[o] = (v >>> 24) & 255; a[o+1] = (v >>> 16) & 255; a[o+2] = (v >>> 8) & 255; a[o+3] = v & 255;}
    function crc32(chunk) {
      var crc = 0xffffffff;
      for (var i = 0; i < chunk.length; i++) {
        crc ^= chunk[i];
        for (var b = 0; b < 8; b++) crc = (crc & 1) ? (0xedb88320 ^ (crc >>> 1)) : (crc >>> 1);
      }
      return (crc ^ 0xffffffff) >>> 0;
    }
    var chunk = new Uint8Array(21);
    set32(chunk, 0, 9);
    chunk.set([112, 72, 89, 115], 4); // pHYs
    var pixelsPerMetre = Math.round(dpi / .0254);
    set32(chunk, 8, pixelsPerMetre);
    set32(chunk, 12, pixelsPerMetre);
    chunk[16] = 1;
    set32(chunk, 17, crc32(chunk.slice(4, 17)));
    var parts = [bytes.slice(0, 8)], offset = 8, added = false;
    while (offset + 12 <= bytes.length) {
      var length = read32(offset);
      var end = offset + 12 + length;
      if (end > bytes.length) throw new Error('Generated PNG chunk is corrupt.');
      var type = String.fromCharCode.apply(null, Array.from(bytes.slice(offset + 4, offset + 8)));
      if (type === 'IDAT' && !added) {parts.push(chunk); added = true;}
      if (type !== 'pHYs') parts.push(bytes.slice(offset, end));
      offset = end;
    }
    if (!added) throw new Error('Generated PNG has no pixel data.');
    return new Blob(parts, {type:'image/png'});
  }

  async function highResolutionPNG(visual, scene) {
    var target = newFinalCanvas();
    var ctx = target.ctx;
    ctx.fillStyle = backgroundFor(scene);
    ctx.fillRect(0, 0, pixelW, pixelH);
    var bleedPx = bleed * dpi;
    var contentW = widthIn * dpi;
    var contentH = heightIn * dpi;
    var multiplier = Math.min(contentW / 900, contentH / 600);
    // Render the objects again at print resolution; simply stretching the
    // browser's 900x600 preview would not produce 150-DPI artwork.
    var source = visual.toDataURL({format: 'png', multiplier: multiplier, enableRetinaScaling: false});
    var image = await imageLoaded(source);
    var drawnW = 900 * multiplier, drawnH = 600 * multiplier;
    ctx.drawImage(image, bleedPx + (contentW - drawnW) / 2, bleedPx + (contentH - drawnH) / 2, drawnW, drawnH);
    if (!target.canvas.toBlob) return dataUrlToBlob(target.canvas.toDataURL('image/png'));
    return new Promise(function (resolve, reject) {
      target.canvas.toBlob(function (blob) {
        if (blob) resolve(blob);
        else reject(new Error('Browser could not encode the full-size PNG.'));
      }, 'image/png');
    });
  }

  async function pdfDocument(visual, scene) {
    var jsPDF = window.jspdf.jsPDF;
    var pageWPt = totalWIn * 72, pageHPt = totalHIn * 72;
    if (pageWPt > 14380 || pageHPt > 14380) throw new Error('PDF exceeds the page size supported by this renderer.');
    var pdf = new jsPDF({unit: 'pt', format: [pageWPt, pageHPt],
      orientation: pageWPt >= pageHPt ? 'landscape' : 'portrait', compress: true, precision: 6});
    var bg = hexRGB(backgroundFor(scene));
    pdf.setFillColor(bg[0], bg[1], bg[2]);
    pdf.rect(0, 0, pageWPt, pageHPt, 'F');
    // Physical dimensions are in PDF points; one point is exactly 1/72 inch.
    var marginPt = bleed * 72;
    var widthPt = widthIn * 72, heightPt = heightIn * 72;
    var usedVector = false;
    if (typeof pdf.svg === 'function') {
      try {
        var svgSource = visual.toSVG();
        var parsed = new DOMParser().parseFromString(svgSource, 'image/svg+xml');
        var svgNode = parsed.documentElement;
        if (!svgNode || svgNode.nodeName.toLowerCase() !== 'svg') throw new Error('Fabric SVG invalid');
        svgNode.setAttribute('width', String(widthPt));
        svgNode.setAttribute('height', String(heightPt));
        svgNode.setAttribute('viewBox', '0 0 900 600');
        svgNode.setAttribute('preserveAspectRatio', 'xMidYMid meet');
        await pdf.svg(svgNode, {x: marginPt, y: marginPt, width: widthPt, height: heightPt});
        usedVector = true;
      } catch (err) {
        console.warn('[AGAS Print] SVG-to-PDF fallback:', err);
        usedVector = false;
      }
    }
    if (!usedVector) {
      var data = visual.toDataURL({format: 'png', multiplier: 3});
      var scale = Math.min(widthPt / 900, heightPt / 600);
      pdf.addImage(data, 'PNG', marginPt + (widthPt - 900 * scale) / 2,
        marginPt + (heightPt - 600 * scale) / 2, 900 * scale, 600 * scale);
    }
    return {blob: pdf.output('blob'), vector: usedVector};
  }

  var busy = false;
  async function exportFile(side, kind) {
    if (busy) return;
    busy = true;
    document.querySelectorAll('[data-agas-export]').forEach(function (btn) {btn.disabled = true;});
    var loaded = null;
    try {
      message('Rendering ' + side + ' side as ' + kind.toUpperCase() + '…', false);
      loaded = await loadScene(side);
      var fileName = nameFor(side, kind);
      if (kind === 'png') {
        var png = await highResolutionPNG(loaded.canvas, loaded.scene);
        downloadBlob(await withDpiMetadata(png), fileName);
        message('Downloaded ' + fileName + ' (' + pixelW + ' × ' + pixelH + ' pixels, ' + dpi + ' DPI physical output).', false);
      } else {
        var pdf = await pdfDocument(loaded.canvas, loaded.scene);
        downloadBlob(pdf.blob, fileName);
        message('Downloaded ' + fileName + ' at true physical page size with ' + (pdf.vector ? 'SVG vector rendering' : 'a raster fallback') + '. Verify fonts, bleed, and artwork before production.', false);
      }
    } catch (err) {
      console.error('[AGAS Print]', err);
      message('Export failed: ' + err.message, true);
    } finally {
      if (loaded && loaded.canvas) loaded.canvas.dispose();
      busy = false;
      document.querySelectorAll('[data-agas-export]').forEach(function (btn) {btn.disabled = false;});
    }
  }
  document.querySelectorAll('[data-agas-export]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      exportFile(btn.getAttribute('data-side'), btn.getAttribute('data-agas-export'));
    });
  });
})();

/*
 * AGAS Design Studio: dependency-free JPEG-in-PDF fallback.
 * Embeds a high-resolution JPEG as an image XObject on a physically sized PDF page.
 * This is a raster fallback, not a vector replacement for jsPDF/svg2pdf.
 */
(function (root) {
  'use strict';

  function rasterPdf(jpegBytes, widthPx, heightPx, widthPt, heightPt) {
    if (!(jpegBytes instanceof Uint8Array) || jpegBytes.length < 4 ||
        jpegBytes[0] !== 0xff || jpegBytes[1] !== 0xd8 ||
        jpegBytes[jpegBytes.length - 2] !== 0xff || jpegBytes[jpegBytes.length - 1] !== 0xd9) {
      throw new Error('Raster PDF requires valid JPEG data.');
    }
    if (![widthPx, heightPx, widthPt, heightPt].every(Number.isFinite) ||
        widthPx < 1 || heightPx < 1 || widthPt <= 0 || heightPt <= 0 ||
        widthPt > 14400 || heightPt > 14400) {
      throw new Error('Print dimensions are invalid or exceed the PDF page-size limit.');
    }
    widthPx = Math.round(widthPx);
    heightPx = Math.round(heightPx);
    var encoder = new TextEncoder();
    var pieces = [];
    var offsets = [0];
    var size = 0;
    function addText(s) {
      var bytes = encoder.encode(s);
      pieces.push(bytes);
      size += bytes.byteLength;
    }
    function addBytes(bytes) {
      pieces.push(bytes);
      size += bytes.byteLength;
    }
    function addObject(n, body) {
      offsets[n] = size;
      addText(n + ' 0 obj\n' + body + '\nendobj\n');
    }
    var w = widthPt.toFixed(4), h = heightPt.toFixed(4);
    var content = 'q\n' + w + ' 0 0 ' + h + ' 0 0 cm\n/Artwork Do\nQ\n';
    addText('%PDF-1.4\n%AGAS\n');
    addObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
    addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    addObject(3, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' + w + ' ' + h + '] /Resources << /ProcSet [/PDF /ImageC] /XObject << /Artwork 4 0 R >> >> /Contents 5 0 R >>');
    offsets[4] = size;
    addText('4 0 obj\n<< /Type /XObject /Subtype /Image /Width ' + widthPx + ' /Height ' + heightPx + ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' + jpegBytes.length + ' >>\nstream\n');
    addBytes(jpegBytes);
    addText('\nendstream\nendobj\n');
    addObject(5, '<< /Length ' + encoder.encode(content).byteLength + ' >>\nstream\n' + content + 'endstream');
    var xrefOffset = size;
    addText('xref\n0 6\n0000000000 65535 f \n');
    for (var i = 1; i <= 5; i++) {
      addText(String(offsets[i]).padStart(10, '0') + ' 00000 n \n');
    }
    addText('trailer\n<< /Root 1 0 R /Size 6 >>\nstartxref\n' + xrefOffset + '\n%%EOF\n');
    return new Blob(pieces, {type: 'application/pdf'});
  }

  root.AGASRasterPDF = {create: rasterPdf};
})(typeof window === 'undefined' ? globalThis : window);

(function () {
  'use strict';
  function init() {
    var product = document.getElementById('agas-product-select');
    var category = document.getElementById('agas-category-filter');
    var search = document.getElementById('agas-gallery-search');
    var cards = Array.from(document.querySelectorAll('.agas-template-card'));
    var empty = document.getElementById('agas-gallery-empty');
    if (!cards.length) return;
    if (product) product.addEventListener('change', function () { window.location.href = product.value; });
    function update() {
      var query = (search ? search.value : '').trim().toLowerCase();
      var cat = (category ? category.value : '').trim().toLowerCase();
      var visible = 0;
      cards.forEach(function (card) {
        var match = (!query || card.dataset.name.includes(query)) && (!cat || card.dataset.category === cat);
        card.hidden = !match;
        if (match) visible++;
      });
      if (empty) empty.hidden = visible > 0;
    }
    if (search) search.addEventListener('input', update);
    if (category) category.addEventListener('change', update);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

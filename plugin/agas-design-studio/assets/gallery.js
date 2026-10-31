(function () {
  'use strict';
  function init() {
    var root = document.getElementById('agas-gallery');
    if (!root) return;
    var product = document.getElementById('agas-product-select');
    var category = document.getElementById('agas-category-filter');
    var search = document.getElementById('agas-gallery-search');
    var clear = document.getElementById('agas-gallery-clear');
    var cards = Array.from(root.querySelectorAll('.agas-template-card'));
    var empty = document.getElementById('agas-gallery-empty');
    var results = document.getElementById('agas-gallery-results');
    var preview = document.getElementById('agas-gallery-mockup');
    var image = document.getElementById('agas-gallery-mockup-image');
    var fallback = document.getElementById('agas-gallery-mockup-fallback');
    var name = document.getElementById('agas-selected-name');
    var chosenCategory = document.getElementById('agas-selected-category');
    var mockupType = document.getElementById('agas-selected-mockup-type');
    var cta = document.getElementById('agas-selected-customize');
    var selected = cards.find(function(card){return card.classList.contains('is-selected');}) || null;
    if (image) image.addEventListener('error', function () {image.hidden = true; fallback.hidden = false;});

    function choose(card) {
      selected = card;
      cards.forEach(function (item) {
        var active = item === card;
        item.classList.toggle('is-selected', active);
        var button = item.querySelector('.agas-card-pick');
        if (button) button.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
      if (!card) {
        name.textContent = 'No matching designs';
        chosenCategory.textContent = 'Try changing your filters.';
        image.hidden = true;
        image.removeAttribute('src');
        fallback.textContent = 'Choose a template';
        if (mockupType) mockupType.textContent = '';
        fallback.hidden = false;
        cta.hidden = true;
        if (window.AGASRealisticMockups) window.AGASRealisticMockups.render(preview, '');
        return;
      }
      name.textContent = card.dataset.name || 'Selected design';
      chosenCategory.textContent = card.dataset.category || '';
      if (window.AGASRealisticMockups && preview) {
        var details = window.AGASRealisticMockups.configure(preview, {
          kind: root.dataset.productKind || 'banner',
          category: card.dataset.category || '',
          name: card.dataset.name || '',
          override: card.dataset.mockupProfile || ''
        });
        if (mockupType && details) mockupType.textContent = 'Physical format: ' + details.label;
      }
      var source = card.dataset.preview || '';
      image.hidden = !source;
      if (source) image.src = source;
      else image.removeAttribute('src');
      fallback.hidden = !!source;
      fallback.style.backgroundColor = card.dataset.background || '#294B72';
      fallback.textContent = card.dataset.name || 'Customize your message';
      cta.href = card.dataset.editorUrl;
      cta.hidden = false;
      if (window.AGASRealisticMockups) window.AGASRealisticMockups.render(preview, source);
    }
    function filter() {
      var query = (search ? search.value : '').trim().toLocaleLowerCase();
      var selectedCategory = (category ? category.value : '').trim().toLocaleLowerCase();
      var visible = [];
      cards.forEach(function (card) {
        var searchable = (card.dataset.search || '').toLocaleLowerCase();
        var cat = (card.dataset.category || '').toLocaleLowerCase();
        var matches = (!query || searchable.includes(query)) && (!selectedCategory || selectedCategory === cat);
        card.hidden = !matches;
        if (matches) visible.push(card);
      });
      if (empty) empty.hidden = visible.length > 0;
      if (results) results.textContent = visible.length + (visible.length === 1 ? ' design' : ' designs') + ' available. Select a card to preview it on the product.';
      if (clear) clear.hidden = !search || !search.value;
      if (!selected || selected.hidden) choose(visible[0] || null);
    }
    cards.forEach(function (card) {
      var button = card.querySelector('.agas-card-pick');
      if (button) button.addEventListener('click', function () {choose(card);});
    });
    if (product) product.addEventListener('change', function () {
      if (product.value) window.location.assign(product.value);
    });
    if (search) {
      search.addEventListener('input', filter);
      search.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && search.value) {search.value = '';filter();}
      });
    }
    if (clear) clear.addEventListener('click', function () {search.value = '';filter();search.focus();});
    if (category) category.addEventListener('change', filter);
    choose(selected || cards[0] || null);
    filter();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

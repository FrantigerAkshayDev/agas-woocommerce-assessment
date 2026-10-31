/* AGAS v0.1.17: lightweight, accessible in-editor template browser.
 * Only metadata is shipped at page load. Fabric JSON is fetched on selection. */
(function () {
  'use strict';
  function mount(options) {
    var root = options.root;
    var grid = root.querySelector('#agas-template-sidebar-grid');
    var search = root.querySelector('#agas-template-sidebar-search');
    var category = root.querySelector('#agas-template-sidebar-category');
    var count = root.querySelector('#agas-template-sidebar-count');
    var empty = root.querySelector('#agas-template-sidebar-empty');
    var browse = root.querySelector('#agas-change-template');
    if (!grid || !search || !category || !count || !browse) return null;
    var templates = Array.isArray(options.templates) ? options.templates.slice() : [];
    var current = Number(options.getCurrentId ? options.getCurrentId() : 0) || 0;
    var busy = false;
    var names = Array.from(new Set(templates.map(function (t) {return String(t.category || 'Other');})))
      .sort(function (a, b) {return a.localeCompare(b);});
    names.forEach(function (name) {
      var option = document.createElement('option');
      option.value = name.toLowerCase();
      option.textContent = name;
      category.appendChild(option);
    });
    function card(template) {
      var id = Number(template ? template.id : 0) || 0;
      var label = template ? String(template.name || 'Untitled design') : 'Start from blank';
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'agas-sidebar-template-card';
      button.dataset.templateId = String(id);
      button.setAttribute('aria-label', template ? 'Use ' + label + ' template' : 'Start with a blank canvas');
      button.setAttribute('aria-pressed', String(id === current));
      if (id === current) button.classList.add('is-selected');
      var thumb = document.createElement('span');
      thumb.className = 'agas-sidebar-template-thumb';
      if (template && template.thumbnail) {
        var img = document.createElement('img');
        img.src = String(template.thumbnail);
        img.alt = '';
        img.loading = 'lazy';
        thumb.appendChild(img);
      } else {
        thumb.classList.add('agas-sidebar-template-blank');
        thumb.textContent = template ? 'Aa' : '+';
      }
      var name = document.createElement('strong');
      name.textContent = label;
      var type = document.createElement('small');
      type.textContent = template ? String(template.category || 'Other') : 'Empty canvas';
      button.appendChild(thumb);
      button.appendChild(name);
      button.appendChild(type);
      button.addEventListener('click', async function () {
        if (busy) return;
        busy = true;
        Array.from(grid.querySelectorAll('button')).forEach(function (el) {el.disabled = true;});
        count.textContent = 'Loading ' + label + '...';
        try {
          var accepted = await options.onChoose(template || null);
          if (accepted !== false) current = id;
        } catch (error) {
          count.textContent = error && error.message ? error.message : 'Could not load template.';
          if (typeof options.onError === 'function') options.onError(error);
        } finally {
          busy = false;
          render();
        }
      });
      return button;
    }
    function render() {
      if (busy) return;
      var query = search.value.trim().toLowerCase();
      var filter = category.value;
      var visible = templates.filter(function (t) {
        var text = (String(t.name || '') + ' ' + String(t.category || '')).toLowerCase();
        return (!query || text.includes(query)) && (!filter || String(t.category || 'Other').toLowerCase() === filter);
      });
      grid.replaceChildren();
      if (!query && !filter) grid.appendChild(card(null));
      visible.forEach(function (t) {grid.appendChild(card(t));});
      empty.hidden = visible.length > 0 || (!query && !filter);
      count.textContent = visible.length + ' template' + (visible.length === 1 ? '' : 's') + ' available';
    }
    search.addEventListener('input', render);
    category.addEventListener('change', render);
    browse.addEventListener('click', function () {search.focus();});
    render();
    return {render: render, getCurrentId: function () {return current;}};
  }
  window.AGASTemplateSidebar = {mount: mount};
})();

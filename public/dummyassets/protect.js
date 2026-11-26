// Copy deterrent (not real security): blocks right-click, view-source / DevTools / save / print shortcuts,
// text copy and image drag. Form fields stay usable.
(function () {
  var isField = function (el) { return el && el.closest && el.closest('input, textarea, select, [contenteditable="true"]'); };

  document.addEventListener('contextmenu', function (e) { if (!isField(e.target)) e.preventDefault(); });
  document.addEventListener('dragstart', function (e) { if (e.target.tagName === 'IMG') e.preventDefault(); });
  ['copy', 'cut'].forEach(function (t) {
    document.addEventListener(t, function (e) { if (!isField(e.target)) e.preventDefault(); });
  });

  document.addEventListener('keydown', function (e) {
    var k = (e.key || '').toLowerCase(), mod = e.ctrlKey || e.metaKey;
    var blocked = k === 'f12'
      || (mod && e.shiftKey && ['i', 'j', 'c', 'k'].indexOf(k) > -1) // DevTools
      || (e.metaKey && e.altKey && ['i', 'j', 'c', 'u'].indexOf(k) > -1) // DevTools / source on Mac
      || (mod && ['u', 's', 'p'].indexOf(k) > -1); // view-source, save, print
    if (blocked) { e.preventDefault(); e.stopPropagation(); }
  }, true);
})();

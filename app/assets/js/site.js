$(function () {
  $.ajaxSetup({
    beforeSend: function (xhr, settings) {
      if (settings.type === 'POST') {
        xhr.setRequestHeader('X-CSRF-Token', $('meta[name="csrf-token"]').attr('content'));
      }
    }
  });

  // footer "ofxAddons" -> back to top. href="#top" already works on its
  // own via the browser's native anchor scroll (progressive enhancement
  // for no-JS), but that native behavior isn't reliable in every
  // context, so this makes it explicit and guaranteed either way.
  $(document).on('click', '.site-footer__brand', function (e) {
    e.preventDefault();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  // copy-to-clipboard buttons next to the clone URL fields on the addon
  // detail page - purely client-side, nothing sent anywhere
  $(document).on('click', '.copy-field__btn', function () {
    var $btn = $(this);
    var targetId = $btn.data('copy-target');
    var $input = $('#' + targetId);
    if (!$input.length || !navigator.clipboard) {
      return;
    }
    navigator.clipboard.writeText($input.val()).then(function () {
      var original = $btn.text();
      $btn.addClass('is-copied').text('Copied!');
      setTimeout(function () {
        $btn.removeClass('is-copied').text(original);
      }, 1500);
    });
  });

  var $filter = $('#addon-filter');
  if ($filter.length) {
    // Pages that opt into the database-search fallback wrap their listing in
    // #filterable-content and provide a #search-results container. Pages
    // without those (e.g. /unsorted) just keep the old client-side-only
    // filtering, since a global addon search wouldn't make sense there.
    var $filterable = $('#filterable-content');
    var $searchResults = $('#search-results');
    var $filterWrap = $filter.closest('.filter-wrap');
    var dbFallbackEnabled = $filterable.length && $searchResults.length;
    var searchXhr = null;
    var searchTimer = null;

    function showLocalListing() {
      clearTimeout(searchTimer);
      if (searchXhr) {
        searchXhr.abort();
        searchXhr = null;
      }
      $filterWrap.removeClass('is-searching');
      $searchResults.prop('hidden', true).empty();
      $filterable.show();
    }

    function runDbSearch(q) {
      if (searchXhr) {
        searchXhr.abort();
      }
      $filterWrap.addClass('is-searching');
      searchXhr = $.ajax({ url: '/search?q=' + encodeURIComponent(q), method: 'GET' })
        .done(function (html) {
          // The query box may have changed (or been cleared) while this
          // request was in flight - only apply a result that still matches.
          if ($filter.val().toLowerCase().trim() !== q) {
            return;
          }
          $filterable.hide();
          $searchResults.html(html).prop('hidden', false);
        })
        .always(function () {
          $filterWrap.removeClass('is-searching');
        });
    }

    $filter.on('input', function () {
      var q = $(this).val().toLowerCase().trim();
      clearTimeout(searchTimer);

      var anyMatch = false;
      // :not(.addon-card--view-all) - that's a fake "View all" tile with
      // no data-name/data-desc, not a real card to match against
      $('.addon-card:not(.addon-card--view-all)').each(function () {
        var $card = $(this);
        var matches = !q || $card.data('name').indexOf(q) !== -1 || $card.data('desc').indexOf(q) !== -1;
        $card.toggleClass('is-hidden', !matches);
        if (matches) anyMatch = true;
      });
      $('.category-section').each(function () {
        var $section = $(this);
        var visible = $section.find('.addon-card:not(.addon-card--view-all)').not('.is-hidden').length;
        $section.toggle(visible > 0);
      });

      if (!dbFallbackEnabled) {
        return;
      }
      if (!q || anyMatch) {
        showLocalListing();
        return;
      }
      if (q.length < 2) {
        showLocalListing();
        return;
      }
      // wait for a 1s pause in typing before hitting the database - the
      // spinner (shown only once the request is actually in flight, via
      // is-searching) makes it clear this is a deliberate lookup, not a
      // per-keystroke refresh
      searchTimer = setTimeout(function () { runDbSearch(q); }, 1000);
    });
  }

  // /browse - loads every addon as one JSON payload (see ofx_browse_content())
  // and does all sorting/filtering/view-switching client-side against that
  // in-memory copy, rather than re-querying the server per interaction -
  // same approach daandelange's ofxAddonsJs browser uses.
  var $browseTiles = $('#browse-tiles');
  if ($browseTiles.length) {
    var browseAll = [];
    var browseView = 'tiles';
    try { browseView = localStorage.getItem('ofxBrowseView') || 'tiles'; } catch (e) {}
    var browseDebounce = null;

    function browseEsc(s) {
      return $('<div>').text(s || '').html();
    }

    // pushed_at/created_at are naive UTC strings ("Y-m-d H:i:s") - append
    // a literal UTC marker before parsing so the browser doesn't fall back
    // to interpreting them in the visitor's own local timezone
    function browseDate(s) {
      return s ? new Date(s.replace(' ', 'T') + 'Z') : null;
    }

    function browseTimeAgo(s) {
      var d = browseDate(s);
      if (!d) return '';
      var diff = (Date.now() - d.getTime()) / 1000;
      if (diff < 60) return 'just now';
      var mins = Math.floor(diff / 60);
      if (mins < 60) return mins + 'm ago';
      var hours = Math.floor(mins / 60);
      if (hours < 24) return hours + 'h ago';
      var days = Math.floor(hours / 24);
      if (days < 30) return days + 'd ago';
      var months = Math.floor(days / 30);
      if (months < 12) return months + 'mo ago';
      return Math.floor(months / 12) + 'y ago';
    }

    function browseAddonUrl(fullName) {
      var parts = (fullName || '').split('/');
      return '/addons/' + encodeURIComponent(parts[0] || '') + '/' + encodeURIComponent(parts[1] || '');
    }

    function browseSetView(view) {
      browseView = view;
      try { localStorage.setItem('ofxBrowseView', view); } catch (e) {}
      $('.view-toggle__btn').removeClass('is-active');
      $('.view-toggle__btn[data-view="' + view + '"]').addClass('is-active');
      $browseTiles.prop('hidden', view !== 'tiles');
      $('#browse-table-wrap').prop('hidden', view !== 'table');
    }

    function browseFilteredRows() {
      var q = ($('#browse-search').val() || '').toLowerCase().trim();
      var cat = $('#browse-category').val();
      return browseAll.filter(function (a) {
        if (cat && (a.categories || []).indexOf(cat) === -1) return false;
        if (!q) return true;
        return (a.name || '').toLowerCase().indexOf(q) !== -1
          || (a.description || '').toLowerCase().indexOf(q) !== -1
          || (a.owner || '').toLowerCase().indexOf(q) !== -1
          || (a.full_name || '').toLowerCase().indexOf(q) !== -1;
      });
    }

    function browseSortRows(rows, sortKey) {
      var bits = sortKey.split('-');
      var field = bits[0];
      var mult = bits[1] === 'desc' ? -1 : 1;
      return rows.slice().sort(function (a, b) {
        if (field === 'name' || field === 'owner') {
          var av = (a[field] || '').toLowerCase();
          var bv = (b[field] || '').toLowerCase();
          return av < bv ? -mult : av > bv ? mult : 0;
        }
        if (field === 'pushed_at' || field === 'created_at') {
          var ad = browseDate(a[field]);
          var bd = browseDate(b[field]);
          return ((ad ? ad.getTime() : 0) - (bd ? bd.getTime() : 0)) * mult;
        }
        return ((a[field] || 0) - (b[field] || 0)) * mult;
      });
    }

    function browseVersionTag(a) {
      if (!a.of_version) return '';
      var cls = 'tag tag--version' + (a.of_version_curated ? '' : ' tag--version-guess');
      return '<span class="' + cls + '">OF ' + browseEsc(a.of_version) + '</span>';
    }

    function browseFlags(a) {
      var html = '';
      if (a.archived) html += '<span class="tag tag--archived" title="Owner has archived this repo on Github">Archived</span>';
      if (a.has_releases) html += '<span class="tag tag--releases" title="Has tagged Github releases">Releases</span>';
      return html;
    }

    function browseCategoryTags(a) {
      return (a.categories || []).map(function (c) {
        return '<span class="tag">' + browseEsc(c) + '</span>';
      }).join('');
    }

    // table cells don't reliably respect max-width/width for wrapping
    // inline content in table-layout:auto (it's a well-known cross-browser
    // quirk) - a flex-wrap div with its own max-width does, so the table
    // view gets its own wrapper instead of reusing browseCategoryTags()'s
    // bare tags directly.
    function browseCategoryTagsWrapped(a) {
      return '<div class="browse-table__cats">' + browseCategoryTags(a) + '</div>';
    }

    function browseRenderTiles(rows) {
      if (!rows.length) {
        $browseTiles.html('<p class="empty-state">No addons match.</p>');
        return;
      }
      var html = rows.map(function (a) {
        var url = browseAddonUrl(a.full_name);
        var thumb = a.thumbnail
          ? '<img class="addon-card__thumb" src="' + browseEsc(a.thumbnail) + '" alt="" loading="lazy" onerror="this.remove()">'
          : '';
        return '<article class="addon-card">' + thumb
          + '<div class="addon-card__head">'
          + '<div class="addon-card__title">'
          + '<a class="addon-card__name" href="' + url + '">' + browseEsc(a.name) + '</a>'
          + (a.owner ? '<a class="addon-card__owner" href="/contributors/' + encodeURIComponent(a.owner) + '">@' + browseEsc(a.owner) + '</a>' : '')
          + '</div></div>'
          + '<a class="addon-card__desc" href="' + url + '">' + browseEsc(a.description || 'No description.') + '</a>'
          + '<div class="addon-card__tags">' + browseVersionTag(a) + browseCategoryTags(a) + browseFlags(a) + '</div>'
          + '<div class="addon-card__meta">'
          + '<span class="addon-card__stats"><span class="addon-card__stars">&#9733; ' + a.stars + '</span></span>'
          + '<span class="addon-card__dates"><span class="addon-card__updated">Updated ' + browseTimeAgo(a.pushed_at) + '</span></span>'
          + '</div></article>';
      }).join('');
      $browseTiles.html(html);
    }

    function browseRenderTable(rows) {
      var $body = $('#browse-table-body');
      if (!rows.length) {
        $body.html('<tr><td colspan="10" class="empty-state">No addons match.</td></tr>');
        return;
      }
      var html = rows.map(function (a) {
        var url = browseAddonUrl(a.full_name);
        return '<tr>'
          + '<td><a href="' + url + '">' + browseEsc(a.name) + '</a></td>'
          + '<td><div class="browse-table__desc">' + browseEsc(a.description || '') + '</div></td>'
          + '<td>' + (a.owner ? '<a href="/contributors/' + encodeURIComponent(a.owner) + '">@' + browseEsc(a.owner) + '</a>' : '') + '</td>'
          + '<td>' + browseCategoryTagsWrapped(a) + '</td>'
          + '<td>' + browseVersionTag(a) + '</td>'
          + '<td>' + a.stars + '</td>'
          + '<td>' + a.forks + '</td>'
          + '<td>' + browseTimeAgo(a.pushed_at) + '</td>'
          + '<td>' + browseTimeAgo(a.created_at) + '</td>'
          + '<td>' + browseFlags(a) + '</td>'
          + '</tr>';
      }).join('');
      $body.html(html);
    }

    // Rendering every match at once (2000+ addons, each a full tile with
    // an image or a table row) is enough synchronous DOM work that a
    // click could feel like it did nothing - the view/localStorage state
    // (see browseSetView) updates instantly, but the visible re-render
    // lags or, worse, a second impatient click queues up behind the first
    // and the two toggles cancel out. Capping what actually gets built
    // keeps every render fast regardless of how unfiltered the current
    // search/category is - same mitigation the reference ofxAddonsJs
    // browser uses (it paginates 60 at a time behind "Load more").
    var BROWSE_RENDER_LIMIT = 300;

    function browseRender() {
      var allRows = browseSortRows(browseFilteredRows(), $('#browse-sort').val());
      var rows = allRows.slice(0, BROWSE_RENDER_LIMIT);
      var status = allRows.length + ' addon' + (allRows.length === 1 ? '' : 's');
      if (allRows.length > rows.length) {
        status += ' - showing first ' + rows.length + ', search or pick a category to narrow it down';
      }
      $('#browse-status').text(status);
      try {
        if (browseView === 'tiles') {
          browseRenderTiles(rows);
        } else {
          browseRenderTable(rows);
        }
      } catch (e) {
        // a render that throws partway through a big html() build can
        // otherwise leave the previous view's stale content on screen
        // with no visible error - surface it in the status line instead
        // of failing silently
        $('#browse-status').text('Could not render that view - see console.');
        if (window.console && console.error) console.error(e);
      }
    }

    $('#browse-search').on('input', function () {
      clearTimeout(browseDebounce);
      browseDebounce = setTimeout(browseRender, 150);
    });
    $('#browse-sort, #browse-category').on('change', browseRender);
    // delegated (matches every other row-action binding in this file),
    // and yields one frame between toggling visibility and the actual
    // (potentially slow, see BROWSE_RENDER_LIMIT above) render, so the
    // active-button/visible-panel switch paints immediately instead of
    // being blocked behind building a few hundred rows of HTML first
    $(document).on('click', '.view-toggle__btn', function () {
      browseSetView($(this).data('view'));
      setTimeout(browseRender, 0);
    });

    $('#browse-status').text('Loading…');
    $.ajax({ url: '/browse.json', method: 'GET', dataType: 'json' }).done(function (data) {
      browseAll = data || [];
      var seen = {};
      var $cat = $('#browse-category');
      browseAll.forEach(function (a) {
        (a.categories || []).forEach(function (c) { seen[c] = true; });
      });
      Object.keys(seen).sort().forEach(function (c) {
        $cat.append($('<option>').val(c).text(c));
      });
      browseSetView(browseView);
      browseRender();
    }).fail(function () {
      $('#browse-status').text('Could not load addons - try refreshing.');
    });
  }

  function incrementPage(url) {
    var u = new URL(url, window.location.origin);
    var page = parseInt(u.searchParams.get('page') || '1', 10);
    u.searchParams.set('page', page + 1);
    return u.pathname + u.search;
  }

  $('.grid-sentinel').each(function () {
    var $sentinel = $(this);
    var $grid = $sentinel.prev('.addon-grid');
    var $loading = $sentinel.next('.grid-loading');
    var $end = $loading.next('.grid-end');
    if (!$grid.length) return;

    var loading = false;

    function loadMore() {
      if (loading || $grid.data('has-more') != 1) return;
      loading = true;
      $loading.prop('hidden', false);

      $.ajax({
        url: $grid.data('next-url'),
        method: 'GET'
      }).done(function (html, status, xhr) {
        $grid.append(html);
        var hasMore = xhr.getResponseHeader('X-Has-More') === '1';
        // .data(key, val) here, not .attr() - jQuery's .data() reads are
        // cached internally after the first access (line above), so an
        // .attr() write updates the DOM but not that cache, leaving
        // .data('next-url') stuck returning the same page forever
        $grid.data('has-more', hasMore ? '1' : '0');
        $grid.data('next-url', incrementPage($grid.data('next-url')));
        $loading.prop('hidden', true);
        if (!hasMore) {
          $end.prop('hidden', false);
          observer.disconnect();
        }
        $('#addon-filter').trigger('input');
      }).fail(function () {
        $loading.prop('hidden', true);
      }).always(function () {
        loading = false;
      });
    }

    var observer = new IntersectionObserver(function (entries) {
      if (entries[0].isIntersecting) loadMore();
    }, { rootMargin: '400px' });
    observer.observe(this);
  });

  function rowEndpoint($row) {
    return $row.closest('table').data('endpoint');
  }

  // Small spinner+text status box shown right under a Generate button
  // while its request is in flight, instead of the far-away actions
  // column - $scope is whatever ancestor holds the one console box that
  // belongs to this button (there can be more than one per row).
  function showConsole($scope, text) {
    var $console = $scope.find('.admin-row__console');
    $console.find('.admin-row__console-text').text(text);
    $console.prop('hidden', false);
    return $console;
  }
  function hideConsole($scope) {
    $scope.find('.admin-row__console').prop('hidden', true);
  }

  function saveRepoType($row, type, categoryIds, removeIfNot, description, descriptionGenerated, extra) {
    var repoId = $row.data('repo-id');
    var $status = $row.find('.admin-row__status');

    $row.removeClass('is-saved is-error');
    $status.text('Saving…');

    var data = $.extend({
      type: type,
      category_ids: categoryIds || []
    }, extra || {});
    if (description !== undefined) {
      data.description = description;
      data.description_generated = descriptionGenerated ? '1' : '';
    }

    $.ajax({
      url: rowEndpoint($row) + '/' + repoId,
      method: 'POST',
      data: data,
      dataType: 'json'
    }).done(function () {
      $row.addClass('is-saved');
      $status.text('Saved ✓');
      if (removeIfNot && removeIfNot.indexOf(type) === -1) {
        $row.fadeOut(300, function () { $row.remove(); });
      }
    }).fail(function (xhr) {
      $row.addClass('is-error');
      var msg = 'Save failed';
      try {
        var body = JSON.parse(xhr.responseText);
        if (body.error) msg = [].concat(body.error).join(', ');
      } catch (e) {}
      $status.text(msg);
    });
  }

  function updateCharCount($textarea) {
    var max = parseInt($textarea.attr('maxlength'), 10) || 0;
    var len = $textarea.val().length;
    var $count = $textarea.siblings('.admin-row__desc-meta').find('.admin-row__char-count');
    $count.text(len + ' / ' + max).toggleClass('is-near-limit', max > 0 && len >= max * 0.9);
  }

  $('.admin-row__desc').each(function () {
    updateCharCount($(this));
  });

  $(document).on('click', '.category-chip', function () {
    $(this).toggleClass('is-selected');
  });

  $(document).on('input', '.my-addon-row__thumbnail', function () {
    var url = $(this).val().trim();
    var $preview = $(this).siblings('.my-addon-row__thumbnail-preview');
    if (!url) {
      $preview.prop('hidden', true);
      return;
    }
    $preview.attr('src', url);
  });

  $(document).on('click', '.admin-row__save', function () {
    var $row = $(this).closest('.admin-row');
    var $typeSelect = $row.find('.admin-row__type');
    var type = $typeSelect.val() || 'Addon';
    var categoryIds = $row.find('.category-chip.is-selected').map(function () {
      return $(this).data('category-id');
    }).get();
    var description = $row.find('.admin-row__desc').val();
    var generated = $row.find('.admin-row__desc-generated').val() === '1';

    var extra = {};
    var $hidden = $row.find('.my-addon-row__hidden');
    if ($hidden.length) extra.hidden = $hidden.is(':checked') ? '1' : '0';
    var $thumb = $row.find('.my-addon-row__thumbnail');
    if ($thumb.length) extra.thumbnail_url_override = $thumb.val();

    var removeIfNot = $typeSelect.length ? ['Unsorted', 'Incomplete', 'Spam', 'Addon'] : null;

    saveRepoType($row, type, categoryIds, removeIfNot, description, generated, extra);
  });

  $(document).on('input', '.admin-row__desc', function () {
    $(this).siblings('.admin-row__desc-generated').val('0');
    updateCharCount($(this));
  });

  $(document).on('click', '.admin-row__ban', function () {
    var $row = $(this).closest('.admin-row');
    var name = $row.data('repo-name') || 'this repo';
    if (!window.confirm('Ban "' + name + '"? This marks it as not really an openFrameworks addon.')) {
      return;
    }
    saveRepoType($row, 'NonAddon', [], []);
  });

  $(document).on('click', '.admin-row__unban', function () {
    var $row = $(this).closest('.admin-row');
    saveRepoType($row, 'Unsorted', [], []);
  });

  // manual equivalent of the owner-edit AI triage fast-track - jumps this
  // repo to the front of the next /api/triage/batch. Toggles, so clicking
  // an already-flagged repo clears it again.
  $(document).on('click', '.admin-row__triage-flag', function () {
    var $btn = $(this);
    var repoId = $btn.data('repo-id');

    $btn.prop('disabled', true);

    $.ajax({
      url: '/admin/repos/' + repoId + '/triage-priority',
      method: 'POST',
      dataType: 'json'
    }).done(function (res) {
      $btn.toggleClass('is-flagged', res.flagged);
      $btn.html('&#128681; ' + (res.flagged ? 'Flagged for triage' : 'Flag for triage'));
      $btn.attr('title', res.flagged ? 'Flagged - click to unflag' : 'Jump this to the front of the next AI triage batch');
    }).fail(function () {
      window.alert('Could not update triage priority');
    }).always(function () {
      $btn.prop('disabled', false);
    });
  });

  $(document).on('click', '.admin-row__dismiss-appeal', function () {
    var $btn = $(this);
    var $row = $btn.closest('.admin-row');
    var repoId = $row.data('repo-id');
    var $status = $row.find('.admin-row__status');

    $btn.prop('disabled', true);
    $status.text('Saving…');

    $.ajax({
      url: '/admin/repos/' + repoId + '/dismiss-appeal',
      method: 'POST',
      dataType: 'json'
    }).done(function () {
      $row.fadeOut(300, function () { $row.remove(); });
    }).fail(function (xhr) {
      var msg = 'Failed';
      try {
        var body = JSON.parse(xhr.responseText);
        if (body.error) msg = [].concat(body.error).join(', ');
      } catch (e) {}
      $status.text(msg);
      $btn.prop('disabled', false);
    });
  });

  $(document).on('click', '.dupe-item__confirm', function () {
    var $btn = $(this);
    var $item = $btn.closest('.dupe-item');
    var $status = $item.find('.dupe-item__status');
    var repoId = $btn.data('repo-id');
    var parentId = $btn.data('parent-id');
    var hide = $btn.data('hide');

    $item.find('.dupe-item__confirm').prop('disabled', true);
    $status.removeClass('is-error').text('Comparing on Github…');

    $.ajax({
      url: '/admin/repos/' + repoId + '/confirm-fork',
      method: 'POST',
      data: { of: parentId, hide: hide },
      dataType: 'json'
    }).done(function () {
      window.location.reload();
    }).fail(function (xhr) {
      var msg = 'Failed';
      try {
        var body = JSON.parse(xhr.responseText);
        if (body.error) msg = [].concat(body.error).join(', ');
      } catch (e) {}
      $status.addClass('is-error').text(msg);
      $item.find('.dupe-item__confirm').prop('disabled', false);
    });
  });

  $(document).on('click', '.dupe-item__unconfirm', function () {
    var $btn = $(this);
    var $item = $btn.closest('.dupe-item');
    var $status = $item.find('.dupe-item__status');
    var repoId = $btn.data('repo-id');

    $btn.prop('disabled', true);
    $status.removeClass('is-error').text('Saving…');

    $.ajax({
      url: '/admin/repos/' + repoId + '/unconfirm-fork',
      method: 'POST',
      dataType: 'json'
    }).done(function () {
      window.location.reload();
    }).fail(function (xhr) {
      var msg = 'Failed';
      try {
        var body = JSON.parse(xhr.responseText);
        if (body.error) msg = [].concat(body.error).join(', ');
      } catch (e) {}
      $status.addClass('is-error').text(msg);
      $btn.prop('disabled', false);
    });
  });

  $(document).on('click', '.dupe-item__confirm-unique', function () {
    var $btn = $(this);
    var $item = $btn.closest('.dupe-item');
    var $status = $item.find('.dupe-item__status');
    var repoId = $btn.data('repo-id');

    $item.find('.dupe-item__confirm, .dupe-item__confirm-unique').prop('disabled', true);
    $status.removeClass('is-error').text('Saving…');

    $.ajax({
      url: '/admin/repos/' + repoId + '/confirm-unique',
      method: 'POST',
      dataType: 'json'
    }).done(function () {
      window.location.reload();
    }).fail(function (xhr) {
      var msg = 'Failed';
      try {
        var body = JSON.parse(xhr.responseText);
        if (body.error) msg = [].concat(body.error).join(', ');
      } catch (e) {}
      $status.addClass('is-error').text(msg);
      $item.find('.dupe-item__confirm, .dupe-item__confirm-unique').prop('disabled', false);
    });
  });

  $(document).on('click', '.dupe-item__unconfirm-unique', function () {
    var $btn = $(this);
    var $item = $btn.closest('.dupe-item');
    var $status = $item.find('.dupe-item__status');
    var repoId = $btn.data('repo-id');

    $btn.prop('disabled', true);
    $status.removeClass('is-error').text('Saving…');

    $.ajax({
      url: '/admin/repos/' + repoId + '/unconfirm-unique',
      method: 'POST',
      dataType: 'json'
    }).done(function () {
      window.location.reload();
    }).fail(function (xhr) {
      var msg = 'Failed';
      try {
        var body = JSON.parse(xhr.responseText);
        if (body.error) msg = [].concat(body.error).join(', ');
      } catch (e) {}
      $status.addClass('is-error').text(msg);
      $btn.prop('disabled', false);
    });
  });

  $(document).on('click', '.my-addon-row__appeal-ban', function () {
    var $btn = $(this);
    var repoId = $btn.data('repo-id');

    $btn.prop('disabled', true);

    $.ajax({
      url: '/my/addons/' + repoId + '/appeal-ban',
      method: 'POST',
      dataType: 'json'
    }).done(function () {
      $btn.replaceWith('<span class="tag tag--curated">Review requested</span>');
    }).fail(function (xhr) {
      var msg = 'Failed';
      try {
        var body = JSON.parse(xhr.responseText);
        if (body.error) msg = [].concat(body.error).join(', ');
      } catch (e) {}
      window.alert(msg);
      $btn.prop('disabled', false);
    });
  });

  $(document).on('click', '.admin-row__generate-desc', function () {
    var $btn = $(this);
    var $row = $btn.closest('.admin-row');
    var $cell = $btn.closest('.admin-row__desc-cell');
    var repoId = $row.data('repo-id');
    var $desc = $row.find('.admin-row__desc');
    // whatever's currently shown, saved or not - a second click builds
    // on this rather than the last saved value, and the server uses it
    // to steer the prompt away from repeating what's already there
    var existing = $desc.val().trim();
    var maxLength = parseInt($desc.attr('maxlength'), 10) || 350;

    $btn.prop('disabled', true);
    showConsole($cell, existing ? 'Generating more…' : 'Generating…');

    $.ajax({
      url: rowEndpoint($row) + '/' + repoId + '/generate-description',
      method: 'POST',
      data: { existing: existing },
      dataType: 'json'
    }).done(function (res) {
      var combined = existing ? existing + ' - ' + res.description : res.description;
      $desc.val(combined.slice(0, maxLength));
      $row.find('.admin-row__desc-generated').val('1');
      updateCharCount($desc);
      hideConsole($cell);
    }).fail(function (xhr) {
      var msg = 'Generate failed';
      try {
        var body = JSON.parse(xhr.responseText);
        if (body.error) msg = [].concat(body.error).join(', ');
      } catch (e) {}
      showConsole($cell, msg);
    }).always(function () {
      $btn.prop('disabled', false);
    });
  });

  // admin-only "Generate Img" button in the Repo column - replaces
  // itself with the resulting <img> on success, same element either
  // table it's in came from (there's only one caller, the admin table,
  // but this mirrors the other buttons' pattern for consistency).
  $(document).on('click', '.admin-row__generate-thumb', function () {
    var $btn = $(this);
    var $row = $btn.closest('.admin-row');
    var $cell = $btn.closest('td');
    var repoId = $btn.data('repo-id');

    $btn.prop('disabled', true);
    showConsole($cell, 'Generating - this can take up to a minute…');

    $.ajax({
      url: rowEndpoint($row) + '/' + repoId + '/generate-thumbnail',
      method: 'POST',
      dataType: 'json'
    }).done(function (res) {
      hideConsole($cell);
      // the button stays (relabeled) instead of being replaced by the
      // image, so an admin can regenerate again later - e.g. over a
      // generic ofxAddonTemplate example image the repo's own
      // ofxaddons_thumbnail.png just happened to be
      var $img = $cell.find('.admin-row__thumb');
      if ($img.length) {
        $img.attr('src', res.thumbnail_url).prop('hidden', false);
      } else {
        $btn.before($('<img class="admin-row__thumb" alt="" loading="lazy">').attr('src', res.thumbnail_url));
      }
      $btn.html('&#10024; Regenerate Img').prop('disabled', false);
    }).fail(function (xhr) {
      var msg = 'Generate failed';
      try {
        var body = JSON.parse(xhr.responseText);
        if (body.error) msg = [].concat(body.error).join(', ');
      } catch (e) {}
      showConsole($cell, msg);
      $btn.prop('disabled', false);
    });
  });

  $(document).on('click', '.feature-toggle', function () {
    var $btn = $(this);
    var repoId = $btn.data('repo-id');
    var categoryId = $btn.data('category-id');

    $btn.prop('disabled', true);

    $.ajax({
      url: '/admin/categorizations/' + repoId + '/' + categoryId + '/toggle-featured',
      method: 'POST',
      dataType: 'json'
    }).done(function (res) {
      $btn.data('featured', res.featured ? '1' : '0');
      $btn.text(res.featured ? '★ Featured' : '☆ Feature');
      $btn.closest('.addon-card-wrap').toggleClass('is-featured', !!res.featured);
    }).fail(function () {
      window.alert('Could not update featured status');
    }).always(function () {
      $btn.prop('disabled', false);
    });
  });

  // OF version chips on the admin sub-row - a single click saves
  // immediately (same instant-save pattern as the feature-star toggle
  // above), no need to hit the row's Save button. Clicking the
  // already-confirmed chip again clears it back to a guessed version
  // instead of doing nothing, so there's no separate "clear" control.
  // Uses .attr(), not .data(), for the version string - jQuery's .data()
  // auto-converts a numeric-looking value like "0.10" to the number 0.1,
  // which would both mis-highlight chips and send the wrong value.
  $(document).on('click', '.version-chip', function () {
    var $chip = $(this);
    var $group = $chip.closest('.version-picker');
    var repoId = $chip.data('repo-id');
    var version = $chip.hasClass('is-confirmed') ? '' : $chip.attr('data-version');

    $group.find('.version-chip').prop('disabled', true);

    $.ajax({
      url: '/admin/repos/' + repoId + '/version',
      method: 'POST',
      data: { version: version },
      dataType: 'json'
    }).done(function (res) {
      $group.find('.version-chip').each(function () {
        var $c = $(this);
        var v = $c.attr('data-version');
        $c.toggleClass('is-confirmed', !!res.curated && v === res.version);
        $c.toggleClass('is-guessed', !res.curated && v === res.guessed);
      });
    }).fail(function () {
      window.alert('Could not update version');
    }).always(function () {
      $group.find('.version-chip').prop('disabled', false);
    });
  });

  $('#admin-sync-now').on('click', function () {
    var $btn = $(this);
    var $status = $('#admin-sync-status');

    $btn.prop('disabled', true);
    $status.removeClass('is-error').text('Pulling latest release…');

    $.ajax({
      url: '/admin/sync-now',
      method: 'POST',
      dataType: 'json'
    }).done(function (res) {
      $status.text(res.added + ' added, ' + res.updated + ' updated, ' + res.skipped_banned + ' skipped');
    }).fail(function (xhr) {
      var msg = 'Sync failed';
      try {
        var body = JSON.parse(xhr.responseText);
        if (body.error) msg = [].concat(body.error).join(', ');
      } catch (e) {}
      $status.addClass('is-error').text(msg);
    }).always(function () {
      $btn.prop('disabled', false);
    });
  });

  $('#admin-regenerate-caches').on('click', function () {
    var $btn = $(this);
    var $status = $('#admin-regenerate-caches-status');

    $btn.prop('disabled', true);
    $status.removeClass('is-error').text('Regenerating…');

    $.ajax({
      url: '/admin/regenerate-caches',
      method: 'POST',
      dataType: 'json'
    }).done(function () {
      $status.text('Done ✓');
    }).fail(function (xhr) {
      var msg = 'Regenerate failed';
      try {
        var body = JSON.parse(xhr.responseText);
        if (body.error) msg = [].concat(body.error).join(', ');
      } catch (e) {}
      $status.addClass('is-error').text(msg);
    }).always(function () {
      $btn.prop('disabled', false);
    });
  });

  $('#admin-toggle-maintenance').on('click', function () {
    var $btn = $(this);
    var turningOn = $btn.data('on') !== 1 && $btn.data('on') !== '1';
    var confirmMsg = turningOn
      ? 'Take the ENTIRE public site down behind a static 503 page? Only /admin stays reachable.'
      : 'Bring the public site back online?';
    if (!window.confirm(confirmMsg)) return;

    var $status = $('#admin-toggle-maintenance-status');
    $btn.prop('disabled', true);
    $status.removeClass('is-error').text('Working…');

    $.ajax({
      url: '/admin/maintenance/toggle',
      method: 'POST',
      dataType: 'json'
    }).done(function (res) {
      $btn.data('on', res.maintenanceOn ? '1' : '0');
      $btn.toggleClass('is-danger', !!res.maintenanceOn);
      $btn.text(res.maintenanceOn ? 'Disable maintenance mode' : 'Enable maintenance mode');
      $status.text(res.maintenanceOn ? 'Site is now DOWN' : 'Site is back up');
    }).fail(function () {
      $status.addClass('is-error').text('Toggle failed');
    }).always(function () {
      $btn.prop('disabled', false);
    });
  });

  $('.admin-unflag-btn').on('click', function () {
    var $btn = $(this);
    var id = $btn.data('id');
    $btn.prop('disabled', true);

    $.ajax({
      url: '/admin/repos/' + id + '/unflag',
      method: 'POST',
      dataType: 'json'
    }).done(function () {
      $('#flagged-row-' + id).fadeOut(200, function () { $(this).remove(); });
    }).fail(function () {
      $btn.prop('disabled', false).text('Failed - retry');
    });
  });

  // "Deny" on the AI triage review screen - an explicit rejection with a
  // note for the model, distinct from just leaving the row unchecked
  // (which the bulk Confirm button below just discards silently). The
  // prompt() is the whole point: it's the note fed back to the model on
  // its next batch (see denied_feedback in ofx_api_triage_batch), so
  // skipping it (Cancel) skips the deny entirely rather than sending one
  // with no reason.
  $(document).on('click', '.import-diff-row__deny-btn', function () {
    var $btn = $(this);
    var $row = $btn.closest('.import-diff-row');
    var fullName = $btn.data('full-name');
    var reason = window.prompt(
      'Note for the AI on why "' + fullName + '" is being denied (shown back to it next batch):',
      ''
    );
    if (reason === null) {
      return; // Cancel - don't deny
    }

    $row.find('.import-diff-row__deny-btn, .import-diff-row__check').prop('disabled', true);

    $.ajax({
      url: '/admin/ai-triage/deny',
      method: 'POST',
      data: { full_name: fullName, reason: reason },
      dataType: 'json'
    }).done(function () {
      $row.find('.import-diff-row__check').prop('checked', false);
      $row.addClass('import-diff-row--denied').fadeTo(200, 0.5);
      $btn.text('Denied').off('click');
    }).fail(function (xhr) {
      var msg = 'Deny failed';
      try {
        var body = JSON.parse(xhr.responseText);
        if (body.error) msg = [].concat(body.error).join(', ');
      } catch (e) {}
      window.alert(msg);
      $row.find('.import-diff-row__deny-btn, .import-diff-row__check').prop('disabled', false);
    });
  });

  $('#admin-add-repo').on('click', function () {
    var $btn = $(this);
    var $input = $('#admin-add-repo-input');
    var $status = $('#admin-add-repo-status');
    var repo = $input.val().trim();
    if (!repo) {
      return;
    }

    $btn.prop('disabled', true);
    $status.removeClass('is-error').text('Fetching from Github…');

    $.ajax({
      url: '/admin/add-repo',
      method: 'POST',
      data: { repo: repo },
      dataType: 'json'
    }).done(function (res) {
      $status.text(res.full_name + ' added as ' + res.type + ' - reloading…');
      $input.val('');
      // NonAddon/Deleted repos already banned before this add live on the
      // Banned page, not any of the admin tabs (which only cover
      // Unsorted/Incomplete/Spam/Addon) - send those there instead
      var isBanned = res.type === 'NonAddon' || res.type === 'Deleted';
      window.location.href = isBanned
        ? '/admin/banned'
        : '/admin/repos?type=' + encodeURIComponent(res.type) + '&q=' + encodeURIComponent(res.full_name);
    }).fail(function (xhr) {
      var msg = 'Could not add that repo';
      try {
        var body = JSON.parse(xhr.responseText);
        if (body.error) msg = [].concat(body.error).join(', ');
      } catch (e) {}
      $status.addClass('is-error').text(msg);
    }).always(function () {
      $btn.prop('disabled', false);
    });
  });

  $(document).on('click', '.admin-user__toggle', function () {
    var $btn = $(this);
    var $row = $btn.closest('tr');
    var userId = $row.data('user-id');
    var makingAdmin = $btn.data('admin') != 1;
    var login = $row.find('a').text().trim();
    var verb = makingAdmin ? 'Grant' : 'Revoke';
    if (!window.confirm(verb + ' admin access ' + (makingAdmin ? 'to' : 'from') + ' ' + login + '?')) {
      return;
    }

    $btn.prop('disabled', true);
    $.ajax({
      url: '/admin/admins/' + userId + '/toggle',
      method: 'POST',
      dataType: 'json'
    }).done(function () {
      window.location.reload();
    }).fail(function (xhr) {
      var msg = 'Failed';
      try {
        var body = JSON.parse(xhr.responseText);
        if (body.error) msg = [].concat(body.error).join(', ');
      } catch (e) {}
      window.alert(msg);
      $btn.prop('disabled', false);
    });
  });

  $(document).on('click', '.admin-user__toggle-super', function () {
    var $btn = $(this);
    var $row = $btn.closest('tr');
    var userId = $row.data('user-id');
    var makingSuper = $btn.data('super-admin') != 1;
    var login = $row.find('a').text().trim();
    var verb = makingSuper ? 'Grant' : 'Revoke';
    if (!window.confirm(verb + ' super admin access ' + (makingSuper ? 'to' : 'from') + ' ' + login + '?')) {
      return;
    }

    $btn.prop('disabled', true);
    $.ajax({
      url: '/admin/admins/' + userId + '/toggle-super',
      method: 'POST',
      dataType: 'json'
    }).done(function () {
      window.location.reload();
    }).fail(function (xhr) {
      var msg = 'Failed';
      try {
        var body = JSON.parse(xhr.responseText);
        if (body.error) msg = [].concat(body.error).join(', ');
      } catch (e) {}
      window.alert(msg);
      $btn.prop('disabled', false);
    });
  });

  var $adminTbody = $('#admin-tbody');
  if ($adminTbody.length) {
    var $adminSentinel = $('#admin-sentinel');
    var $adminLoading = $adminSentinel.next('.grid-loading');
    var $adminEnd = $adminLoading.next('.grid-end');
    var adminLoading = false;

    function loadAdminRows(url, replace) {
      if (adminLoading) return;
      adminLoading = true;
      $adminLoading.prop('hidden', false);
      if (replace) $adminEnd.prop('hidden', true);

      $.ajax({ url: url, method: 'GET' }).done(function (html, status, xhr) {
        if (replace) $adminTbody.empty();
        $adminTbody.append(html);
        var hasMore = xhr.getResponseHeader('X-Has-More') === '1';
        // .data(), not .attr() - see the matching comment in loadMore() above
        $adminTbody.data('has-more', hasMore ? '1' : '0');
        $adminTbody.data('next-url', incrementPage(url));
        $adminLoading.prop('hidden', true);
        $adminEnd.prop('hidden', !!hasMore);
      }).fail(function () {
        $adminLoading.prop('hidden', true);
      }).always(function () {
        adminLoading = false;
      });
    }

    var adminObserver = new IntersectionObserver(function (entries) {
      if (entries[0].isIntersecting && $adminTbody.data('has-more') == 1) {
        loadAdminRows($adminTbody.data('next-url'), false);
      }
    }, { rootMargin: '400px' });
    adminObserver.observe($adminSentinel[0]);

    function withSearch(url) {
      var q = $('#admin-search').val();
      url = url.replace(/([?&])q=[^&]*&?/, '$1').replace(/[?&]$/, '');
      if (q) url += (url.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(q);
      return url;
    }

    $('.admin-tab').on('click', function (e) {
      e.preventDefault();
      var url = withSearch($(this).attr('href'));
      $(this).closest('.admin-tabs').find('.admin-tab').removeClass('active');
      $(this).addClass('active');
      if (window.history && history.pushState) history.pushState(null, '', url);
      var sep = url.indexOf('?') === -1 ? '?' : '&';
      loadAdminRows(url + sep + 'page=1', true);
    });

    var adminSearchTimer;
    $('#admin-search').on('input', function () {
      clearTimeout(adminSearchTimer);
      adminSearchTimer = setTimeout(function () {
        var url = withSearch($('.admin-tabs .admin-tab.active').attr('href') || '/admin/repos');
        if (window.history && history.pushState) history.pushState(null, '', url);
        var sep = url.indexOf('?') === -1 ? '?' : '&';
        loadAdminRows(url + sep + 'page=1', true);
      }, 350);
    });
  }
});

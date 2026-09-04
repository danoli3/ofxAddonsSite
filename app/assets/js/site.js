$(function () {
  $.ajaxSetup({
    beforeSend: function (xhr, settings) {
      if (settings.type === 'POST') {
        xhr.setRequestHeader('X-CSRF-Token', $('meta[name="csrf-token"]').attr('content'));
      }
    }
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
      $('.addon-card').each(function () {
        var $card = $(this);
        var matches = !q || $card.data('name').indexOf(q) !== -1 || $card.data('desc').indexOf(q) !== -1;
        $card.toggleClass('is-hidden', !matches);
        if (matches) anyMatch = true;
      });
      $('.category-section').each(function () {
        var $section = $(this);
        var visible = $section.find('.addon-card').not('.is-hidden').length;
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
        $grid.attr('data-has-more', hasMore ? '1' : '0');
        $grid.attr('data-next-url', incrementPage($grid.data('next-url')));
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

  $(document).on('click', '.my-addon-row__appeal-ban', function () {
    var $btn = $(this);
    var repoId = $btn.data('repo-id');

    $btn.prop('disabled', true);

    $.ajax({
      url: '/my/addons/' + repoId + '/appeal-ban',
      method: 'POST',
      dataType: 'json'
    }).done(function () {
      $btn.replaceWith('<span class="tag tag--curated">Ban appealed</span>');
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
    var repoId = $row.data('repo-id');
    var $desc = $row.find('.admin-row__desc');
    var $status = $row.find('.admin-row__status');
    // whatever's currently shown, saved or not - a second click builds
    // on this rather than the last saved value, and the server uses it
    // to steer the prompt away from repeating what's already there
    var existing = $desc.val().trim();
    var maxLength = parseInt($desc.attr('maxlength'), 10) || 350;

    $btn.prop('disabled', true);
    $status.text(existing ? 'Generating more…' : 'Generating…');

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
      $status.text('Suggested - review & Save');
    }).fail(function (xhr) {
      var msg = 'Generate failed';
      try {
        var body = JSON.parse(xhr.responseText);
        if (body.error) msg = [].concat(body.error).join(', ');
      } catch (e) {}
      $status.text(msg);
    }).always(function () {
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
        $adminTbody.attr('data-has-more', hasMore ? '1' : '0');
        $adminTbody.attr('data-next-url', incrementPage(url));
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

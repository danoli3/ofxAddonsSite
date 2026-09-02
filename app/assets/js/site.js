$(function () {
  $.ajaxSetup({
    beforeSend: function (xhr, settings) {
      if (settings.type === 'POST') {
        xhr.setRequestHeader('X-CSRF-Token', $('meta[name="csrf-token"]').attr('content'));
      }
    }
  });

  var $filter = $('#addon-filter');
  if ($filter.length) {
    $filter.on('input', function () {
      var q = $(this).val().toLowerCase().trim();
      $('.addon-card').each(function () {
        var $card = $(this);
        var matches = !q || $card.data('name').indexOf(q) !== -1 || $card.data('desc').indexOf(q) !== -1;
        $card.toggleClass('is-hidden', !matches);
      });
      $('.category-section').each(function () {
        var $section = $(this);
        var visible = $section.find('.addon-card').not('.is-hidden').length;
        $section.toggle(visible > 0);
      });
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

  $(document).on('click', '.admin-row__save', function () {
    var $row = $(this).closest('.admin-row');
    var $typeSelect = $row.find('.admin-row__type');
    var type = $typeSelect.val() || 'Addon';
    var categoryIds = $row.find('.admin-row__categories').val();
    var description = $row.find('.admin-row__desc').val();
    var generated = $row.find('.admin-row__desc-generated').val() === '1';

    var extra = {};
    var $hidden = $row.find('.my-addon-row__hidden');
    if ($hidden.length) extra.hidden = $hidden.is(':checked') ? '1' : '0';
    var $thumb = $row.find('.my-addon-row__thumbnail');
    if ($thumb.length) extra.thumbnail_url_override = $thumb.val();

    var removeIfNot = $typeSelect.length ? ['Unsorted', 'Incomplete'] : null;

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

  $(document).on('click', '.admin-row__generate-desc', function () {
    var $btn = $(this);
    var $row = $btn.closest('.admin-row');
    var repoId = $row.data('repo-id');
    var $desc = $row.find('.admin-row__desc');
    var $status = $row.find('.admin-row__status');

    $btn.prop('disabled', true);
    $status.text('Generating…');

    $.ajax({
      url: rowEndpoint($row) + '/' + repoId + '/generate-description',
      method: 'POST',
      dataType: 'json'
    }).done(function (res) {
      $desc.val(res.description);
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

    $('.admin-tab').on('click', function (e) {
      e.preventDefault();
      var url = $(this).attr('href');
      $('.admin-tab').removeClass('active');
      $(this).addClass('active');
      if (window.history && history.pushState) history.pushState(null, '', url);
      var sep = url.indexOf('?') === -1 ? '?' : '&';
      loadAdminRows(url + sep + 'page=1', true);
    });
  }
});

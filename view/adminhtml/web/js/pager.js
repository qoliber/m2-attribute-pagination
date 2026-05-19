define([
    'jquery',
    'uiRegistry'
], function ($, registry) {
    "use strict";

    function getPanel(config) {
        return $('#' + config.panelId);
    }

    function getCurrentLimit($panel, config) {
        return parseInt($panel.find('.attribute-pager-limit').first().val(), 10) || config.pagination.pageSize;
    }

    function getCurrentFilter($panel, config) {
        var $filter = config.filterSelector ? $panel.find(config.filterSelector).first() : $();

        if (!$filter.length) {
            return config.requestParams.option_filter || '';
        }

        return $.trim($filter.val());
    }

    function updatePagination($panel, pagination) {
        var previousPage = Math.max(1, pagination.currentPage - 1),
            nextPage = Math.min(pagination.maxPageCount, pagination.currentPage + 1),
            isFirstPage = pagination.currentPage <= 1,
            isLastPage = pagination.currentPage >= pagination.maxPageCount;

        $panel.find('.attribute-pager-limit').val(String(pagination.pageSize));
        $panel.find('.attribute-pager-current')
            .val(pagination.currentPage)
            .attr('max', pagination.maxPageCount);
        $panel.find('.attribute-pager-total-pages').text(pagination.maxPageCount);
        $panel.find('.action-previous')
            .data('page', previousPage)
            .toggleClass('disabled', isFirstPage)
            .prop('disabled', isFirstPage);
        $panel.find('.action-next')
            .data('page', nextPage)
            .toggleClass('disabled', isLastPage)
            .prop('disabled', isLastPage);
    }

    function resetComponent(component, $panel, config) {
        component.itemCount = 0;
        component.totalItems = 0;
        component.rendered = 0;
        component.elements = '';
        $panel.find(config.containerSelector).empty();

        if (typeof component.updateItemsCountField === 'function') {
            component.updateItemsCountField();
        }
    }

    function renderPage(config, attributesData) {
        var $panel = getPanel(config);

        registry.get(config.registryKey, function (component) {
            if (typeof component.ignoreValidate === 'function') {
                component.ignoreValidate();
            }

            resetComponent(component, $panel, config);
            component.renderWithDelay(attributesData || [], 0, 100, 300);
        });
    }

    function loadPage(config, page, limit) {
        var $panel = getPanel(config),
            currentFilter = getCurrentFilter($panel, config),
            requestData = $.extend({}, config.requestParams, {
                isAjax: 1,
                panel: config.panelType,
                page: page,
                limit: limit,
                option_filter: currentFilter
            }),
            state = $panel.data('attributePagerState') || {},
            requestId,
            renderTriggered = false;

        if (!$panel.length) {
            return;
        }

        if (state.request && state.request.readyState !== 4) {
            state.request.abort();
        }

        state.requestCounter = (state.requestCounter || 0) + 1;
        requestId = state.requestCounter;
        config.requestParams.option_filter = currentFilter;
        $('body').trigger('processStart');

        state.request = $.ajax({
            url: config.ajaxUrl,
            type: 'GET',
            dataType: 'json',
            data: requestData
        }).done(function (response) {
            if (!response || response.error) {
                return;
            }

            config.pagination = response.pagination || config.pagination;
            updatePagination($panel, config.pagination);
            renderTriggered = true;
            renderPage(config, response.attributesData);

            if (response.currentUrl && window.history && typeof window.history.replaceState === 'function') {
                window.history.replaceState({}, '', response.currentUrl);
            }
        }).always(function () {
            if (state.requestCounter === requestId && !renderTriggered) {
                $('body').trigger('processStop');
            }
        });

        $panel.data('attributePagerState', state);
    }

    return function (config) {
        var $panel = getPanel(config);

        if (!$panel.length || $panel.data('attributePagerInitialized')) {
            return;
        }

        $panel.data('attributePagerInitialized', true);
        updatePagination($panel, config.pagination);

        $panel.on('change.attributePager', '.attribute-pager-limit', function () {
            loadPage(config, parseInt($panel.find('.attribute-pager-current').first().val(), 10) || 1, getCurrentLimit($panel, config));
        });

        $panel.on('blur.attributePager', '.attribute-pager-current', function () {
            var page = parseInt($(this).val(), 10) || 1,
                maxPageCount = parseInt($(this).attr('max'), 10) || config.pagination.maxPageCount;

            page = Math.max(1, Math.min(page, maxPageCount));
            loadPage(config, page, getCurrentLimit($panel, config));
        });

        $panel.on('keydown.attributePager', '.attribute-pager-current', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                $(this).trigger('blur');
            }
        });

        $panel.on('click.attributePager', '.attribute-pager-btn', function () {
            if ($(this).hasClass('disabled') || $(this).prop('disabled')) {
                return;
            }

            loadPage(config, parseInt($(this).data('page'), 10) || 1, getCurrentLimit($panel, config));
        });

        $panel.on('click.attributePager', '.attribute-pager-filter-btn', function () {
            loadPage(config, 1, getCurrentLimit($panel, config));
        });

        $panel.on('click.attributePager', '.attribute-pager-filter-clear-btn', function () {
            var $filter = config.filterSelector ? $panel.find(config.filterSelector).first() : $();

            if ($filter.length) {
                $filter.val('');
            }

            config.requestParams.option_filter = '';
            loadPage(config, 1, getCurrentLimit($panel, config));
        });

        $panel.on('keydown.attributePager', '.attribute-pager-filter-input', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                loadPage(config, 1, getCurrentLimit($panel, config));
            }
        });
    };
});

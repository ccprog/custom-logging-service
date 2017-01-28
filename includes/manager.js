(function ($) {
  /*
   * Proxy ajax
   */
  Backbone.ajax = function (settings) {
    // parameters must not be submitted in body for DELETE
    if (settings.type === 'DELETE') {
      settings.url += '?' + $.param(settings.data);
      delete settings.data;
    }

    return Backbone.$.ajax.call(Backbone.$, settings);
  };

  /*
   * Proxy Backbone.sync
   */
  var sync = Backbone.sync;
  Backbone.sync = function (method, model, options) {
    var beforeSend, success, complete;

    options = options || {};

    beforeSend = options.beforeSend;
    options.beforeSend = function (xhr) {
      // phase in nonce
      xhr.setRequestHeader( 'X-WP-Nonce', clgs_base.nonce );

      // set interaction block
      model.trigger('block', model, true);

      if (beforeSend) {
        return beforeSend.apply(this, arguments);
      }
    };

    success = options.success;
    options.success = function (model, state, xhr) {
      // phase out nonce
      clgs_base.nonce = xhr.getResponseHeader('X-WP-Nonce');

      if ( success ) {
        return success.apply(this, arguments);
      }
    };

    // direct calls to sync do not trigger events
    if (!options.error) {
      options.error = function (xhr, textStatus, errorThrown) {
        model.trigger('error', model, xhr, textStatus, errorThrown);
      };
    }

    complete = options.complete;
    options.complete = function () {
      // release interaction block
      model.trigger('block', model, false);

      if (complete) {
        return complete.apply(this, arguments);
      }
    };

    return sync(method, model, options);
  };

   /*
   * Log statistics model
   */
  var LogCountModel = Backbone.Model.extend({
    default: {
      none: 0,
      debug: 0,
      notice: 0,
      warning: 0,
      error: 0,
      fatal: 0,
      total: 0,
    }
  });

  /*
   * Log count view for select element
   */
  var LogCountView = Backbone.View.extend({
    initialize: function() {
      this.listenTo(this.model, 'change', this.render);
    },

    render: function() {
      var counts = this.model.changedAttributes();
      delete counts.total;

      // add count to every option in select
      for (var severity in counts) {
        var $option = this.$('[value=' + severity + ']');
        $option.text(severity + ' (' + (counts[severity] || 0) + ')');
      }
    }
  });

  /*
   * Unseen count model
   */
  var BubbleModel = Backbone.Model.extend({
    default: {
      count: 0
    }
  });

  /*
   * Unseen count bubble in menu
   */
  var BubbleView = Backbone.View.extend({
    initialize: function () {
      this.listenTo(this.model, 'change', this.render);
    },

    template: _.template('<span class="awaiting-mod count-<%= count %>' + 
        '" aria-label="' + clgs_base.l10n.unseen + '"><span><%= count %></span></span>'),

    render: function () {
      var $mod = this.$('.awaiting-mod');

      // bubble disappears for count = 0
      if (!this.model.attributes.count) {
        $mod.remove();
      } else if ($mod.length) {
        $mod.replaceWith(this.template(this.model.attributes));
      } else {
        this.$el.append(this.template(this.model.attributes));
      }
    }
  });

  /*
   * Table widgets
   */
  var widget = {
    /*
     * Bulk action
     */
    bulk: {
      Model: Backbone.Model.extend({
        defaults: {
          action: -1
        }
      }),

      View: Backbone.View.extend({
        events: {
          'click .doaction': 'go',
          'change select': 'select'
        },

        initialize: function () {
          this.listenTo(this.model, 'change', this.render);

          this.$selector = this.$('select');

          this.render();
        },

        render: function () {
          this.$selector.val(this.model.attributes.action);
        },

        // action selector
        select: function (e) {
          e.preventDefault();
          this.model.set('action', $(e.currentTarget).val());
        },

        // Apply button
        go: function (e) {
          e.preventDefault();
          e.stopPropagation();
          this.model.trigger('action');
        }
      }),
    },

    /*
     * Filter options
     */
    filter: {
      Model: Backbone.Model.extend({
        defaults: {
          seen: false,
          min_severity: 'none'
        }
      }),

      View: Backbone.View.extend({
        events: {
          'click .dofilter': 'go',
          'click input.clgs-seen': 'check',
          'change select': 'select'
        },

        initialize: function () {
          this.listenTo(this.model, 'change', this.render);

          this.$seen = this.$('input.clgs-seen');

          // minimum severity selector gets spiced with log counts
          this.severity = new LogCountView({
            el: this.$('select')[0],
            model: this.model.log_count
          });

          this.render();
        },

        render: function () {
          this.$seen.prop('checked', this.model.attributes.seen);
          this.severity.$el.val(this.model.attributes.min_severity);
        },

        // show seen checkbox
        check: function (e) {
          this.model.set('seen', $(e.currentTarget).is(':checked'));
        },

        // minimum severity selector
        select: function (e) {
          e.preventDefault();
          this.model.set('min_severity', $(e.currentTarget).val());
        },

        // Filter button
        go: function (e) {
          e.preventDefault();
          e.stopPropagation();
          this.model.trigger('action', false);
        }
      })
    },

    /*
     * Page navigation
     */
    navigate: {
      Model: Backbone.Model.extend({
        defaults: {
          pages: 1,
          current: 1,
          items: 0
        }
      }),

      View: Backbone.View.extend({
        events: {
          'click a': 'leaf',
          'change input': 'jump'
        },

        // test for link deactivation
        test_position: function (action, navigate) {
          switch (action) {
          case 'first':
          case 'prev':
            return navigate.current <= 1;
          case 'next':
          case 'last':
            return navigate.current >= navigate.pages;
          }
        },

        // identify link target
        set_ref: function (action, navigate) {
          switch (action) {
          case 'first':
            return 1;
          case 'prev':
            return Math.max(1, navigate.current - 1);
          case 'next':
            return Math.min(navigate.pages, navigate.current + 1);
          case 'last':
            return navigate.pages;
          }
        },

        initialize: function () {
          this.listenTo(this.model, 'change', this.render);

          this.$items = this.$('.displaying-num');
          this.$input = this.$('.current-page');
          this.$total = this.$('.total-pages');

          // reference links
          var $pagination = this.$('.pagination-links');

          this.links = ['first', 'prev', 'next', 'last'].map(function (action) {
            var $a = $pagination.children('a.' + action + '-page');
            var $reader = $a.children('.screen-reader-text');
            var $link = $a.children('[aria-hidden]');

            // activate/deactivate link and set target page
            $link.set_relation = function () {
              if (this.test_position(action, this.model.attributes)) {
                $link.addClass('tablenav-pages-navspan')
                  .attr('aria-hidden', 'true');
                if($link.parent().is('a')) {
                  $link.unwrap();
                }
                $reader.detach();
              } else {
                $link.removeClass('tablenav-pages-navspan')
                  .attr('aria-hidden', 'false');
                if (!$link.parent().is('a')) {
                  $link.wrap($a)
                    .before($reader);
                }
                $link.parent().data('page', this.set_ref(action, this.model.attributes));
              }

              return $link;
            };

            return $link;
          });

          this.render();
        },

        render: function () {
          // hide and show pagination
          this.$el.removeClass('one-page no-page');
          if (this.model.attributes.pages < 1) {
            this.$el.addClass('no-page');
          } else if (this.model.attributes.pages < 2) {
            this.$el.addClass('one-page');
          }

          // display data
          var text = this.model.attributes.items === 1 ? 'item' : 'items';
          this.$items.text(this.model.attributes.items + ' ' + clgs_base.l10n[text]);
          this.$total.text(this.model.attributes.pages);
          this.$input.val(this.model.attributes.current);

          // adjust pagination links
          this.links.forEach(function ($link) {
            $link.set_relation.bind(this)();
          }, this);
        },

        // change page after click on pagination link
        leaf: function (e) {
          e.preventDefault();
          var page = $(e.currentTarget).data('page');
          this.model.set('current', page);
          this.model.trigger('tablenav', true);
        },

        // change page after manual page input
        jump: function (e) {
          e.preventDefault();
          e.stopPropagation();
          var page = parseInt($(e.currentTarget).val(), 10);
          // validation
          if (isNaN(page) || page < 1 || page > this.model.attributes.pages) {
            this.$input.val(this.model.attributes.current);
          } else {
            this.model.set('current', page);
            this.model.trigger('tablenav', true);
          }
        }
      })
    },

    /*
     * Table head/foot row
     */
    head: {
      Model: Backbone.Model.extend({
        defaults: {
          check: false,
          orderby: 'date',
          order: 'desc',
        }
      }),

      View: Backbone.View.extend({
        events: {
          'click a': 'sort',
          'click #cb-select-all': 'check'
        },

        initialize: function () {
          this.listenTo(this.model, 'change', this.render);

          this.$check = this.$('#cb-select-all');

          // reference sortable columns
          var sortable = this.sortable = {};
          this.$el.children().each(function () {
            var $field = $(this);
            var id = $field.attr('data-id');

            if ($field.hasClass('sortable')) {
              sortable[id] = $field;

              // the sort direction that will be set _after_ a click
              $field.children('a').data({
                orderby: id,
                order: $field.hasClass('asc') ? 'desc' : 'asc'
              });
            }
          });

          this.render();
        },

        // Select all checkbox
        check: function (e) {
          this.model.set('check', $(e.currentTarget).is(':checked'));
        },

        // helper
        swap_order: function (order) {
          return order === 'asc' ? 'desc' : 'asc';
        },

        render: function () {
          this.$check.prop('checked', this.model.attributes.check);

          var sorted, active;
          for (var id in this.sortable) {
            var $field = this.sortable[id];
            var $a = $field.children('a');

            sorted = (id === this.model.attributes.orderby);
            active = undefined;
            if (sorted) {
              active = this.model.attributes.order;
            } else if ($field.hasClass('sorted')) {
              active = $a.data('order');
            }

            if (active) {
              // field class = active sorting order
              $field.addClass(active)
              .removeClass(this.swap_order(active));
              // next target = opposite
              $a.data('order', this.swap_order(active));
            }

            $field.toggleClass('sortable', !sorted)
              .toggleClass('sorted', sorted);
          }
        },

        sort: function (e) {
          e.preventDefault();
          $(e.currentTarget).blur();
          this.model.set($(e.currentTarget).data());
          this.model.trigger('tablesort', false);
        }
      })
    }
  };

  /*
   * Table View Prototypes
   */
  var protoView = {
    Item: Backbone.View.extend({
      tagName: 'tr',

      // transform UNIX timestamps to localized date/time
      expand_dates: function () {
        this.$('[data-date]').each(function () {
          var $date = $(this);
          var date = new Date($date.data('date') * 1000); // JS wants milliseconds!
          $date.text(date.toLocaleString());
        });
      },

      // table field toggle button for small displays
      get_toggle_button: function () {
        return '<button type="button" class="toggle-row"><span class="screen-reader-text">' +
          clgs_base.l10n.more + '</span></button>';
      },
    }),

    List: Backbone.View.extend({
      render: function() {
        this.$el.empty();

        if (!this.collection.length) {
          this.add_no_row();
        }

        this.collection.each(function(model, index) {
          var item = new this.ItemView({
            model: model,
            id: index
          });
          this.$el.append(item.render().$el);
        }, this);

        return this;
      },

      no_row_template: _.template('<tr class="no-items">' +
          '<td class="colspanchange" colspan="<%= count %>"><%= text %>' +
          '</td></tr>'),

      // placeholder row for no data
      add_no_row: function (error) {
        this.$el.append(this.no_row_template({
          count: this.column_count,
          text: error || clgs_base.l10n.no_items
        }));
      },
    })
  };

  /*
   * Single category data model
   */
  var CategoryModel = Backbone.Model.extend({
    default: {
      name: null,
      description: null,
    },
    idAttribute: 'name'
  });

  /*
   * Category collection data model
   */
  var CategoryCollection = Backbone.Collection.extend({
    url: clgs_base.rest_base + '/categories',
    model: CategoryModel,
  });

  /*
   * Single log data model
   */
  var LogModel = Backbone.Model.extend({
    defaults: function () {
      var obj = {};
      for (var name in clgs_base.used_columns) {
        obj[name] = null;
      }
    }
  });

  /*
   * Log collection data model
   */
  var LogCollection = Backbone.Collection.extend({
    url: clgs_base.rest_base + '/logs',
    model: LogModel,

    // split count and collection to their models
    parse: function (data) {
      this.log_count.set(data.count);
      return data.collection;
    },
  });

  /*
   * Single category view - abstract for table row and single category mode
   */
  var CategoryItemView = protoView.Item.extend({
    events: {
      'click a': 'action'
    },

    // row action links
    action: function (e) {
      var data = $(e.currentTarget).data();
      // category mode toggle is handled by wrapper
      if (['single-category', 'show-all'].indexOf(data.action) >= 0) {
        return;
      }
      
      e.preventDefault();

      var params = {
        action: data.action,
        name: this.model.attributes.name
      };
      var method = params.action === 'unregister' ? 'delete' : 'update';

      // execute category bulk action
      this.model.sync(method, this.model, {
        emulateJSON: true,
        data: params,
        success: this.action_success.bind(this)
      });
    },

    // after bulk action, signal need for fresh data
    action_success: function () {
      this.model.trigger('success');
    },

    render: function () {
      if (this.id % 2 === 0) {
        this.$el.addClass('alternate');
      }

      var attr = this.model.attributes;
      this.$category.text(attr.name);
      this.$description.text(attr.description);
      
      // screan reader texts contain explicit category names
      this.$('a[aria-label]').each(function () {
        var label = _.template($(this).attr('aria-label'));
        $(this).attr('aria-label', label(attr));
      });

      return this;
    }
  });

  /*
   * Single category view in single category mode
   */
  var CategoryWrapItemView = CategoryItemView.extend({
    initialize: function() {
      this.listenTo(this.model, 'change', this.render);
      this.$category = this.$('#clgs-category-header');
      this.$description = this.$('.category-description');
    },
  });

  /*
   * Single category view in all category mode (table row)
   */
  var CategoryListItemView = CategoryItemView.extend({
    initialize: function () {
      // duplicate template row
      this.setElement(this.$template.clone().get());

      // primary column has toggle button
      var $title = this.$('.column-primary').append(this.get_toggle_button());
      this.$category = $title.find('span a').data(this.model.attributes);
      this.$description = this.$('.column-description');
    },
  });

  /*
   * Category collection view
   */
  var CategoryListView = protoView.List.extend({
    el: '#clgs-category-list tbody',
    ItemView: CategoryListItemView,
    column_count: 2,
  
    initialize: function () {
      // template row is extracted from html page
      // this makes it easier to receive localized texts
      this.ItemView = this.ItemView.extend({
        $template: this.$('tr').detach().removeClass('hidden')
      });

      this.listenTo(this.collection, {
        sync: this.render,
        // after bulk action, refresh data
        success: this.collection.fetch.bind(this.collection)
      });
    },
  });

  /*
   * Single log row
   */
  var LogItemView = protoView.Item.extend({
    render: function() {
      this.$el.addClass('severity-' + this.model.attributes.severity);
      if (this.id % 2 === 0) {
        this.$el.addClass('alternate');
      }

      for(var column in clgs_base.used_columns) {
        this.$el.append(this.get_field(column, clgs_base.used_columns[column]));
      }
      this.expand_dates();

      return this;
    },

    // render single table cell
    get_field: function (column, title) {
      var $td = $('<td>')
        .addClass(column + ' column-' + column)
        .attr('data-colname', title);

      switch (column) {
      case 'id':
        // check column
        $td = $('<th scope="row" class="column-cb check-column"><input type="checkbox"></th>');
        $td.children().val(this.model.attributes.id);
        break;
      case 'message':
        // log message
        var unseen = this.model.attributes.seen ? '' : '<strong>(' + clgs_base.l10n.new + ')</strong> ';

        // primary column has toggle button
        $td.addClass('has-row-actions column-primary')
          .html(unseen + this.model.attributes[column])
          .append(this.get_toggle_button());
        break;
      case 'date':
        // log timestamp
        $td.html(this.model.attributes[column]);
        break;
      case 'user':
        // user avatar and diplay name
        var img = this.model.attributes.avatar || '';

        $td.html(img + this.model.attributes[column]).addClass('column-username');
        break;
      default:
        // severity, category or blog
        $td.html(this.model.attributes[column]);
        break;
      }

      return $td;
    },
  });

  /*
   * Log collection view
   */
  var LogListView = protoView.List.extend({
    el: '.clgs-logs-table tbody',
    column_count: Object.keys(clgs_base.used_columns).length,

    ItemView: LogItemView,

    widget_selectors: {
      bulk: '.bulkactions',
      filter: '.clgs-tablefilter',
      navigate: '.tablenav-pages',
      head: '.clgs-logs-table thead tr, .clgs-logs-table tfoot tr'
    },

    initialize: function () {
      this.listenTo(this.collection, 'sync', this.render);

      // widgets are outside table body 
      var $app = this.$el.parents('#clgs-manager');

      // log count is a sub model to log collection
      this.collection.log_count = new LogCountModel();

      // widget views are collected in a property
      this.widgets = {};
      for(var which in this.widget_selectors) {
        // widget models are properties of the collection view itself
        var model = this[which] = new widget[which].Model();
        if (which === 'filter') {
          // the same log count model is also a sub model to filter model
          model.log_count = this.collection.log_count;
        }

        /* jshint loopfunc: true */
        this.widgets[which] = $app.find(this.widget_selectors[which]).map(function (idx, el) {
          return new widget[which].View({ el: el, model: model });
        }).get();
      }

      // get action events for every widget
      // bulk triggers a bulk action request
      this.listenTo(this.bulk, 'action', this.on_action);
      // all others simply fetch new data
      this.listenTo(this.filter, 'action', this.request_data);
      this.listenTo(this.navigate, 'tablenav', this.request_data);
      this.listenTo(this.head, 'tablesort', this.request_data);
    },

    // bulk action
    on_action: function () {
      // collect selected entries
      var $selected = this.$('.check-column :checked');
      var list = $selected.map(function() {
        return $(this).val();
      }).get();

      if (this.bulk.attributes.action !== -1 && list.length) {
        var params = {
          action: this.bulk.attributes.action,
          entries: list.join(',')
        };
        var method = params.action === 'mark-seen' ? 'update' : 'delete';

        // execute logs bulk action
        this.collection.sync(method, this.collection, {
          emulateJSON: true,
          data: params,
          success: this.action_success.bind(this, $selected)
        });
      }
    },

    // after bulk action, reset controls and fetch new data
    action_success:  function ($selected) {
      this.bulk.set('action', -1);
      $selected.prop('checked', false); // not really necessary
      this.request_data(true);
    },

    // after new data fetch, update navigation count data and Unseen bubble
    on_fetch: function (collection, response, options) {
      this.navigate.set({
        items: options.xhr.getResponseHeader('X-WP-Total'),
        pages: options.xhr.getResponseHeader('X-WP-TotalPages')
      });

      this.collection.trigger('unseen', options.xhr.getResponseHeader('X-CLGS-Unseen'));
    },

    // fetch new log data
    request_data: function (remain) {
      // reset to first page, with the exception of bulk action and navigation
      if (!remain) {
        this.navigate.set('current', 1);
      }
      this.head.set('check', false);

      this.collection.fetch({
        // collect attributes from all widgets
        data: _.extend({
          page: this.navigate.attributes.current,
          orderby: this.head.attributes.orderby,
          order: this.head.attributes.order
        }, this.filter.attributes),
        success: this.on_fetch.bind(this)
      });
    },

    // set category filter
    filter_category: function (category) {
      this.filter.set('category', category);
      this.request_data();
    }
  });

  /*
   * Application wrapper
   */
  function Wrapper ($wrap) {
    // parts only visible in all categories mode
    var $all = $wrap.find('.clgs-all-categories');
    // parts only visible in single category mode
    var $single = $wrap.find('#clgs-category-details');

    // category table
    this.category_list = new CategoryListView({
      collection: new CategoryCollection()
    });

    // logs table
    this.log_list = new LogListView({
      collection: new LogCollection(),
    });
    // if new category data are requested, trigger a request for new logs data
    this.log_list.listenTo(this.category_list.collection, 'request', function(context) {
      if (context instanceof Backbone.Collection) {
        this.request_data(false);
      }
    });

    // single category paragraph
    this.single = new CategoryWrapItemView({
      el: $single.get(),
      model: new (CategoryModel.extend({
        urlRoot: CategoryCollection.prototype.url
      }))()
    });
    // after bulk category action new data are needed
    this.category_list.collection.listenTo(this.single.model, 'success', this.category_list.collection.fetch);

    // Unseen bubble
    this.bubble = new BubbleView({
      el: $('#menu-dashboard li.current a'),
      model: new BubbleModel()
    });
    // update data
    this.bubble.model.listenTo(this.log_list.collection, 'unseen', this.bubble.model.set);

    // scroll to page top
    function scroll_top () {
      if (Element.prototype.scrollIntoView) {
        $('#wpbody-content')[0].scrollIntoView({ behavior: 'smooth' });
      } else {
        $(document).scrollTop(0);
      }
    }

    // user interaction block
    var $curtain = $wrap.children('#clgs-curtain'), requesting = [];

    // set or remove block
    function draw_curtain (context, block) {
      var pos = requesting.indexOf(context);
      // add or remove blocking instance from stack
      if (block && pos < 0) {
        requesting.push(context);
      } else if (!block && pos >= 0) {
        requesting.splice(pos, 1);
      }
      // if stack is empty, block can be released
      $curtain.toggleClass('closed', requesting.length > 0);
    }

    // dismissible notice
    var notice = _.template('<div class="notice notice-error is-dismissible">' +
        '<p><strong><%= title %></strong>: <%= msg %></p></div>');

    // show xhr error messages in notice
    function show_message (model, xhr, statusText, errorThrown) {
      $(notice({
        title: errorThrown || xhr.statusText || 'Error',
        msg: xhr.responseJSON ? xhr.responseJSON.message : xhr.responseText || 'Action failed.'
      })).insertBefore($curtain);
      $(document).trigger('wp-updates-notice-added'); // hijack!
      // bring message into view
      scroll_top();
    }

    [this.category_list.collection, this.log_list.collection, this.single.model]
      .forEach(function (model) {
        model.on({
          // block interaction during requests
          block: draw_curtain,
          // show error messages
          error: show_message
        });
      });

    // toggle between all categories and single category mode
    function toggle (e) {
      e.preventDefault();
      e.stopPropagation();
      var data = $(e.currentTarget).data();

      switch (data.action) {
      case 'show-all':
        $all.removeClass('hidden');
        $single.addClass('hidden');
        this.log_list.filter_category(undefined);
        break;
      case 'single-category':
        $all.addClass('hidden');
        $single.removeClass('hidden');
        this.single.model.set(data);
        this.log_list.filter_category(data.name);
        // bring category paragraphs into view
        scroll_top();
        break;
      }
    }
    $wrap.on('click', 'a', toggle.bind(this));

    // initial data fetch
    this.category_list.collection.fetch();
  }

  $(document).ready(function () {
    window.clgs = new Wrapper($('#clgs-manager'));
  });
})(jQuery);

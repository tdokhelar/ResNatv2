<template>
  <div class="icon-input-container" @click.stop>
    <span @click="open" :title="t('admin.options.fields.icon')">
      <template v-if="value">
        <img v-if="isValueImageUrl" class="icon value-icon" :src="value">
        <span v-else class="value-icon" :class="value" ></span>
      </template>
      <span v-else class="fas fa-ban demo-icon value-icon"></span>
    </span>
    <input class="fake-input" ref="input"/>
  </div>
</template>

<script>
import { isImageUrl } from '../../utils.js'

export default {
  props: ['value'],
  computed: {
    isValueImageUrl() {
      return isImageUrl(this.value)
    },
    $input() {
      return $(this.$root.$refs.iconInput || this.$refs.input)
    },
    $popup() {
      return this.$input.siblings('.iconpicker-popover')
    },
    pickerInstance() {
      return this.$input.data('iconpicker')
    },
  },
  mounted() {
    this.initPicker()
  },
  methods: {
    open(event) {
      this.$root.closeAllPopups()

      // Display the popup for real
      const offset = $(event.target).offset()
      this.$popup.addClass('gogo-show in').show().css({ top: offset.top + 22, left: offset.left })

      this.$popup.find('.iconpicker-item').removeClass('iconpicker-selected')
      this.pickerInstance.getSearchInput().val(this.value).focus()

      this.$popup.find(".clear-icon-button").off('click')
      this.$popup.find(".clear-icon-button").on('click', () => {
        this.$emit('input', '')
        this.$input.data('iconpicker').hide()
      })

      this.$input.off('iconpickerSelected')
      this.$input.on('iconpickerSelected', (event) => {
        let value = event.iconpickerValue
        // if nothing selected, use the value of the search box
        if (!value || !$(event.iconpickerItem).is(':visible')) {
          value = event.iconpickerInstance.getSearchInput().val()
        }
        this.$emit('input', value)
      })
    },
    initPicker() {
      var options = {
        title: false, // Popover title (optional) only if specified in the template
        selected: this.value, // use this value as the current item and ignore the original
        defaultValue: false, // use this value as the current item if input or element value is empty
        placement: 'RightTop', // (has some issues with auto and CSS). auto, top, bottom, left, right
        collision: true, // If true, the popover will be repositioned to another position when collapses with the window borders
        animation: true, // fade in/out on show/hide ?
        //hide iconpicker automatically when a value is picked. it is ignored if mustAccept is not false and the accept button is visible
        hideOnSelect: true,
        showFooter: true,
        searchInFooter: false, // If true, the search will be added to the footer instead of the title
        mustAccept: false, // only applicable when there's an iconpicker-btn-accept button in the popover footer
        selectedCustomClass: '', // Appends this class when to the selected item
        // icons: [], // list of icon objects [{title:String, searchTerms:String}]. By default, all Font Awesome icons are included.
        fullClassFormatter: function(val) {
          return val;
        },
        input: 'input,.iconpicker-input', // children input selector
        inputSearch: false, // use the input as a search box too?
        container: false, //  Appends the popover to a specific element. If not set, the selected element or element parent is used
        component: '.input-group-addon,.iconpicker-component', // children component jQuery selector or object, relative to the container element
        // Plugin templates:
        templates: {
          popover: '<div class="iconpicker-popover popover"><div class="arrow"></div>' +
            '<div class="popover-title"></div><div class="popover-content"></div></div>',
          footer: '<div class="popover-footer"></div>',
          buttons: `
            <button class="iconpicker-btn iconpicker-btn-cancel btn btn-default btn-sm">${t('admin.commons.actions.cancel')}</button>
            <button class="iconpicker-btn iconpicker-btn-accept btn btn-primary btn-sm">${t('admin.commons.actions.ok')}</button>`,
          search: `
            <div class="input-group" style="display: flex">
              <input type="search" class="form-control iconpicker-search"
                     placeholder="${t('admin.icons.search_placeholder')}" />
              <span class="input-group-btn" style="width: auto">
                <button class="btn btn-default clear-icon-button" type="button" style="height: 38px">
                  ${t('admin.commons.actions.clear')}
                </button>
              </span>
            </div>`,
          iconpicker: '<div class="iconpicker"><div class="iconpicker-items"></div></div>',
          iconpickerItem: '<a role="button" class="iconpicker-item"><i></i></a>',
        }
      };
      if (!this.$input.data('iconpicker')) {
        this.$input.iconpicker(options).on('iconpickerHide', (event) => {
          this.$popup.removeClass('gogo-show')
        })
        // we show it to it's fully looded, then we just need to make it visible and move it to
        // the right position in open() methods
        this.$input.data('iconpicker').show()
      }
    }
  }
}
</script>

<style lang='scss'>
  #taxonomy-tree {
    .iconpicker-popover.popover {
      width: 540px !important;
      &:not(.gogo-show) {
        display: none !important;
      }
    }
    .iconpicker .iconpicker-items {
      max-height: 150px !important;
      min-height: 0 !important;
    }
    .icon-input-container {
      position: relative;
      display: flex;
      width: 3rem;
      align-items: center;
      justify-content: center;
      margin-top: 2px;
    }
    .value-icon {
      cursor: pointer;
      display: flex;
      padding: 5px;
    }
    .fake-input {
      width: 0;
      overflow: hidden;
      position: absolute;
      right: 0;
      bottom: 0;
      padding: 0;
      border: none;
      visibility: hidden;
    }
    .demo-icon {
      opacity: .2;
    }
    .iconpicker-search {
      margin: 0 !important
    }
    img.icon {
      height: 2.5rem;
    }
  }
</style>
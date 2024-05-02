<template>
  <div class="item-picker" :class="nodeClass"
       :data-depth="depth" :style="customStyle">
    <component :is="`${type}-choice`" v-for="item in itemsToDisplay" :key="item.id"
               :item="item" ref="items"
               :expanded="item == expandedItem"
               @expand="expand"
               @collapse="collapse"
               :depth="type == 'category' ? depth : depth + 1"
               :sticky-height="stickyHeight"
               :siblings-count="itemsToDisplay.length" />
  </div>
</template>

<script>
import CategoryChoice from './CategoryChoice.vue'
import OptionChoice from './OptionChoice.vue'

export default {
  components: { CategoryChoice, OptionChoice },
  props: {
    type: { type: String, required: true },
    items: { type: Array, required: true },
    depth: { type: Number, default: 0 },
    customStyle: { type: Object },
    stickyHeight: { type: Number, default: 0 },
    display: {
      type: String,
      default: 'block',
      validator: function (value) {
        return ['block', 'floating'].includes(value)
      }
    }
  },
  data() {
    return {
      name: "ItemPicker",
      expandedItem: null,
      noChildren: false
    }
  },
  beforeMount() {
    this.initChildrenExpansion()
  },
  mounted() {
    // Collapse when clicking outside
    if (this.display == 'floating')
      window.addEventListener('click', (e) => {
        // console.log("click", e.target, this.$el)
        // if (!this.$el.contains(e.target)) {
        if ($(e.target).closest('.clickable').length == 0) {
          this.$emit('click-outside')
        }
      });

    this.noChildren = this.itemsToDisplay.every(item =>
      (item.subcategories || item.options).filter(i => i.displayInForm).length == 0)
  },
  computed: {
    nodeClass() {
      return [
        `${this.type}-picker display-${this.display} children-display-${this.$root.config.childrenDisplay}`,
        {'child-expanded': !!this.expandedItem, 'no-children': this.noChildren}
      ]
    },
    itemsToDisplay() {
      return this.items.filter(i => i.displayInForm)
    },
  },
  methods: {
    initChildrenExpansion() {
      if (this.type == 'category' && (this.depth > 0 || this.$root.config.rootExpansion && this.$root.config.rootDisplay == 'block'))
        this.expandedItem = this.items[0]
    },
    updateExpandedItem(value) {
      // Before doing anything we collapse all children, so it improve the collapse-transition
      // Otherwise, with floating mode, the height of the container is very big because of the
      // floating children and it result in bad animation
      this.recursivelyCollapse(this)
      // Don't know why, we need a timeOut here for the children to be really collapsed and so
      // the calculated height for the transition is correct
      setTimeout(() => { this.expandedItem = value }, 50)
    },
    expand(item) {
      this.updateExpandedItem(item)
      // When expanding any picker except for the absolute pickers themself, close them
      if (!$(this.$el).closest('.category-value').length) this.$root.pickerDisplayed = null
    },
    async collapse(item) {
      if (this.expandedItem && item && this.expandedItem.id == item.id) {
        this.updateExpandedItem(null)
      }
    },
    recursivelyCollapse(ref) {
      ref.$children.forEach(childRef => {
        this.recursivelyCollapse(childRef)
      })
      if (ref.name == "ItemPicker" && ref != this) ref.expandedItem = null
    }
  },
}
</script>

<style lang='scss'>
  .taxonomy-picker .item-picker {
    display: inline-flex;
    flex-direction: column;
    font-size: 1rem; // reset style
    font-weight: normal; // reset style

    &.child-expanded.children-display-floating {
      overflow: visible !important; // fix bug with collapse transition resulting in applying overflow hidden
    }

    .item-choice > .content {
      padding: 0 .8em 0 .6em;
    }

    &.display-floating {
      position: absolute;
      left: 100%;
      top: calc(-.5em - 1px);
      z-index: 50000;
      background-color: white;
      border: 1px solid #eee;
      border-radius: 5px;
      box-shadow: 3px 2px 13px 0 rgb(0 0 0 / 5%);

      // Use this kind of margin on children so the animation works better
      & > .item-choice:first-child {
        margin-top: .25em;
      }
      & > .item-choice:last-child {
        margin-bottom: .5em;
      }

      &[data-depth="0"] {
        left: 0;
        top: 30px;
        margin: 0;
        &.no-children {
          right: 0;
        }
        & > .item-choice:first-child {
          margin-top: .5em;
        }
      }
    }
  }

  .category-picker.display-block {
    display: flex;
  }

  .option-picker .category-picker.display-block {
    margin-left: 1em;

    border-left: 1px var(--color-light-soft) solid;
    padding-left: .8em;
    font-size: calc(1em - .25px);
  }
  .option-picker .child-display-block.expanded + .option-choice {
    margin-top: .7em; // add margin on children to improve animation
  }

  .option-picker {
    margin-top: .25em;
    &[data-depth="0"] {
      margin-top: .4em;
      &.display-block {
        margin-left: -.25em;
      }
    }
  }
</style>
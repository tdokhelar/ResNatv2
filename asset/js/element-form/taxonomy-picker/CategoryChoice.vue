<template>
  <div class="category-choice item-choice" :class="nodeClass" :data-depth="depth">
    <!-- Title -->
    <div v-show="displayName" @click="toggleExpand()" class="content clickable" :class="nodeClass"
         :data-depth="depth" ref="content"
         :style="stickyStyle">
      <slot>
        <span class="name">{{ categoryName }}</span>
        <ItemChoiceExpandIcon v-if="expandable" />
      </slot>
    </div>

    <!-- Chidlren -->
    <collapse-transition :disabled="childrenDisplay == 'floating' && depth > 0">
      <ItemPicker :items="category.options" type="option" :depth="depth" :display="childrenDisplay"
                  v-if="expandable && expanded || siblingsCount == 1 && depth != 0" ref="picker"
                  @click-outside="toggleExpand(false)"
                  :sticky-height="stickyNewHeight" />
    </collapse-transition>

    <!-- Values -->
    <CategoryValue v-if="showValues" :category="category" />
  </div>
</template>

<script>
import ItemChoice from './item-choice-mixin'
import ItemChoiceExpandIcon from './ItemChoiceExpandIcon.vue'

export default {
  mixins: [ItemChoice],
  components: { ItemChoiceExpandIcon },
  props: ['expanded', 'item', 'siblingsCount', 'depth', 'stickyHeight'],
  data() {
    return {
      stickyReady: false
    }
  },
  computed: {
    category() {
      return this.item
    },
    displayName() {
      return this.siblingsCount > 1 || this.depth == 0
    },
    stickyNewHeight() {
      return this.stickyHeight + (this.displayName ? 29 : 0)
    },
    stickyStyle() {
      return this.stickyReady && this.expanded && { top: `${this.stickyHeight}px`, 'z-index': 5000 - this.stickyHeight }
    },
    displayAsButton() {
      return this.depth == 0
    },
    categoryName() {
      if (!this.displayAsButton) return this.category.name
      if (this.category.pickingOptionText) {
        const transKey = this.checkedOptions.length > 0 ? 'category_add' : 'category_choose'
        return this.t(`element_form.${transKey}`, { cat: this.category.pickingOptionText })
      } else {
        return this.category.name
      }
    },
    expandable() {
      return this.siblingsCount > 1 || this.depth == 0
    },
    childrenDisplay() {
      return this.depth == 0 ? this.$root.config.rootDisplay : 'block'
    },
    checkedOptions() {
      return (this.category.options || []).filter(option => option.checked)
    },
    showValues() {
      return this.depth == 0
    },
    invalid() {
      return this.category.invalid
    }
  },
  mounted() {
    // Weird bug with sticky and collapse animation. We wait for the end of the animation
    // to add the css style to the element
    setTimeout(() => this.stickyReady = true, 550)
  }
}
</script>

<style lang='scss'>
  .category-choice {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    position: relative;
    &.with-name { margin-top: 0.25em; }

    &.as-btn + .category-choice.as-btn {
      margin-top: .5em;
    }
    &.child-display-block.expanded + .category-choice {
      margin-top: 1em;
    }

    & > .content {
      display: flex;
      align-items: center;
      width: 100%;
      height: 25px;
      white-space: nowrap;
      cursor: pointer;
      transition: top 600s; // small hack to avoid a glitch when top transition to 0 when animating collapse

      .name {
        font-weight: bold;
      }

      &.as-btn {
        border-radius: 3px;
        height: 30px;
        background-color: var(--color-dark-soft);
        color: var(--color-light);

        &.expanded {
          background-color: var(--color-primary);
        }

        .expand-container {
          margin-left: auto;
          .expand-icon {
            color: inherit !important;
          }
        }
      }

      &.mandatory.invalid .name:after {
        content: "*";
        font-weight: normal;
        padding: 0 4px;
        color: var(--color-error);
      }

      &.child-display-floating {
        margin-bottom: 0;
      }
      &:not(.as-btn).child-display-floating.expanded {
        color: var(--color-primary);
      }
    }
  }

  .category-picker.display-block > .category-choice > .content:not(.as-btn) {
    &.expanded {
      @extend .as-btn;
      background-color: var(--color-light-soft);
      color: var(--color-text);
    }
  }

  // When all display in block, activate sticky
  .taxonomy-picker .category-picker[data-depth="0"].display-block.children-display-block {
    .content.expanded {
      position: sticky;
    }
    .option-choice > .content.expanded {
      height: 30px; // make it bigger for the sticky position
      & + .item-picker {
        margin-top: -.25em;
      }
    }
  }
</style>
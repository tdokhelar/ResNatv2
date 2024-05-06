<template>
  <div class="option-value"
       :class="{root: option.root, draggable: draggable, 'with-description': withDescription}"
       :style="{color: applyColorToChildren ? option.color : null}" :data-color="option.color"
       :data-depth="depth" :option-id="option.id" :option-custom-id="option.customId">

    <div class="content" v-if="!option.root">
      <!-- Icon + Name -->
      <span class="drag-zone" :title="draggable ? t('element_form.category_drag') : ''">
        <template v-if="option.icon">
          <img v-if="optionIconIsImageUrl" class="option-icon" :src="option.icon">
          <span v-else class="option-icon" :class="option.icon"
                :style="{color: colorizeIcon ? option.color : null}" />
        </template>
        <span class="name">{{ option.name }}</span>
      </span>

      <!-- Description Input -->
      <template v-if="withDescription">
        <input type="text" :placeholder="option.descriptionLabel || option.parentCategory.descriptionLabel"
               v-model="option.checkedDescription" class="description"/>
      </template>

      <!-- Delete Button -->
      <span class="delete-icon" :title="t('element_form.category_remove')" @click="remove">✕</span>
    </div>

    <!-- Chidren -->
    <div class="option-value-children" v-if="option.displayChildrenInForm && categoriesToDisplay.length > 0">
      <CategoryValue :category="category" :siblings-count="categoriesToDisplay.length" :depth="depth + 1"
                     v-for="category in categoriesToDisplay" :key="`display-${category.id}`"></CategoryValue>
    </div>
  </div>
</template>

<script>
import { isImageUrl } from '../../utils.js'
import ItemValueMixin from './item-value-mixin.js'

export default {
  mixins: [ItemValueMixin],
  props: {
    option: { type: Object, required: true },
    draggable: { type: Boolean, default: true },
    depth: { type: Number, default: 0 },
  },
  data() {
    return {
      pickerPosition: {
        top: 0,
        left: 0,
        right: 'auto'
      }
    }
  },
  computed: {
    applyColorToChildren() {
      return this.depth == 0 && this.$root.config.colorizeValuesWithMainCategoryColor
    },
    colorizeIcon() {
      return !this.$root.config.colorizeValuesWithMainCategoryColor
    },
    showPicker() {
      return this.$root.pickerDisplayed == this
    },
    withDescription() {
      return this.option.enableDescription || this.option.parentCategory.enableDescription
    },
    categoriesToDisplay() {
      return this.option.subcategories.filter(cat => cat.displayInForm)
    },
    checkedCategories() {
      return this.checkedCategoriesFor(this.option)
    },
    optionIconIsImageUrl() {
      return isImageUrl(this.option.icon)
    },
  },
  methods: {
    remove() {
      const parentCat = this.option.parentCategory
      parentCat.invalid = parentCat.isMandatory && parentCat.options.filter(o => o.checked).length <= 1
      this.option.checked = false
    },
  }
}
</script>

<style lang='scss'>
  .children:not(.dragging) > div > .option-value.draggable > .content .drag-zone:hover,
  .option-value.sortable-ghost > .content .drag-zone {
    color: var(--color-primary);
    .option-icon {
      color: var(--color-primary) !important;
    }
  }
  .children:not(.dragging) > div > .option-value > .content .delete-icon:hover {
    font-weight: bold;
    opacity: 1;
    color: var(--color-error);
  }

  .option-value {
    &.draggable > .content .drag-zone:hover {
      cursor: grab;
    }

    & > .content {
      height: 2rem;
      display: flex;
      align-items: center;

      .drag-zone {
        display: flex;
        align-items: center;
        transition: all .1s;
        flex-shrink: 0;
      }

      .option-icon {
        width: 1.1rem;
        font-size: 1em;
        text-align: center;
        margin-right: 0.5rem;
      }

      .delete-icon {
        padding-left: .25em;
        width: 1.2em;
        cursor: pointer;
        margin-top: 2px;
        opacity: .4;
        transition: all .1s;
      }

      .button-add {
        margin-left: 0.3rem;
        margin-top: 1px;
        font-size: .95em;
      }

      input.description {
        margin: 0 .25em 0 .7em;
        height: auto;
        border-bottom: 1px solid var(--color-light-soft);
        padding: 0;
        margin-top: .15em;
        font-size: .85rem;
        color: var(--color-text-soft);
        &:focus {
          color: var(--color-text);
          box-shadow: none;
          border-color: var(--color-neutral);
          outline: none;
        }
      }
    }

    &:not(.root) .option-value-children {
      margin-left: .5rem;
      margin-bottom: .25em;
      border-left: 1px var(--color-light-soft) solid;
      padding-left: 1.2rem;
    }

    &.root > .option-value-children {
      margin-top: 2rem;
    }
  }

  .category-value[data-depth="0"]:not(.inline-children) {
    .option-value[data-depth="0"] {
      margin-bottom: 1.5rem;
    }
  }
</style>
<template>
  <div class="option-choice item-choice" :class="nodeClass">
    <div class="content clickable" :class="nodeClass" ref="content"
        :style="{top: `${stickyHeight}px`, 'z-index': 5000 - stickyHeight}">
      <!-- Checkbox -->
      <input type="checkbox" ref="input" v-model="option.checked" :data-key="option.id" />
      <label @click="onCheckboxClick">&nbsp;</label>

      <!-- Index -->
      <span class="index" @click="option.checked = false" v-if="$root.config.displayIndexInPicker">
        {{ option.checkedIndex || 0 }}
      </span>

      <!-- Title -->
      <span class="title" @click.stop="onTitleClick">
        <template v-if="$root.config.displayIconInPicker && option.icon">
          <img v-if="optionIconIsImageUrl" class="option-icon" :src="option.icon">
          <span v-else class="option-icon" :class="option.icon" />
        </template>
        <span class="name">{{ option.name }}</span>
        <span v-if="option.textHelper" class="textHelper" :title="option.textHelper">(?)</span>
        <ItemChoiceExpandIcon v-if="expandable" />

        <!-- Description Input -->
          <input type="text" v-if="withDescription && option.checked" @click.stop
               :placeholder="option.descriptionLabel || option.parentCategory.descriptionLabel"
                v-model="option.checkedDescription" class="description"/>
      </span>
    </div>

    <collapse-transition :disabled="childrenDisplay == 'floating' && depth > 0">
      <ItemPicker :items="option.subcategories" type="category" :depth="depth" :display="childrenDisplay"
                  v-if="expandable && expanded" ref="picker" :sticky-height="stickyNewHeight"
                  @click-outside="toggleExpand(false)" />
    </collapse-transition>
  </div>
</template>

<script>
import ItemChoice from './item-choice-mixin'
import ItemChoiceExpandIcon from './ItemChoiceExpandIcon.vue'
import { isImageUrl } from '../../utils.js'

export default {
  mixins: [ItemChoice],
  components: { ItemChoiceExpandIcon },
  props: ['item', 'expanded', 'depth', 'stickyHeight'],
  computed: {
    option() {
      return this.item
    },
    expandable() {
      return this.option.displayChildrenInForm && this.option.subcategories.length >= 1
    },
    stickyNewHeight() {
      return this.stickyHeight + 30
    },
    childrenDisplay() {
      return this.$root.config.childrenDisplay
    },
    optionIconIsImageUrl() {
      return isImageUrl(this.option.icon)
    },
    invalid() {
      return this.option.checked && !!this.option.allChildren.find(cat =>
        cat.type == "category" && cat.invalid && cat.parentOption && cat.parentOption.checked
      )
    },
    withDescription() {
      return this.option.enableDescription || this.option.parentCategory.enableDescription
    },
  },
  methods: {
    onTitleClick() {
      if (this.expandable) this.toggleExpand()
      else this.option.checked = !this.option.checked
    },
    onCheckboxClick() {
      this.option.checked = !this.option.checked
      if (this.option.checked) this.toggleExpand()
    },
  },
  watch: {
    'option.checked': {
      handler() {
        this.toggleExpand(this.option.checked)

        const parentCat = this.option.parentCategory

        if (this.option.checked) {
          // Also check parent
          if (this.option.parentOption) this.option.parentOption.checked = true
          // Handle category->singleOption
          if (parentCat.singleOption) {
            parentCat.options.forEach(opt => {
              if (opt.id != this.option.id) opt.checked = false
            })
          }
          // create the new index
          this.option.checkedIndex = parentCat.options.filter(o => o.checked).length
        } else {
          // uncheck all children
          this.option.allChildren.forEach(item => {
            if (item.type = 'option') item.checked = false
          })
          // reorder calculate existing indexes so there are no gap like 1,2,5,6
          this.option.checkedIndex = undefined
          let index = 1
          parentCat.options.filter(o => o.checked)
            .sort((a,b) => a.checkedIndex < b.checkedIndex ? -1 : 1)
            .forEach(option => {
              option.checkedIndex = index
              index = index + 1
            })
        }
        // check validity
        parentCat.invalid = parentCat.isMandatory && !parentCat.options.find(o => o.checked)
      }
    }
  }
}
</script>

<style lang='scss' scoped>
  .option-choice {
    display: flex;
    flex-direction: column;
    position: relative;
    transition: .2s margin ease-in-out;

    .content {
      display: flex;
      align-items: center;
      height: 25px;
      transition: .2s height ease-in-out, .2 margin ease-in-out;
      background-color: var(--color-content-background); // for sticky position

      input[type=checkbox] + label {
        font-size: 1em;
        line-height: 0;
        padding-left: 0;
        width: 1.8em;
        display: inline-flex;
        align-items: center;
      }
      input[type=checkbox] + label:before {
        margin: 0;
        position: relative;
      }
      input[type=checkbox]:not(:checked) + label:before
      {
        width: 1em;
        height: 1em;
        border-radius: .15em;
        margin-top: -2px;
      }
      input[type=checkbox]:checked + label:before
      {
        width: .6em;
        height: 1em;
        margin-top: 2px;
      }

      .index {
        opacity: 0;
        width: 0;
        overflow: hidden;
        transition: opacity .2s;
      }

      &.checked .index {
        opacity: 1;
        position: absolute;
        cursor: pointer;
        font-size: 0.65em;
        left: 1em;
        top: -1px;
        width: 1em;
        text-align: center;
        color: var(--color-neutral);
      }

      .title {
        cursor: pointer;
        display: flex;
        flex-grow: 1;
        align-items: center;
        white-space: nowrap;

        input.description {
          height: auto;
          border: 1px solid var(--color-light-soft);
          border-radius: 3px;
          padding: 0em 0.5em;
          margin: 0 0 0 .5em;
          background-color: #00000005;
          font-size: .9em;
          color: var(--color-text-soft);
          &:focus {
            color: var(--color-text);
            box-shadow: none;
            border-color: var(--color-light-soft);
            outline: none;
          }
        }
      }

      &.expanded .title {
        color: var(--color-primary);
      }

      .option-icon {
        width: 1.5em;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 0.5em;
      }
      
      .textHelper {
        cursor: help;
        font-weight: normal;
        margin-left: 5px;
        opacity: .6;
      }
    }
  }

  // turn checkboxes into radio buttons when only once option selectable
  .category-choice.single-option > .option-picker > .option-choice > .content input[type=checkbox]:not(:checked) + label:before {
    border-radius: 50%;
  }
</style>
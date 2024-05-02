<template>
  <div class="category-value" :class="{'inline-children': displayChildrenInline}"
       :data-depth="depth"
       @click="$emit('click')" :category-id="category.id" :category-custom-id="category.customId">
    <!-- Name -->
    <div class="content clickable" v-if="displayName" @click="togglePicker" :class="{invalid: invalid}">
      <div class="name" >{{ category.name }}</div>
      <span class="fa fa-caret-down"></span>
    </div>

    <!-- Children Options -->
    <div class="children" :class="{inline: displayChildrenInline, dragging: drag}" v-if="optionsToDisplay.length > 0">
      <draggable :list="optionsToDisplay" :group="category.id" @change="updateCheckedIndexes"
                 @start="drag=true" @end="drag=false">
        <OptionValue v-for="option in optionsToDisplay" :key="`value-${option.id}`" :option="option"
                     :draggable="optionsToDisplay.length > 1" :depth="depth" />
      </draggable>
    </div>

    <!-- Add Subcategories Button -->
    <div class="button-add clickable" @click="togglePicker"
         v-if="!displayName && depth > 0 || checkedOptions.length > 0"
         :title="t('element_form.category_add_more')" :class="{opened: showPicker, invalid: invalid}">
      <span class="error-message" v-if="invalid">{{ t('element_form.category_invalid') }}</span>
      <span :class="category.singleOption ? 'fa fa-pencil-alt' : 'fa fa-plus-circle'"></span>
      <span class="fa fa-exclamation invalid-icon" v-if="invalid"></span>
    </div>

    <!-- Options picker -->
    <ItemPicker v-if="showPicker" :items="category.options" type="option"
                display="floating" ref="picker"
                @click-outside="togglePicker($event, false)"
                :custom-style="pickerPosition"></ItemPicker>
  </div>
</template>

<script>
import ItemValueMixin from './item-value-mixin.js'
import CategoryChoice from './CategoryChoice.vue'

export default {
  mixins: [ItemValueMixin],
  components: { CategoryChoice },
  props: {
    category: { type: Object, required: true },
    siblingsCount: { type: Number, default: 1 },
    depth: { type: Number, default: 0 },
  },
  data() {
    return {
      drag: false,
      pickerPosition: {
        top: 0,
        left: 0,
        right: 'auto'
      }
    }
  },
  computed: {
    displayName() {
      return this.siblingsCount > 1
    },
    displayAddButton() {
      return this.optionsToDisplay.length > 0 && !this.category.singleOption && !this.category.options.every(o => o.checked)
    },
    showPicker() {
      return this.$root.pickerDisplayed == this
    },
    optionsToDisplay() {
      return this.checkedOptions
    },
    checkedOptions() {
      return this.checkedOptionsFor(this.category)
    },
    displayChildrenInline() {
      return !this.category.enableDescription && this.optionsToDisplay.every(option => option.subcategories.length == 0)
    },
    invalid() {
      return this.category.isMandatory && this.checkedOptions.length == 0
    },
  },
  methods: {
    updateCheckedIndexes(event) {
      const increment = event.moved.newIndex > event.moved.oldIndex ? 1.5 : 0.5
      event.moved.element.checkedIndex = event.moved.newIndex + increment
      // adjust all indexes so it continous : 1,2,3,4...
      this.checkedOptions.forEach((option, i) => {
        option.checkedIndex = i + 1
      })
    },
    expand() {
      this.$root.pickerDisplayed = this
    },
    collapse() {
      this.$root.pickerDisplayed = null
    },
    togglePicker(event, value) {
      if (value === false || this.$root.pickerDisplayed == this) this.$root.pickerDisplayed = null
      else {
        this.$root.pickerDisplayed = this
        const target = $(event.target).closest('.clickable')[0]
        const top = `${target.offsetTop + target.offsetHeight - 1}px`
        const left = target.offsetLeft - 1

        this.pickerPosition.top = top
        if (left < 400) {
          this.pickerPosition.left = `${left}px`
          this.pickerPosition.right = 'auto'
        } else {
          this.pickerPosition.left =  'auto'
          this.pickerPosition.right = `${$('.taxonomy-picker').width() - left - 15}px`
        }
      }
    },
  }
}
</script>

<style lang='scss'>
  .taxonomy-picker-container .taxonomy-picker .category-value {
    width: 100%;

    & > .content {
      height: 1.7rem;
      display: inline-flex;
      align-items: center;
      padding: 0 .5em;
      border-radius: 3px;
      margin: .2em .5em .5em -.3em;
      background-color: #f6f6f6;
      &.invalid {
        background-color: var(--color-error);
        color: var(--color-light);
      }

      .name {
        font-weight: 600;
        font-size: .95em;
      }

      .fa {
        opacity: .4;
        margin-left: .4em;
        font-size: .85em;
      }

      &:hover {
        &:not(.invalid) { background-color: var(--color-light-soft); }

        cursor: pointer;
        .fa { opacity: 1 }
      }
    }

    .children {
      transition: opacity .15s;
    }
    .children.inline {
      display: inline-flex;
      margin-right: .25em;

      .option-value {
        display: inline-flex;
        color: inherit !important;

        & > .content {
          .name {
            font-weight: normal;
          }
          .option-icon {
            display: none;
          }
        }
        &:not(:last-child):after {
          content: "•";
          font-size: .8em;
          margin: 0 0.5rem;
          align-items: center;
          display: inline-flex;
        }
      }
    }
    .button-add {
      display: inline-flex;
      align-items: baseline;
      cursor: pointer;

      .fa {
        font-size: .9em;
      }
      &:not(.opened) .fa {
        opacity: 0.4;
      }
      &:hover .fa {
        opacity: 1;
      }
      &.invalid {
        color: var(--color-error);
        .fa { opacity: 1 !important; }
      }
      .invalid-icon {
        margin-left: .25em;
      }
      .error-message {
        display: none;
        margin-right: .5rem;
      }
    }
    &[data-depth="0"] > .button-add {
      margin-left: .5em;
      margin-bottom: 1rem !important;
    }
    .children:not(.inline) + .button-add {
      padding-left: 2px;
      margin-bottom: .4em;
    }
  }
  .category-value[data-depth="1"] .category-value {
    font-size: .85rem;
  }
  .category-value + .category-value > .content {
    margin-top: .25em;
  }
  .category-value[data-depth="0"] > .children {
    margin: .5rem 0 .5rem .5rem;
  }
  .category-choice > .content.child-display-floating.expanded ~ .category-value:not(:hover) > .children {
    opacity: .4;
  }
  .category-choice > .content.child-display-block.expanded ~ .category-value > .children {
    padding-top: .5rem;
    border-top: 1px solid var(--color-light-soft);
  }
</style>
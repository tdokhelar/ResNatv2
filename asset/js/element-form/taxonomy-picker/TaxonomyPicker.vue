<template>
  <div class="taxonomy-picker" :class="{'display-errors': formSubmitted}">
    <ItemPicker v-if="ready" type="category" :items="mainCategories" :depth="0" ref="picker"></ItemPicker>

    <input v-for="(optionValues, catId) in optionValues" :key="catId"
            :value="JSON.stringify(optionValues)"
            :name="`options-values[${catId}]`" type="hidden"/>
  </div>
</template>

<script>
import Vue from '../../vendor/vue-custom'
import OptionValue from './OptionValue.vue'
import CategoryValue from './CategoryValue.vue'
import ItemPicker from './ItemPicker.vue'
import draggable from 'vuedraggable'
import CollapseTransition from "../../common/components/CollapseTransition.vue"

Vue.component('CollapseTransition', CollapseTransition)
Vue.component('ItemPicker', ItemPicker)
Vue.component('OptionValue', OptionValue)
Vue.component('CategoryValue', CategoryValue)
Vue.component('draggable', draggable)

export default {
  props: ['editing', 'taxonomy', 'initialOptionValues', 'config'],
  data() {
    return {
      mainCategories: [],
      ready: false,
      formSubmitted: false,
      enteredOptionValues: []
    }
  },
  computed: {
    optionValues() {
      return Object.fromEntries(this.mainCategories.map(cat => {
        return [cat.id, cat.allChildren
          .filter(item => item.checked && item.type == 'option')
          .map(option => {
            return {
              optionId: option.id,
              description: option.checkedDescription,
              index: option.checkedIndex
            }
          })
        ]
      }))
    }
  },
  mounted() {
    let restrictCategories = (this.config.categories || "").split(/[\s,]/g).filter(id => !!id)
    this.mainCategories = this.taxonomy.filter(cat =>
      restrictCategories.length == 0 || restrictCategories.includes(`${cat.id}`)
    )
    this.applyInitialValues()
    const isMobile = window.screen.width < 800
    this.$root.config = {
      rootDisplay: this.config.display == 'floating' && !isMobile ? 'floating' : 'block', // floating | block
      editing: this.editing,
      rootExpansion: !this.editing,
      childrenDisplay: this.config.display == 'floating' && !isMobile ? 'floating' : 'block', // floating | block
      displayIconInPicker: false,
      displayIndexInPicker: false,
      colorizeValuesWithMainCategoryColor: true
    }
    this.ready = true
  },
  methods: {
    applyInitialValues() {
      this.mainCategories.forEach(category => {
        this.recursivelyApplyInitialValues(category)
      })
    },
    resetValues(values) {
      this.enteredOptionValues = values
      this.applyInitialValues()
    },
    recursivelyApplyInitialValues(category) {
      category.type = 'category'
      category.allChildren = category.options || []

      category.options.forEach(option => {
        option.type = 'option'

        // parent & children
        option.parentCategory = category
        option.parentOption = category.parentOption
        category.allChildren = category.allChildren.concat(option.subcategories)

        // option value
        const optionValues = (this.enteredOptionValues.length > 0) ? this.enteredOptionValues : this.initialOptionValues
        const optionValue = optionValues.find(ov => ov.optionId == option.id)
        Vue.set(option, 'checked', !!optionValue)
        Vue.set(option, 'checkedIndex', (optionValue || {}).index || 0)
        Vue.set(option, 'checkedDescription', (optionValue || {}).description || "")
        // Check parent
        if (option.checked && option.parentOption) option.parentOption.checked = true

        option.subcategories.forEach(subcategory => {
          subcategory.parentOption = option
          this.recursivelyApplyInitialValues(subcategory)
          category.allChildren = category.allChildren.concat(subcategory.allChildren)
        })
        option.allChildren = option.subcategories.map(c => [c, c.allChildren]).flat().flat()
        Vue.set(option, 'invalid', option.checked && !!option.subcategories.find(item => item.invalid))
      })
      Vue.set(category, 'invalid', category.isMandatory && !category.options.find(o => o.checked))
    },
    checkValidity() {
      this.formSubmitted = true
    },
  },
}
</script>

<style lang='scss'>
  .taxonomy-picker.display-errors .item-choice.invalid {
    & > .content:not(.as-btn):not(.expanded) {
      .title {
        color: var(--color-error);
      }
      [type=checkbox]:checked + label:before {
        border-right-color: var(--color-error);
        border-bottom-color: var(--color-error);
      }
    }
    & > .content.as-btn {
      background-color: var(--color-error);
    }
  }
  .taxonomy-picker.display-errors .error-message {
    display: inline-flex;
  }

  .field-container + .field-container.field-taxonomy {
    margin-top: .5rem;
  }
  .field-container.field-taxonomy + .field-container:not(.field-taxonomy):not(.field-separator) {
    margin-top: 2rem;
  }

  .categories-info p {
    margin: 0;
  }

  // Fix conflicts with sonata CSS
  .sonata-ba-field .taxonomy-picker {
    .content {
      min-height: 0;
      padding: 0;
      margin: 0;
    }
    input[type=checkbox] {
      margin: -2px 8px 0 0;
      & + label {
        display: none !important;
      }
    }
    input.description {
      border: none;
    }
  }
</style>
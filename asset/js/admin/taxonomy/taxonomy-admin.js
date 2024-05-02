import Category from './Category.vue'
import Option from './Option.vue'
import OptionFormModal from './OptionFormModal.vue'
import CategoryFormModal from './CategoryFormModal.vue'
import Vue from '../../vendor/vue-custom'
import CrudMixin from './crud-mixin'
import draggable from 'vuedraggable'
import CollapseTransition from '../../common/components/CollapseTransition.vue'

document.addEventListener('DOMContentLoaded', function() {
  if ($('#taxonomy-tree').length > 0) {
    
    // register globally see https://vuejs.org/v2/guide/components-edge-cases.html#Recursive-Components
    Vue.component('Category', Category)
    Vue.component('Option', Option)
    Vue.component('draggable', draggable)
    Vue.component('CollapseTransition', CollapseTransition)

    window.tree = new Vue({
      el: "#taxonomy-tree",
      mixins: [CrudMixin],
      components: { optioncomponent: Option, OptionFormModal, CategoryFormModal },
      data: {
        saving: false,
        deletedOptionIds: [],
        deletedCategoryIds: [],
        mainOption: null,
        defaultMarkerShape: '',
      },      
      mounted() {
        this.defaultMarkerShape = this.$el.dataset.defaultMarkerShape
        $.getJSON('/api/taxonomy.json', (data) => {
          this.mainOption = { type: "option", subcategories: data }
        })
      },
      methods: {
        save() {
          const allItems = this.getAllItems()
          let itemsToSave = allItems.filter((item) => item.new || item.edited)
          itemsToSave = itemsToSave.map((item) => {
            let newItem = { ...item }
            delete newItem.subcategories
            delete newItem.options
            return newItem
          })
          this.saving = true
          $.post('/admin/taxonomy/save', {
              deletedOptionIds: this.deletedOptionIds,
              deletedCategoryIds: this.deletedCategoryIds,
              items: itemsToSave
          }, (result) => {
            if (result.newIds)
              for (let category of this.mainOption.subcategories) {
                this.recursiveUpdateIds(category, result.newIds)
              }
            this.deletedOptionIds = []
            this.deletedCategoryIds = []
            this.saving = false
            this.$nextTick(() => {
              this.recursiveSetPristine(this.mainOption)
            })
          })
        },
        editOption(option) {
          this.$refs.optionModal.edit('options', option)
        },
        editCategory(category) {
          this.$refs.categoryModal.edit('categories', category)
        },
        getAllItems() {
          let allItems = []
          for (let category of this.mainOption.subcategories) {
            this.recursiveCollectItem(category, allItems)
          }
          return allItems
        },
        recursiveCollectItem(category, allItems, parentId = null) {
          if (parentId == null) delete category.parentId
          else category.parentId = parentId
          allItems.push(category)
          for (let option of category.options) {
            option.parentId = category.id
            allItems.push(option)
            if (option.subcategories) {
              for (let subcategory of option.subcategories) {
                this.recursiveCollectItem(subcategory, allItems, option.id)
              }
            }
          }
          return allItems
        },
        // after save, the backend return the real id that have been created upon saving
        // we update our data accordingly to that
        recursiveUpdateIds(category, newIds) {
          if (newIds[category.id]) category.id = newIds[category.id]
          if (newIds[category.parentId]) category.parentId = newIds[category.parentId]
          for (let option of category.options) {
            if (newIds[option.id]) option.id = newIds[option.id]
            if (newIds[option.parentId]) option.parentId = newIds[option.parentId]
            if (option.subcategories) {
              for (let subcategory of option.subcategories) {
                this.recursiveUpdateIds(subcategory, newIds)
              }
            }
          }
        },
        recursiveSetPristine(option) {
          this.setPristine(option)
          for (let subcategory of option.subcategories) {
            this.setPristine(subcategory)
            for (let suboption of subcategory.options) {
              this.recursiveSetPristine(suboption)
            }
          }
        },
        setPristine(model) {
          delete model.new
          delete model.edited
        },
        closeAllPopups() {
          $('.iconpicker-popover.in').removeClass('gogo-show in').hide()
          $('#taxonomy-tree').trigger('click') // close other popups
        }
      }
    })
  }
})

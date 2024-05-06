<template>
  <div class="category-container">

    <div class="category-item" :data-depth="depth">
      <span class="node-content" @click="expanded = !expanded">
        <input class="form-control name" @click.stop v-model="category.name" @keyup.enter="$event.target.blur()"
               :style="{'max-width': `calc(${category.name.length}ch + 20px)`}" />
        <span class="gogo-icon-expand fa fa-caret-down"></span>
      </span>

      <span class="actions">
        <span v-if="category.isMandatory" class="label label-primary">{{ t('admin.categories.list.mandatory') }}</span>
        <span v-show="!category.new" :title="t('admin.options.form.id_custom_id')"
              class="custom-id label label-default">{{ category.customId || category.id }}</span>
        <VisibilityInput :model="category" model-name="categories" />
        <i class="drag-icon fas fa-grip-lines" :title="t('admin.options.form.drag_drop')"></i>
        <span class="btn btn-sm btn-link" @click="$root.editCategory(category)"><i class="fa fa-pen"></i></span>
        <span @click="deleteNode(category)" class="btn btn-sm btn-link"><i class="fa fa-trash"></i></span>
      </span>
    </div>

    <collapse-transition>
      <div v-show="expanded" class="category-children"
          :class="{ expanded: category.showExpanded }">
        <draggable v-model="category.options" filter=".no-drag" group="options" @change="updatePosition(category, category.options)">
          <Option v-for="option in category.options" :depth="depth" :key="option.id" :option="option"></Option>
        </draggable>
        <div class="option-item">
          <input type="text" class="new-option-name form-control" v-model="newOption.name"
                @keyup.enter="addOption" ref="newOptionName" :placeholder="t('admin.categories.form.add_option')"/>
          <i class="fa fa-plus-circle" v-show="newOption.name" @click="addOption"></i>
        </div>
      </div>
    </collapse-transition>
    
  </div>
</template>

<script>
import CrudMixin from './crud-mixin'
import VisibilityInput from './VisibilityInput.vue'

export default {
  props: ['category', 'depth'],
  mixins: [ CrudMixin ],
  components: { VisibilityInput },
  data() {
    return {
      expanded: true,
      newOption: {},
      previousState: {}
    }
  },
  computed: {
    expandable() {
      return this.category.options && this.category.options.length > 0
    }
  },
  mounted() {
    this.category.options ||= []
    if (this.category.new || !this.category.parentId) this.expanded = true
    this.previousState = { ...this.category }
  },
  methods: {
    addOption() {
      const newOption = {
        id: this.generateId(),
        name: this.newOption.name || t('admin.options._label'),
        new: true,
        type: "Option",
        index: this.category && this.category.options.length > 0 ? Math.max(...this.category.options.map(o => o.index)) + 1 : 0,
        subcategories: [],
        displayInForm: true,
        displayInMenu: true,
        displayInInfoBar: true
      }
      this.category.options.push(newOption)
      this.newOption = {}
      this.$refs.newOptionName.focus()
    }
  },
  watch: {
    category: {
      handler(oldVal, newVal) {
        newVal = { ...this.category }
        oldVal = { ...this.previousState }
        delete newVal.options
        delete oldVal.options
        if (JSON.stringify(newVal).trim() != JSON.stringify(oldVal).trim()) {
          // console.log("editing category group", oldVal, newVal)
          this.category.edited = true
        }
        this.previousState = { ...this.category }
      },
      deep: true
    }
  }
}
</script>

<style lang="scss">
  .drag-icon {
    font-size: 1.2rem;
    margin-right: 1rem;
    opacity: .8;
    cursor: grabbing;
  }
  .category-item, .option-item {
    border: 1px solid #ccc;
    margin-top: -1px;
    padding: 0 1.2rem 0 5px;
    height: 4rem;
    background-color: white;
    display: flex;
    align-items: center;

    .node-content {
      flex-grow: 1;
      display: flex;
      align-items: center;
    }
    .actions {
      display: flex;
      align-items: center;
      span i { margin: 0; }
      .label {
        padding: .3em .6em;
        font-weight: 600;
        margin: 0 8px 0 0;
      }
    }
    .custom-id {
      background-color: #ecf0f5;
      color: #2c3b42eb;
    }
  }
  .category-item {
    &[data-depth="0"] { background-color: #3d4d54; }
    &[data-depth="1"] { background-color: #58727d; }
    &[data-depth="2"] { background-color: #89a7b3; }
    background-color: #98a2a7;

    color: white;
    a, .btn-link {
      color: white
    }
    .custom-id {
      background-color: #6c848e;
      color: inherit;
    }
    height: 3.4rem;
    .name {
      font-weight: bold;
      width: auto;
      height: auto;
      flex-grow: 1;
      margin-right: 10px;
      padding-left: 7px;
      border: none !important;
      &:not(:focus) {
        background: transparent;
        color: inherit;
      }
    }
    .node-content {
      cursor: pointer;
      &:hover .gogo-icon-expand {
        visibility: visible;
      }
      & > .label {
        padding: .2em .6em;
        opacity: .9;
      }
    }
    .gogo-icon-expand {
      display: inline-flex;
      visibility: hidden;
      font-size: 1.1rem;
      margin-left: 5px;
      margin-top: 2px;
      margin-right: 10px;
    }
  }

  .category-container {
    margin-bottom: 1.2rem;
  }

  .new-option-name {
    width: auto;
    margin-right: .5rem;
  }
</style>
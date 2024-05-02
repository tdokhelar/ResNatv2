<template>
  <div class="options-container">
    <template v-if="option.id"> <!-- Root fake option does not have id -->
      <div class="option-item">
        <span class="node-content" @click="expanded = !expanded" :class="{expandable: expandable}">
          <span class="node-expand">
            <span v-if="expandable" :class="`fa fa-chevron-${expanded ? 'down' : 'right'}`"
                  :title="t('admin.options.form.expand')"></span>
            <span v-else @click="addCategory" class="add-subcategories fa fa-plus-circle"
                  :title="t('admin.options.form.add_subcategories')"></span>
          </span>
          <div class="shortcuts no-drag" @click.stop>
            <MarkerShapeInput :class="{default: !option.markerShape}"
                              v-model="option.markerShape" :default-value="$root.defaultMarkerShape"
                              :display-default-option="true">
              <h4>{{ t('admin.options.fields.markerSize') }}</h4>
              <MarkerSizeInput v-model="option.markerSize" />
              <hr/>
              <h4>{{ t('admin.options.fields.markerShape') }}</h4>
            </MarkerShapeInput>
            <ColorInput v-model="option.color"/>
            <IconInput v-model="option.icon"/>
          </div>

          <input class="form-control name" v-model="option.name" @keyup.enter="$event.target.blur()"
                 @click.stop :style="{'max-width': `calc(${option.name.length}ch + 24px)`}"/>

        </span>
        <span class="actions">
          <span v-show="!option.new" class="custom-id label label-default"
                :title="t('admin.options.form.id_custom_id')">{{ option.customId || option.id }}</span>
          <VisibilityInput :model="option" model-name="options" />
          <i class="drag-icon fas fa-grip-lines" :title="t('admin.options.form.drag_drop')"></i>
          <span class="btn btn-sm btn-link" @click="$root.editOption(option)"><i class="fa fa-pen"></i></span>
          <span @click="deleteNode(option)" class="btn btn-sm btn-link"><i class="fa fa-trash"></i></span>
        </span>
      </div>
    </template>

    <collapse-transition>
      <div v-show="(expandable && expanded) || root" class="option-children">
        <draggable v-model="option.subcategories" filter=".no-drag" group="categories" @change="updatePosition(option, option.subcategories)">
          <Category v-for="subcategory in option.subcategories" :key="subcategory.id" :category="subcategory"
                    ref="categories" :depth="depth + 1"></Category>
        </draggable>
        <div class="category-item new-category" :data-depth="depth + 1">
          <input type="text" class="new-category-name form-control" v-model="newCategory.name"
                @keyup.enter="addCategory" :placeholder="t('admin.options.form.add_group')"/>
          <i class="fa fa-plus-circle" v-show="newCategory.name" @click="addCategory"></i>
        </div>
      </div>
    </collapse-transition>
  </div>
</template>

<script>
import CrudMixin from './crud-mixin'
import ColorInput from '../components/ColorInput.vue'
import IconInput from '../components/IconInput.vue'
import MarkerShapeInput from '../components/MarkerShapeInput.vue'
import MarkerSizeInput from '../components/MarkerSizeInput.vue'
import VisibilityInput from './VisibilityInput.vue'

export default {
  props: ['option', 'root', 'depth'],
  mixins: [CrudMixin],
  components: { ColorInput, IconInput, VisibilityInput, MarkerShapeInput, MarkerSizeInput },
  data() {
    return {
      expanded: false,
      newCategory: {},
      previousState: {}
    }
  },
  mounted() {
    this.option.subcategories ||= []
    this.previousState = { ...this.option }
  },
  methods: {
    addCategory() {
      const defaultName = this.option && this.option.id ? t('admin.options.form.subcategories') : t('admin.options.form.new_categories_group')
      let newCategory = {
        id: this.generateId(),
        new: true,
        name: this.newCategory.name || defaultName,
        displayInForm: true,
        displayInMenu: true,
        displayInInfoBar: true,
        index: this.option && this.option.subcategories.length > 0 ? Math.max(...this.option.subcategories.map(o => o.index)) + 1 : 0,
        type: "Group",
        options: []
      }
      this.option.subcategories.push(newCategory)
      this.newCategory = {}

      setTimeout(() => { // nextTick does not work here, don't know why...
        this.expanded = true
        // focus newly created group
        this.$nextTick(function() {
          this.$refs.categories[this.$refs.categories.length -1].$refs.newOptionName.focus()
        })
      }, 0)
    }
  },
  computed: {
    expandable() {
      return this.option.subcategories && this.option.subcategories.length > 0
    }
  },
  watch: {
    option: {
      handler(oldVal, newVal) {
        newVal = { ...this.option }
        oldVal = { ...this.previousState }
        delete newVal.subcategories
        delete oldVal.subcategories
        if (this.option.name && JSON.stringify(newVal).trim() != JSON.stringify(oldVal).trim()) {
          // console.log("editing option", oldVal, newVal)
          this.option.edited = true
        }
        this.previousState = { ...this.option }
      },
      deep: true
    }
  }
}
</script>

<style lang="scss">
  .option-children .option-children {
    padding-left: 2.5rem;
    border-left: 1px solid #d2d6de;
    padding-bottom: 1.2rem;
  }
  .option-item {
    .node-content {
      &.expandable {
        cursor: pointer;
        &:hover .node-expand {
          color: #337ab7;
        }
      }
    }
    .name {
      margin-left: 5px;
      padding-left: 7px;
      width: auto;
      flex-grow: 1;
      margin-right: 10px;
      transition: border-color 0s;
      &:not(:focus):not(:hover) { border-color: transparent !important; }
    }
    .marker-shape-picker {
      width: 3rem;
      &.default {
        & > .shape-item {
          opacity: .4;
        }
      }
      & > .shape-item {
        transform: scale(0.33);
      }
    }
  }
  .new-category-name {
    width: auto;
    background-color: transparent;
    font-weight: bold;
    border: none;
    height: auto;
    padding-left: 7px;
    color: white;
    margin-right: .5rem;
    &:focus {
      background-color: #fdfdfd;
      color: #333;
      &::placeholder {
        color: grey;
      }
    }
    &::placeholder {
      color: #efefef;
    }
  }
  .shortcuts {
    display: flex;
    align-items: center;
  }
  .node-expand {
    width: 3rem;
    padding: 5px 1.7rem;
    align-items: center;
    justify-content: center;
    display: inline-flex;
    cursor: pointer;
    &:hover {
      color: #337ab7;
    }
    .add-subcategories {
      font-size: 1.3rem;
      opacity: .9;
    }
  }
</style>
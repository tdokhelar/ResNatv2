<template>
  <div class="form-group" :data-type="type" :class="disabledClass">

    <!-- Checkbox -->
    <template v-if="type == 'checkbox'">
      <label class="control-label checkbox-label" :title="helpValue">
        <input type="checkbox" v-model="model[field]" />
        <span>{{ labelValue }}</span>
      </label>
    </template>

    <!-- Color -->
    <template v-else-if="type == 'color'">
      <label class="control-label checkbox-label" :title="helpValue">
        <ColorInput v-model="model[field]" />
        <span>{{ labelValue }}</span>
      </label>
    </template>

    <!-- Osm Tags -->
    <template v-else-if="type == 'osm-tags'">
      <label class="control-label" :title="helpValue">{{ labelValue }}</label>
      <OsmTagsInput v-model="model[field]" />
    </template>

    <!-- Other -->
    <template v-else>
      <label class="control-label" :title="helpValue">{{ labelValue }}</label>
      <input :type="type" class="form-control" :placeholder="placeholderValue"
             v-model="model[field]" />
    </template>
  </div>
</template>

<script>
import AdminItem from './admin-item-mixin'
import ColorInput from '../components/ColorInput.vue'
import OsmTagsInput from '../components/OsmTagsInput.vue'

export default {
  mixins: [AdminItem],
  components: { ColorInput, OsmTagsInput },
  props: ['field', 'type', 'disableBy'],
  methods: {
    translationKey(suffix = '') {
      return `admin.${this.modelName}.fields.${this.field}${suffix}`
    }
  },
  computed: {
    disabledClass() {
      if (!this.disableBy) return
      return this.model[this.disableBy] ? '' : 'disabled'
    }
  }
}
</script>

<style lang="scss">
  .form-group {
    margin-bottom: 10px;
    &[data-type="checkbox"] {
      margin-bottom: 0;
    }
    &.disabled {
      opacity: .5;
    }
  }
  .checkbox-label {
    cursor: pointer;
    display: flex;
    align-items: center;
    input { margin: 0px 5px 0 0; }
  }
</style>


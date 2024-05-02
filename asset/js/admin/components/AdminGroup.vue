<template>
  <div :class="`box box-${color}`">
    <div class="box-header">
      <h4 class="box-title">{{ labelValue }}</h4>
      <p v-if="hintValue">{{ hintValue }}</p>
    </div>

    <div class="box-body">
      <slot></slot>
    </div>
  </div>
</template>

<script>
import AdminItem from './admin-item-mixin'

export default {
  mixins: [AdminItem],
  props: {
    label: {
      type: String,
      default: ""
    },
    color: {
      type: String,
      default: "default"
    },
    customKey: {
      type: String,
      default: null
    }
  },
  methods: {
    translationKey(suffix = '') {
      return (this.customKey || `admin.${this.modelName}.form.groups.${this.label}`) + suffix
    }
  }
}
</script>

<style lang='scss' scoped>

</style>
<template>
  <div class="osm-tags-field">
    <div class="osm-tag-container" v-for="(tag, index) in tagsArray" :key="tag.key">
        <osm-condition :key="tag.key" :condition="tag">
        </osm-condition>
        <button type="button" @click="tagsArray.splice(index,1)" class="btn btn-default btn-icon">
            <i class="fa fa-trash"></i>
        </button>
    </div>
    <button type="button" class="btn btn-sm btn-default" 
            @click="tagsArray.push({key: '', value: ''}); onInput();">
        {{ t('admin.options.fields.add_a_tag') }}
    </button>
  </div>
</template>

<script>
import OsmCondition from '../element-import/OsmQueryBuilderCondition.vue'

export default {
  props: ['value'],
  components: { OsmCondition },
  data() {
    return {
      preventEmit: false,
      tagsArray: []
    }
  },
  methods: {
    updateTagsArray() {
      if (this.value == 'null') {
        this.tagsArray = []
        return
      }
      let result = []
      const value = this.value || {}
      for(let key in value) {
        result.push({key: key, value: this.value[key]})
      }
      this.tagsArray = result
    },
    onInput() {
      let result = {}
      for(let tag of this.tagsArray) {
        result[tag.key] = tag.value
      }
      if (Object.keys(result).length == 0) result = "null"
      if (!this.preventEmit) this.$emit('input', result)
      this.preventEmit = false
    }
  },
  mounted() {
    this.updateTagsArray()
  },
  watch: {
    value() {
      this.preventEmit = true // prevent infinte loop
      this.updateTagsArray()
    },
    tagsArray: {
      handler() {
        this.onInput()
      },
      deep: true
    }
  }
}
</script>

<style lang='scss'>
  .osm-tag-container {
    display: flex;
    margin: 10px 0;
    .btn {
      padding: 0 12px !important;
      display: flex;
      align-items: center;
      height: 30px;
    }
  }
  .condition {
    display: flex;
    flex: 1 auto;
  }
  .condition-operator { display: none }
</style>
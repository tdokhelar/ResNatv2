<template>
  <div class="size-input-container">
    <div class="size-input" :class="{disabled: useDefaultSize}">
      <span class="fa fa-minus" @click="changeValue(-0.1)"></span>
      <input type="text" readonly :value="internalValue" class="form-control" style="width: 50px" />
      <span class="fa fa-plus" @click="changeValue(0.1)"></span>
    </div>
    <label class="control-label checkbox-label">
      <input type="checkbox" v-model="useDefaultSize" />
      <span>{{ t('admin.options.fields.markerSize_default') }}</span>
      <a href="#" @click.stop.prevent="openMarkerConfig" style="margin-left: 8px">
        {{ t('admin.options.fields.markerShape_choices.change_default') }}
      </a>
    </label>
  </div>
</template>

<script>
export default {
  props: ['value'],
  data() {
    return {
      internalValue: null,
      useDefaultSize: true,
    }
  },
  mounted() {
    this.updateValues()
  },
  watch: {
    value() {
      this.updateValues()
    },
    useDefaultSize() {
      if (this.useDefaultSize) {
        this.internalValue = null
        this.$emit('input', '')
      } else {
        this.internalValue = 1
      }
    },
  },
  methods: {
    updateValues() {
      this.internalValue = this.value
      this.useDefaultSize = !this.value
    },
    openMarkerConfig() {
      const href = $('.sidebar').find('a[href*=configuration-marker]').attr('href')
      window.open(href, '_blank')
    },
    changeValue(add) {
      if (!this.internalValue) this.internalValue = 1
      this.internalValue += add
      this.useDefaultSize = false
      this.$emit('input', this.internalValue)
    }
  }
}
</script>

<style lang='scss' scoped>
  .size-input-container {
    display: flex;
    align-items: center;
  }
  .size-input {
    display: flex;
    align-items: center;
    input[readonly] {
      margin: 0 .5rem;
    }
    &.disabled {
      opacity: .5;
    }
  }
  .checkbox-label {
    margin: 0 0 0 1.5rem;
  }
</style>
<template>
  <div class="modal fade" :class="{in: visible}">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <button @click="hide" class="close" type="button">×</button>
          <h4 class="modal-title">{{ title }}</h4>
        </div>
        <div class="modal-body">
          <slot></slot>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary" @click="hide">Ok</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: ['title'],
  data() {
    return {
      visible: false
    }
  },
  methods: {
    show() {
      this.visible = true
    },
    hide() {
      this.visible = false
    }
  }
}
</script>

<style lang='scss' scoped>
  .modal.in {
    display: block;
  }
  .modal-body {
    max-height: 80vh;
    overflow: auto;
  }
</style>